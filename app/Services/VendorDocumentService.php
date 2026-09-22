<?php

namespace App\Services;

use App\Models\StatusHistory;
use App\Models\User;
use App\Models\VendorAssignment;
use App\Models\VendorContract;
use App\Models\VendorInvoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class VendorDocumentService
{
    public function __construct(private readonly DocumentService $documents, private readonly AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function addContract(VendorAssignment $assignment, array $data, UploadedFile $file, User $actor): VendorContract
    {
        return DB::transaction(function () use ($assignment, $data, $file, $actor) {
            $document = $this->documents->create([
                'title' => $data['title'], 'description' => $data['notes'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null, 'version_notes' => 'Initial vendor contract upload',
                'branch_id' => $assignment->event->branch_id,
            ], $file, [$assignment->event, $assignment->vendor, $assignment], $actor);
            $contract = $assignment->contracts()->create([
                'document_id' => $document->getKey(), 'title' => $data['title'],
                'contract_reference' => $data['contract_reference'] ?? null, 'status' => 'active',
                'effective_date' => $data['effective_date'] ?? null, 'expiry_date' => $data['expiry_date'] ?? null,
                'notes' => $data['notes'] ?? null, 'created_by_user_id' => $actor->getKey(),
            ]);
            $this->recordInitialStatus($contract, $actor, 'Vendor contract uploaded');
            $this->audit->record('vendor.contract.created', $contract, [], [
                'assignment_id' => $assignment->getKey(), 'document_id' => $document->getKey(),
                'contract_reference' => $contract->contract_reference,
            ], $actor);

            return $contract->load('document');
        });
    }

    /** @param array<string, mixed> $data */
    public function addInvoice(VendorAssignment $assignment, array $data, UploadedFile $file, User $actor): VendorInvoice
    {
        return DB::transaction(function () use ($assignment, $data, $file, $actor) {
            $document = $this->documents->create([
                'title' => $data['title'], 'description' => $data['notes'] ?? null,
                'version_notes' => 'Initial vendor invoice upload', 'branch_id' => $assignment->event->branch_id,
            ], $file, [$assignment->event, $assignment->vendor, $assignment], $actor);
            $invoice = $assignment->invoices()->create([
                'vendor_id' => $assignment->vendor_id, 'document_id' => $document->getKey(),
                'invoice_number' => $data['invoice_number'] ?? null, 'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null, 'amount' => $data['amount'],
                'currency_code' => mb_strtoupper($data['currency_code']), 'status' => 'received',
                'notes' => $data['notes'] ?? null, 'uploaded_by_user_id' => $actor->getKey(),
            ]);
            $this->recordInitialStatus($invoice, $actor, 'Vendor invoice uploaded');
            $this->audit->record('vendor.invoice.received', $invoice, [], [
                'assignment_id' => $assignment->getKey(), 'document_id' => $document->getKey(),
                'invoice_number' => $invoice->invoice_number, 'amount' => $invoice->amount,
                'currency_code' => $invoice->currency_code,
            ], $actor);

            return $invoice->load('document');
        });
    }

    private function recordInitialStatus(object $subject, User $actor, string $reason): void
    {
        StatusHistory::query()->create([
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'from_status' => $subject->status, 'to_status' => $subject->status, 'actor_type' => 'user',
            'actor_user_id' => $actor->getKey(), 'reason' => $reason, 'changed_at' => now(),
        ]);
    }
}
