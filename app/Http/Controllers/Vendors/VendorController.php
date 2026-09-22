<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendors\VendorRequest;
use App\Models\Branch;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategory;
use App\Services\BranchScope;
use App\Services\VendorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class VendorController extends Controller
{
    public function index(Request $request, BranchScope $branches): View
    {
        Gate::authorize('viewAny', Vendor::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:active,archived'], 'category' => ['nullable', 'integer', 'exists:vendor_categories,id'], 'branch' => ['nullable', 'integer', 'exists:branches,id']]);
        $query = $request->user()->hasPermission('vendors.view') ? $branches->apply(Vendor::query(), $request->user()) : Vendor::query()->where('user_id', $request->user()->id);
        $vendors = $query->with(['branch', 'user', 'categories'])->search($filters['q'] ?? null)->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v), fn ($q) => $q->where('status', 'active'))->when($filters['category'] ?? null, fn ($q, $v) => $q->whereHas('categories', fn ($c) => $c->whereKey($v)))->when($filters['branch'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))->orderBy('display_name')->paginate(20)->withQueryString();

        return view('vendors.index', ['vendors' => $vendors, 'filters' => $filters, 'categories' => VendorCategory::query()->where('is_active', true)->orderBy('name')->get(), 'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Vendor::class);

        return view('vendors.create', $this->options());
    }

    public function store(VendorRequest $request, VendorService $service): RedirectResponse
    {
        $vendor = $service->create($request->validated(), $request->user());

        return redirect()->route('vendors.show', $vendor)->with('status', 'Vendor created without creating a login.');
    }

    public function show(Vendor $vendor): View
    {
        Gate::authorize('view', $vendor);
        $vendor->load([
            'categories', 'contacts', 'serviceAreas', 'user', 'branch', 'statusHistory.actor',
            'documentLinks.document.currentVersion', 'availabilities',
            'assignments.event', 'assignments.category', 'assignments.rating', 'ratings',
        ]);
        $users = User::query()->where('is_active', true)->whereNull('deleted_at')->whereHas('roles', fn ($q) => $q->where('slug', 'vendor'))->where(fn ($q) => $q->whereDoesntHave('vendorProfile')->when($vendor->user_id, fn ($q, $id) => $q->orWhere('users.id', $id)))->orderBy('name')->get();

        return view('vendors.show', compact('vendor', 'users'));
    }

    public function edit(Vendor $vendor): View
    {
        Gate::authorize('update', $vendor);

        return view('vendors.edit', $this->options() + ['vendor' => $vendor->load('categories')]);
    }

    public function update(VendorRequest $request, Vendor $vendor, VendorService $service): RedirectResponse
    {
        $vendor = $service->update($vendor, $request->validated(), $request->user());

        return redirect()->route('vendors.show', $vendor)->with('status', 'Vendor updated.');
    }

    private function options(): array
    {
        return ['categories' => VendorCategory::query()->where('is_active', true)->orderBy('name')->get(), 'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get()];
    }
}
