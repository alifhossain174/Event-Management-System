<?php

namespace App\Services;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Validation\ValidationException;
use Throwable;

final class InvoiceDeliveryService
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly InvoicePdfService $pdf,
        private readonly AuditService $audit,
    ) {}

    public function email(Invoice $invoice, string $recipient, User $actor): InvoiceDelivery
    {
        if (! in_array($invoice->status, ['issued', 'partially_paid', 'paid', 'credited'], true) || ! $invoice->invoice_number) {
            throw ValidationException::withMessages(['invoice' => 'Issue the Invoice before emailing it.']);
        }

        try {
            $this->mailer->to($recipient)->send(new InvoiceMail($invoice, $this->pdf->render($invoice)));
            $delivery = $this->record($invoice, $recipient, 'sent', null, $actor);
            $this->audit->record('invoice.emailed', $invoice, [], ['recipient' => $recipient, 'delivery_id' => $delivery->getKey()], $actor);

            return $delivery;
        } catch (Throwable $exception) {
            // Retain enough diagnostic classification for operators without storing
            // transport hosts, credentials, message bodies, or provider responses.
            $message = class_basename($exception);
            $delivery = $this->record($invoice, $recipient, 'failed', $message, $actor);
            $this->audit->record('invoice.email_failed', $invoice, [], ['recipient' => $recipient, 'delivery_id' => $delivery->getKey(), 'error' => $message], $actor);
            throw ValidationException::withMessages(['email' => 'Invoice email failed. The attempt was logged; verify mail configuration and retry.']);
        }
    }

    private function record(Invoice $invoice, string $recipient, string $status, ?string $failure, User $actor): InvoiceDelivery
    {
        return InvoiceDelivery::query()->create([
            'invoice_id' => $invoice->getKey(), 'channel' => 'email', 'recipient' => $recipient,
            'status' => $status, 'failure_message' => $failure,
            'attempted_by_user_id' => $actor->getKey(), 'attempted_at' => now(),
        ]);
    }
}
