<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendors\VendorAssignmentRequest;
use App\Http\Requests\Vendors\VendorAssignmentTransitionRequest;
use App\Http\Requests\Vendors\VendorDocumentRequest;
use App\Http\Requests\Vendors\VendorRatingRequest;
use App\Http\Requests\Vendors\VendorWorkOrderRequest;
use App\Models\Event;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorAssignment;
use App\Models\VendorCategory;
use App\Models\VendorInvoice;
use App\Models\VendorWorkOrder;
use App\Services\BranchScope;
use App\Services\SettingsService;
use App\Services\StatusTransitionService;
use App\Services\VendorAssignmentService;
use App\Services\VendorDocumentService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EventVendorAssignmentController extends Controller
{
    public function index(Request $request, Event $event, SettingsService $settings, BranchScope $branches): View
    {
        Gate::authorize('viewModule', [$event, 'vendors']);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['draft', 'approved', 'in_progress', 'completed', 'cancelled'])],
            'vendor' => ['nullable', 'integer', 'exists:vendors,id'],
        ]);
        $query = $event->vendorAssignments()->with(['vendor', 'category', 'responsibleManager', 'rating']);
        if (! $request->user()->hasPermission('vendors.view-work')) {
            $query->whereHas('vendor', fn ($vendors) => $vendors->where('user_id', $request->user()->id));
        }
        $assignments = $query->search($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['vendor'] ?? null, fn ($query, $value) => $query->where('vendor_id', $value))
            ->orderByDesc('scheduled_starts_at')->paginate(20)->withQueryString();

        return view('events.vendors.index', [
            'event' => $event->load(['client', 'category']), 'assignments' => $assignments,
            'filters' => $filters, 'vendors' => $branches->apply(Vendor::query()->where('status', 'active'), $request->user())->with('categories')->orderBy('display_name')->get(),
            'categories' => VendorCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'managers' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'currency' => $settings->string('general.currency'),
        ]);
    }

    public function store(VendorAssignmentRequest $request, Event $event, VendorAssignmentService $service): RedirectResponse
    {
        $assignment = $service->create($event, $request->assignmentAttributes(), $request->user());

        return redirect()->route('events.vendors.show', [$event, $assignment])
            ->with($assignment->availability_warning ? 'warning' : 'status', $assignment->availability_warning ? 'Assignment created with an availability warning.' : 'Vendor assigned.');
    }

    public function show(Request $request, Event $event, VendorAssignment $assignment): View
    {
        $this->assertNested($event, $assignment);
        Gate::authorize('view', $assignment);
        $assignment->load([
            'event', 'vendor', 'category', 'responsibleManager', 'rating.reviewer',
            'workOrders.statusHistory.actor', 'contracts.document.currentVersion',
            'invoices.document.currentVersion', 'statusHistory.actor',
        ]);

        $managers = User::query()->where('is_active', true)->orderBy('name')->get();

        return view('events.vendors.show', compact('event', 'assignment', 'managers'));
    }

    public function update(VendorAssignmentRequest $request, Event $event, VendorAssignment $assignment, VendorAssignmentService $service): RedirectResponse
    {
        $this->assertNested($event, $assignment);
        $assignment = $service->update($assignment, $request->assignmentAttributes(), $request->user());

        return back()->with($assignment->availability_warning ? 'warning' : 'status', $assignment->availability_warning ? 'Assignment updated with an availability warning.' : 'Assignment updated.');
    }

    public function transition(VendorAssignmentTransitionRequest $request, Event $event, VendorAssignment $assignment, VendorAssignmentService $service): RedirectResponse
    {
        $this->assertNested($event, $assignment);
        $service->transition($assignment, $request->validated('status'), $request->user(), $request->validated('reason'), $request->validated('completion_notes'));

        return back()->with('status', 'Assignment status updated.');
    }

    public function updateDelivery(Request $request, Event $event, VendorAssignment $assignment, VendorAssignmentService $service): RedirectResponse
    {
        $this->assertNested($event, $assignment);
        Gate::authorize('update', $assignment);
        $data = $request->validate([
            'delivery_status' => ['required', Rule::in(['pending', 'scheduled', 'in_progress', 'delivered', 'issue'])],
            'delivery_notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $service->updateDelivery($assignment, $data, $request->user());

        return back()->with('status', 'Delivery update recorded.');
    }

    public function storeWorkOrder(VendorWorkOrderRequest $request, Event $event, VendorAssignment $assignment, VendorAssignmentService $service): RedirectResponse
    {
        $this->assertNested($event, $assignment);
        $service->createWorkOrder($assignment, $request->validated(), $request->user());

        return back()->with('status', 'Work order created.');
    }

    public function transitionWorkOrder(Request $request, Event $event, VendorAssignment $assignment, VendorWorkOrder $workOrder, VendorAssignmentService $service): RedirectResponse
    {
        $this->assertNested($event, $assignment);
        abort_unless($workOrder->vendor_assignment_id === $assignment->getKey(), 404);
        Gate::authorize('update', $assignment);
        $data = $request->validate(['status' => ['required', Rule::in(['issued', 'accepted', 'in_progress', 'completed', 'cancelled'])], 'reason' => ['nullable', 'string', 'max:2000']]);
        $service->transitionWorkOrder($workOrder, $data['status'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'Work order status updated.');
    }

    public function storeContract(VendorDocumentRequest $request, Event $event, VendorAssignment $assignment, VendorDocumentService $service): RedirectResponse
    {
        $this->assertNested($event, $assignment);
        $service->addContract($assignment, $request->safe()->except('file'), $request->file('file'), $request->user());

        return back()->with('status', 'Contract uploaded to protected storage.');
    }

    public function storeInvoice(VendorDocumentRequest $request, Event $event, VendorAssignment $assignment, VendorDocumentService $service): RedirectResponse
    {
        $this->assertNested($event, $assignment);
        $service->addInvoice($assignment, $request->safe()->except('file'), $request->file('file'), $request->user());

        return back()->with('status', 'Vendor invoice uploaded to protected storage. No Expense was created.');
    }

    public function transitionInvoice(Request $request, Event $event, VendorAssignment $assignment, VendorInvoice $invoice, StatusTransitionService $statuses): RedirectResponse
    {
        $this->assertNested($event, $assignment);
        abort_unless($invoice->vendor_assignment_id === $assignment->getKey(), 404);
        Gate::authorize('view', $assignment);
        abort_unless($request->user()->hasPermission('vendors.approve-cost'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'disputed', 'void'])], 'reason' => ['nullable', 'string', 'max:2000']]);
        try {
            $statuses->transition($invoice, $data['status'], $request->user(), $data['reason'] ?? null, [], 'vendor.invoice.status_changed');
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return back()->with('status', 'Invoice metadata status updated.');
    }

    public function storeRating(VendorRatingRequest $request, Event $event, VendorAssignment $assignment, VendorAssignmentService $service): RedirectResponse
    {
        $this->assertNested($event, $assignment);
        $service->rate($assignment, $request->validated(), $request->user());

        return back()->with('status', 'Vendor rating recorded.');
    }

    private function assertNested(Event $event, VendorAssignment $assignment): void
    {
        abort_unless($assignment->event_id === $event->getKey(), 404);
    }
}
