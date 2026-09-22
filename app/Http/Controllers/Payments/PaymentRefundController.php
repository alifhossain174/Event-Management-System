<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StoreRefundRequest;
use App\Models\Event;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;

final class PaymentRefundController extends Controller
{
    public function __invoke(StoreRefundRequest $request, Event $event, Payment $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless($payment->event_id === $event->getKey(), 404);
        $refund = $payments->refund($payment, $request->validated(), $request->user());

        return back()->with('status', 'Refund '.$refund->refund_number.' posted. The original payment was preserved.');
    }
}
