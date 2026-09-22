<?php

namespace Tests\Feature\Finance;

use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\Event;
use App\Models\EventModuleSetting;
use App\Models\Expense;
use App\Models\FinanceCategory;
use App\Models\Income;
use App\Models\Role;
use App\Models\User;
use App\Services\EventModuleDataRegistry;
use App\Services\FinanceService;
use Database\Seeders\EventConfigurationSeeder;
use Database\Seeders\FinanceCategorySeeder;
use Database\Seeders\OrganizationSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            OrganizationSettingsSeeder::class,
            FinanceCategorySeeder::class,
            EventConfigurationSeeder::class,
        ]);
    }

    public function test_budget_and_actuals_remain_separate_and_profit_uses_posted_decimal_entries_only(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $finance = app(FinanceService::class);
        $budget = $finance->createBudget($event, $admin);
        $finance->addBudgetLine($budget, [
            'finance_category_id' => $this->category('sponsorship')->id,
            'direction' => 'income', 'description' => 'Sponsor target', 'planned_amount' => '150.1001',
        ], $admin);
        $finance->addBudgetLine($budget, [
            'finance_category_id' => $this->category('venue')->id,
            'direction' => 'expense', 'description' => 'Venue target', 'planned_amount' => '50.0001',
        ], $admin);
        $income = $finance->createIncome($event, $this->entry('sponsorship', '100.1001'), $admin);
        $expense = $finance->createExpense($event, $this->entry('venue', '40.0001'), $admin);
        $finance->createIncome($event, $this->entry('donations', '999.9999'), $admin);
        $finance->postIncome($income, $admin);
        $finance->postExpense($expense, $admin);

        $summary = $finance->summary($event);
        $this->assertSame('150.1001', $summary['planned_income']);
        $this->assertSame('50.0001', $summary['planned_expense']);
        $this->assertSame('100.1001', $summary['actual_income']);
        $this->assertSame('40.0001', $summary['actual_expense']);
        $this->assertSame('60.1000', $summary['profit']);
        $this->assertSame('100.1001', $summary['categories']->firstWhere('category', 'Sponsorship')['actual_income']);
        $this->assertDatabaseHas('status_histories', ['subject_type' => (new Income)->getMorphClass(), 'subject_id' => $income->id, 'from_status' => 'draft', 'to_status' => 'posted']);
    }

    public function test_posted_entry_is_immutable_and_void_creates_equal_posted_reversal(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $finance = app(FinanceService::class);
        $income = $finance->createIncome($event, $this->entry('sponsorship', '125.5000'), $admin);
        $finance->postIncome($income, $admin);

        try {
            $finance->updateIncome($income->refresh(), $this->entry('sponsorship', '999.0000'), $admin);
            $this->fail('Posted entries must not be editable.');
        } catch (ValidationException) {
            $this->assertSame('125.5000', $income->refresh()->amount);
        }

        $finance->voidIncome($income->refresh(), 'Duplicate bank statement import', $admin);
        $income->refresh();
        $reversal = Income::query()->where('reversal_of_id', $income->id)->firstOrFail();
        $this->assertSame('posted', $income->status);
        $this->assertNotNull($income->reversed_at);
        $this->assertTrue($reversal->is_reversal);
        $this->assertSame('posted', $reversal->status);
        $this->assertSame('125.5000', $reversal->amount);
        $this->assertSame('0.0000', $finance->summary($event)['actual_income']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'finance.income_reversed', 'subject_id' => $income->id]);
    }

    public function test_permissions_separate_draft_entry_from_posting_and_hide_finance_from_staff(): void
    {
        $manager = $this->userWithRole('event-manager');
        $financeUser = $this->userWithRole('finance-accounts');
        $staff = $this->userWithRole('staff');
        $event = $this->event();
        app(FinanceService::class)->createBudget($event, $manager);

        $this->actingAs($manager)->post(route('events.budget.incomes.store', $event), $this->entry('sponsorship', '20.0000'))
            ->assertRedirect();
        $income = Income::query()->firstOrFail();
        $this->actingAs($manager)->post(route('events.budget.incomes.post', [$event, $income]))->assertForbidden();
        $this->actingAs($financeUser)->post(route('events.budget.incomes.post', [$event, $income]))->assertRedirect();
        $this->assertSame('posted', $income->refresh()->status);
        $this->actingAs($staff)->get(route('events.budget.index', $event))->assertForbidden();
    }

    public function test_event_isolation_rejects_nested_entry_from_another_event(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $other = $this->event();
        $income = app(FinanceService::class)->createIncome($event, $this->entry('sponsorship', '10.0000'), $admin);

        $this->actingAs($admin)->post(route('events.budget.incomes.post', [$other, $income]))->assertNotFound();
        $this->assertSame('draft', $income->refresh()->status);
    }

    public function test_source_identity_is_idempotent_and_evidence_must_belong_to_event(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        $finance = app(FinanceService::class);
        $data = $this->entry('venue', '55.0000') + ['source_type' => 'vendor_assignment', 'source_id' => 77];
        $first = $finance->createExpense($event, $data, $admin);
        $second = $finance->createExpense($event, $data, $admin);
        $this->assertTrue($first->is($second));
        $this->assertSame(1, Expense::query()->count());

        $otherEvent = $this->event();
        $document = Document::query()->create(['title' => 'Other Event receipt', 'status' => 'active', 'uploaded_by_user_id' => $admin->id]);
        DocumentLink::query()->create([
            'document_id' => $document->id, 'linkable_type' => $otherEvent->getMorphClass(),
            'linkable_id' => $otherEvent->id, 'relationship' => 'attachment',
            'created_by_user_id' => $admin->id, 'created_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $finance->createExpense($event, $this->entry('venue', '12.0000') + ['evidence_document_id' => $document->id], $admin);
    }

    public function test_disabled_budget_module_protects_direct_route_and_preserves_detected_data(): void
    {
        $admin = $this->userWithRole('administrator');
        $event = $this->event();
        app(FinanceService::class)->createBudget($event, $admin);
        $event->moduleSettings()->where('module_key', 'budget')->update(['is_enabled' => false]);

        $this->assertTrue(app(EventModuleDataRegistry::class)->hasData($event, 'budget'));
        $this->actingAs($admin)->get(route('events.budget.index', $event))
            ->assertRedirect(route('events.modules.edit', $event));
        $this->assertDatabaseHas('event_budgets', ['event_id' => $event->id]);
        $event->moduleSettings()->where('module_key', 'budget')->update(['is_enabled' => true]);
        $this->actingAs($admin)->get(route('events.budget.index', $event))->assertOk()->assertSee('Budget and financial ledger');
    }

    public function test_approved_budget_is_locked_and_category_direction_is_enforced(): void
    {
        $admin = $this->userWithRole('administrator');
        $finance = app(FinanceService::class);
        $budget = $finance->createBudget($this->event(), $admin);
        $finance->addBudgetLine($budget, [
            'finance_category_id' => $this->category('venue')->id,
            'direction' => 'expense', 'description' => 'Venue baseline', 'planned_amount' => '100.0000',
        ], $admin);
        $finance->approveBudget($budget, $admin);

        $this->expectException(ValidationException::class);
        $finance->addBudgetLine($budget->refresh(), [
            'finance_category_id' => $this->category('sponsorship')->id,
            'direction' => 'income', 'description' => 'Late change', 'planned_amount' => '1.0000',
        ], $admin);
    }

    private function event(): Event
    {
        $event = Event::factory()->create(['status' => 'planning', 'currency_code' => 'USD']);
        EventModuleSetting::query()->updateOrCreate(
            ['event_id' => $event->id, 'module_key' => 'budget'],
            ['is_enabled' => true, 'source' => 'manual'],
        );

        return $event;
    }

    private function category(string $slug): FinanceCategory
    {
        return FinanceCategory::query()->where('slug', $slug)->firstOrFail();
    }

    private function entry(string $categorySlug, string $amount): array
    {
        return [
            'finance_category_id' => $this->category($categorySlug)->id,
            'amount' => $amount, 'transaction_date' => '2026-09-19',
            'description' => str($categorySlug)->headline()->toString(),
        ];
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail(), ['assigned_at' => now()]);

        return $user->fresh('roles.permissions');
    }
}
