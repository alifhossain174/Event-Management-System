<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\EventCategory;
use App\Models\FinanceCategory;
use App\Models\InventoryCategory;
use App\Models\LoginHistory;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VendorCategory;
use App\Policies\AuditLogPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ClientPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\LoginHistoryPolicy;
use App\Policies\SystemSettingPolicy;
use App\Policies\UserPolicy;
use App\Support\Navigation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(LoginHistory::class, LoginHistoryPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(SystemSetting::class, SystemSettingPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(EventCategory::class, CategoryPolicy::class);
        Gate::policy(VendorCategory::class, CategoryPolicy::class);
        Gate::policy(InventoryCategory::class, CategoryPolicy::class);
        Gate::policy(FinanceCategory::class, CategoryPolicy::class);
        Gate::policy(DocumentCategory::class, CategoryPolicy::class);

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
    }
}
