<?php

namespace Tests\Feature\Documents;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentService;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([RolePermissionSeeder::class, OrganizationSettingsSeeder::class]);
    }

    public function test_administrator_can_upload_and_download_a_randomly_named_private_document(): void
    {
        $administrator = $this->userWithRole('administrator');
        $category = $this->category();

        $response = $this->actingAs($administrator)->post(route('documents.store'), [
            'title' => 'Signed venue agreement',
            'document_category_id' => $category->id,
            'expiry_date' => now()->addYear()->toDateString(),
            'version_notes' => 'Initial signed copy',
            'file' => $this->pdf('venue-agreement.pdf'),
        ]);

        $document = Document::query()->with('currentVersion')->firstOrFail();
        $response->assertRedirect(route('documents.show', $document));
        $this->assertStringStartsWith('documents/', $document->currentVersion->path);
        $this->assertStringNotContainsString('venue-agreement', $document->currentVersion->path);
        Storage::disk('local')->assertExists($document->currentVersion->path);
        $download = $this->actingAs($administrator)->get(route('documents.download', $document));
        $download->assertOk();
        $this->assertStringContainsString('private', (string) $download->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', (string) $download->headers->get('cache-control'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.created', 'subject_id' => $document->id]);
    }

    public function test_invalid_file_is_rejected_and_never_stored(): void
    {
        $administrator = $this->userWithRole('administrator');

        $this->actingAs($administrator)->post(route('documents.store'), [
            'title' => 'Executable',
            'file' => UploadedFile::fake()->createWithContent('payload.exe', 'MZ executable'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
        Storage::disk('local')->assertDirectoryEmpty('documents');
    }

    public function test_document_access_honors_direct_ownership_and_denies_other_users(): void
    {
        $administrator = $this->userWithRole('administrator');
        $owner = $this->userWithRole('staff');
        $other = $this->userWithRole('staff');
        $staffRole = Role::query()->where('slug', 'staff')->firstOrFail();
        $staffRole->permissions()->syncWithoutDetaching(Permission::query()
            ->whereIn('slug', ['documents.view', 'documents.download'])->pluck('id'));
        $owner->unsetRelation('roles');
        $other->unsetRelation('roles');

        $document = app(DocumentService::class)->create([
            'title' => 'Private staff plan',
        ], $this->pdf('plan.pdf'), [$owner], $administrator);

        $this->actingAs($owner)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($owner)->get(route('documents.download', $document))->assertOk();
        $this->actingAs($other)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($other)->get(route('documents.download', $document))->assertForbidden();
    }

    public function test_replacement_preserves_prior_version_notes_and_files(): void
    {
        $administrator = $this->userWithRole('administrator');
        $company = Company::query()->firstOrFail();
        $service = app(DocumentService::class);
        $document = $service->create(['title' => 'Contract', 'version_notes' => 'Draft'], $this->pdf('draft.pdf'), [$company], $administrator);
        $firstPath = $document->currentVersion->path;

        $second = $service->replace($document, $this->pdf('signed.pdf', 'signed'), 'Signed by both parties', $administrator);
        $document->refresh()->load('versions');

        $this->assertCount(2, $document->versions);
        $this->assertSame(2, $second->version_number);
        $this->assertSame('Signed by both parties', $second->notes);
        $this->assertSame('Draft', $document->versions->firstWhere('version_number', 1)->notes);
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('local')->assertExists($second->path);
    }

    public function test_archive_uses_status_history_and_public_direct_link_is_unavailable(): void
    {
        $administrator = $this->userWithRole('administrator');
        $document = app(DocumentService::class)->create(
            ['title' => 'Archived plan'], $this->pdf('plan.pdf'), [Company::query()->firstOrFail()], $administrator,
        );
        $path = $document->currentVersion->path;

        $this->actingAs($administrator)->delete(route('documents.destroy', $document), ['reason' => 'Superseded'])
            ->assertRedirect(route('documents.index'));
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'archived']);
        $this->assertDatabaseHas('status_histories', [
            'subject_type' => $document->getMorphClass(), 'subject_id' => $document->id,
            'from_status' => 'active', 'to_status' => 'archived', 'actor_user_id' => $administrator->id,
        ]);
        $this->assertContains($this->get('/storage/'.$path)->getStatusCode(), [403, 404]);
    }

    private function category(): DocumentCategory
    {
        return DocumentCategory::query()->create([
            'name' => 'Contracts', 'slug' => 'contracts', 'is_active' => true, 'sort_order' => 10,
        ]);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user->roles()->attach($role, ['assigned_at' => now()]);

        return $user->load('roles');
    }

    private function pdf(string $name, string $body = 'document'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n{$body}\n%%EOF");
    }
}
