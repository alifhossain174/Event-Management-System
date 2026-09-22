<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class PaymentReceiptController extends Controller
{
    public function __invoke(Event $event, Payment $payment, PaymentService $payments, SettingsService $settings): View
    {
        abort_unless($payment->event_id === $event->getKey(), 404);
        Gate::authorize('view', $payment);

        return view('payments.receipt', [
            'event' => $event, 'payment' => $payment->load(['client', 'receivedBy', 'allocations.schedule', 'refunds.allocation']),
            'refundable' => $payments->paymentRefundable($payment),
            'unallocatedRefundable' => $payments->unallocatedRefundable($payment),
            'organizationTimezone' => $settings->string('general.timezone'),
        ]);
    }
}
