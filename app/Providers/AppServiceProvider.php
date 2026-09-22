<?php

namespace App\Providers;

use App\Contracts\ApprovedVendorCostProvider;
use App\Contracts\BookingConflictChecker;
use App\Contracts\BookingFinancialImpactInspector;
use App\Contracts\RegistrationSpamGuard;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Event;
use App\Models\EventBudget;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\Expense;
use App\Models\FinanceCategory;
use App\Models\Guest;
use App\Models\Income;
use App\Models\InventoryCategory;
use App\Models\Invoice;
use App\Models\LoginHistory;
use App\Models\MessageTemplate;
use App\Models\ModuleDefinition;
use App\Models\NotificationRecipient;
use App\Models\OutboundMessage;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationForm;
use App\Models\StaffAssignment;
use App\Models\StaffProfile;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorAssignment;
use App\Models\VendorCategory;
use App\Models\Venue;
use App\Policies\AuditLogPolicy;
use App\Policies\BookingPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ClientPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EventBudgetPolicy;
use App\Policies\EventPolicy;
use App\Policies\EventTemplatePolicy;
use App\Policies\ExpensePolicy;
use App\Policies\GuestPolicy;
use App\Policies\IncomePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LoginHistoryPolicy;
use App\Policies\MessageTemplatePolicy;
use App\Policies\ModuleDefinitionPolicy;
use App\Policies\NotificationRecipientPolicy;
use App\Policies\OutboundMessagePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RegistrationFormPolicy;
use App\Policies\RegistrationPolicy;
use App\Policies\StaffAssignmentPolicy;
use App\Policies\StaffProfilePolicy;
use App\Policies\SystemSettingPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use App\Policies\VendorAssignmentPolicy;
use App\Policies\VendorPolicy;
use App\Policies\VenuePolicy;
use App\Services\CommunicationChannelRegistry;
use App\Services\EventModuleDataRegistry;
use App\Services\GlobalSearchService;
use App\Services\HoneypotRegistrationSpamGuard;
use App\Services\NotificationInboxService;
use App\Services\NullBookingFinancialImpactInspector;
use App\Services\VendorApprovedCostProvider;
use App\Services\VenueBookingConflictChecker;
use App\Support\Navigation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BookingConflictChecker::class, VenueBookingConflictChecker::class);
        $this->app->bind(BookingFinancialImpactInspector::class, NullBookingFinancialImpactInspector::class);
        $this->app->bind(ApprovedVendorCostProvider::class, VendorApprovedCostProvider::class);
        $this->app->bind(RegistrationSpamGuard::class, HoneypotRegistrationSpamGuard::class);

        $this->app->singleton(CommunicationChannelRegistry::class, function ($app) {
            return new CommunicationChannelRegistry(
                collect(config('communications.channels', []))->map(fn (string $channel) => $app->make($channel)),
            );
        });

        $this->app->singleton(GlobalSearchService::class, function ($app) {
            $providers = collect(config('global-search.providers', []))
                ->map(fn (string $provider) => $app->make($provider));

            return new GlobalSearchService($providers);
        });

        $this->app->singleton(EventModuleDataRegistry::class, function ($app) {
            $registry = new EventModuleDataRegistry(config('event-modules.event_scoped_keys', []));

            foreach (config('event-modules.data_detectors', []) as $detectorClass) {
                $registry->register($app->make($detectorClass));
            }

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(LoginHistory::class, LoginHistoryPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(EventBudget::class, EventBudgetPolicy::class);
        Gate::policy(Income::class, IncomePolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Guest::class, GuestPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(VendorAssignment::class, VendorAssignmentPolicy::class);
        Gate::policy(Venue::class, VenuePolicy::class);
        Gate::policy(StaffProfile::class, StaffProfilePolicy::class);
        Gate::policy(StaffAssignment::class, StaffAssignmentPolicy::class);
        Gate::policy(SystemSetting::class, SystemSettingPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(EventCategory::class, CategoryPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(EventTemplate::class, EventTemplatePolicy::class);
        Gate::policy(ModuleDefinition::class, ModuleDefinitionPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(RegistrationForm::class, RegistrationFormPolicy::class);
        Gate::policy(Registration::class, RegistrationPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(NotificationRecipient::class, NotificationRecipientPolicy::class);
        Gate::policy(OutboundMessage::class, OutboundMessagePolicy::class);
        Gate::policy(MessageTemplate::class, MessageTemplatePolicy::class);
        Gate::policy(VendorCategory::class, CategoryPolicy::class);
        Gate::policy(InventoryCategory::class, CategoryPolicy::class);
        Gate::policy(FinanceCategory::class, CategoryPolicy::class);
        Gate::policy(DocumentCategory::class, CategoryPolicy::class);
        Gate::policy(Department::class, CategoryPolicy::class);

        Gate::before(function (User $user) {
            if (! $user->is_active) {
                return false;
            }

            return null;
        });

        foreach (array_keys(config('rbac.permissions', [])) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        View::composer('*', function ($view) {
            $view->with('navigationGroups', app(Navigation::class)->for(auth()->user()));
        });

        View::composer('components.layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user || ! Schema::hasTable('notification_recipients') || ! $user->hasPermission('notifications.view')) {
                $view->with(['notificationUnreadCount' => 0, 'recentNotifications' => collect()]);

                return;
            }

            $inbox = app(NotificationInboxService::class);
            $view->with([
                'notificationUnreadCount' => $inbox->unreadCount($user),
                'recentNotifications' => $inbox->recent($user),
            ]);
        });
    }
}
