<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BranchScope;
use App\Services\SettingsService;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class]);
    }

    public function test_administrator_can_update_typed_settings_and_changes_are_audited(): void
    {
        $administrator = $this->userWithRole('administrator');
        $settings = app(SettingsService::class);

        $settings->update([
            'general.timezone' => 'Asia/Dhaka',
            'general.currency' => 'BDT',
            'finance.default_tax_rate' => '7.500000',
            'invoice.next_number' => 42,
            'features.branches_enabled' => true,
        ], $administrator);

        $this->assertSame('Asia/Dhaka', $settings->string('general.timezone'));
        $this->assertSame('BDT', $settings->string('general.currency'));
        $this->assertSame('7.500000', $settings->decimal('finance.default_tax_rate'));
        $this->assertSame(42, $settings->integer('invoice.next_number'));
        $this->assertTrue($settings->boolean('features.branches_enabled'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.updated',
            'actor_user_id' => $administrator->id,
        ]);
    }

    public function test_secret_placeholders_are_encrypted_masked_and_never_rendered_back(): void
    {
        $administrator = $this->userWithRole('administrator');
        $secret = 'provider-secret-that-must-not-render';

        $this->actingAs($administrator)->put(route('settings.system.update'), $this->validSettings([
            'email_secret_placeholder' => $secret,
        ]))->assertRedirect();

        $record = SystemSetting::query()->where('key', 'integrations.email_secret_placeholder')->firstOrFail();
        $this->assertTrue($record->is_encrypted);
        $this->assertNotSame($secret, $record->getRawOriginal('value'));
        $this->assertSame($secret, app(SettingsService::class)->secret('integrations.email_secret_placeholder'));

        $this->actingAs($administrator)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Saved')
            ->assertDontSee($secret);

        $audit = AuditLog::query()->where('action', 'settings.updated')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString($secret, json_encode([$audit->before_values, $audit->after_values]));
    }

    public function test_setting_cache_contract_works_with_file_and_database_stores(): void
    {
        $administrator = $this->userWithRole('administrator');
        $originalDriver = Cache::getDefaultDriver();

        try {
            foreach (['file', 'database'] as $driver) {
                Cache::setDefaultDriver($driver);
                $settings = app(SettingsService::class);
                $prefix = mb_strtoupper($driver).'-';
                $settings->update(['invoice.prefix' => $prefix], $administrator);

                $this->assertSame($prefix, $settings->string('invoice.prefix'));
                $this->assertSame($prefix, $settings->string('invoice.prefix'));
                Cache::store($driver)->forget('system-setting:invoice.prefix');
            }
        } finally {
            Cache::setDefaultDriver($originalDriver);
        }
    }

    public function test_company_profile_saves_public_logo_metadata_and_audit_without_file_contents(): void
    {
        Storage::fake('public');
        $administrator = $this->userWithRole('administrator');

        $this->actingAs($administrator)->put(route('settings.company.update'), [
            'name' => 'Example Events',
            'email' => 'hello@example.test',
            'country_code' => 'bd',
            'logo' => UploadedFile::fake()->image('brand.png', 120, 120),
        ])->assertRedirect();

        $company = Company::query()->firstOrFail();
        $this->assertSame('Example Events', $company->name);
        $this->assertSame('BD', $company->country_code);
        $this->assertSame('brand.png', $company->logo_original_name);
        Storage::disk('public')->assertExists($company->logo_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'company.updated', 'subject_id' => $company->id]);
    }

    public function test_branch_mode_is_disabled_by_default_and_scope_is_explicit_and_null_safe(): void
    {
        $administrator = $this->userWithRole('administrator');
        $staff = $this->userWithRole('staff');
        $company = Company::query()->firstOrFail();
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'Head Office',
            'is_active' => true,
        ]);
        $otherBranch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'OTHER',
            'name' => 'Other Office',
            'is_active' => true,
        ]);
        $scope = app(BranchScope::class);

        $this->assertFalse($scope->enabled());
        $this->assertTrue($scope->permits($staff, $branch->id));
        $this->assertCount(2, $scope->apply(Branch::query(), $staff, 'id')->get());

        app(SettingsService::class)->update(['features.branches_enabled' => true], $administrator);
        $this->assertFalse($scope->permits($staff, $branch->id));
        $this->assertTrue($scope->permits($staff, null));

        $staff->branches()->attach($branch, ['assigned_by_user_id' => $administrator->id, 'assigned_at' => now()]);
        $this->assertTrue($scope->permits($staff, $branch->id));
        $this->assertFalse($scope->permits($staff, $otherBranch->id));
        $this->assertSame([$branch->id], $scope->apply(Branch::query(), $staff, 'id')->pluck('id')->all());
        $this->assertCount(2, $scope->apply(Branch::query(), $administrator, 'id')->get());
        $this->assertTrue($scope->permits($administrator, $branch->id));
    }

    public function test_administrator_can_manage_filter_and_archive_branches_with_audit_history(): void
    {
        $administrator = $this->userWithRole('administrator');

        $response = $this->actingAs($administrator)->post(route('settings.branches.store'), [
            'code' => 'DHK',
            'name' => 'Dhaka Office',
            'city' => 'Dhaka',
            'timezone' => 'Asia/Dhaka',
            'is_active' => '1',
        ]);
        $branch = Branch::query()->where('code', 'DHK')->firstOrFail();
        $response->assertRedirect(route('settings.branches.edit', $branch));

        Branch::factory()->count(16)->create(['company_id' => $branch->company_id]);
        $this->actingAs($administrator)
            ->get(route('settings.branches.index', ['q' => 'Dhaka']))
            ->assertOk()
            ->assertSee('Dhaka Office');
        $this->actingAs($administrator)->get(route('settings.branches.index'))->assertSee('pagination');

        $this->actingAs($administrator)->delete(route('settings.branches.destroy', $branch))->assertRedirect(route('settings.branches.index'));
        $this->assertSoftDeleted($branch);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branch.archived', 'subject_id' => $branch->id]);
    }

    public function test_non_privileged_user_cannot_access_settings_branches_or_master_data_directly(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($staff)->get(route('settings.branches.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('settings.master-data.index', 'event-categories'))->assertForbidden();
        $this->actingAs($staff)->post(route('settings.master-data.store', 'event-categories'), [
            'name' => 'Private Party',
            'slug' => 'private-party',
            'sort_order' => 0,
            'is_active' => true,
        ])->assertForbidden();
    }

    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'timezone' => 'UTC',
            'currency' => 'USD',
            'locale' => 'en',
            'date_format' => 'Y-m-d',
            'default_tax_rate' => '0.000000',
            'invoice_prefix' => 'INV-',
            'invoice_next_number' => 1,
            'invoice_number_padding' => 6,
            'branches_enabled' => false,
            'client_portal_enabled' => false,
            'vendor_portal_enabled' => false,
            'staff_portal_enabled' => false,
            'communications_enabled' => false,
            'email_secret_placeholder' => null,
            'sms_secret_placeholder' => null,
            'whatsapp_secret_placeholder' => null,
        ], $overrides);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->attach($role, ['assigned_at' => now()]);

        return $user->fresh('roles');
    }
}
