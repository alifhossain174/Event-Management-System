<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invoice $invoice, private readonly string $pdfBytes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->invoice->document_type === 'credit_note' ? 'Credit note ' : 'Invoice ').$this->invoice->invoice_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdfBytes, $this->invoice->invoice_number.'.pdf')->withMime('application/pdf')];
    }
}
