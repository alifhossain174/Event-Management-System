<?php

namespace App\Search;

use App\Contracts\GlobalSearchProvider;
use App\Data\GlobalSearchResult;
use App\Models\Invoice;
use App\Models\User;
use App\Services\EventQueryService;
use Illuminate\Support\Collection;

final class InvoiceSearchProvider implements GlobalSearchProvider
{
    public function __construct(private readonly EventQueryService $events) {}

    public function key(): string
    {
        return 'invoices';
    }

    public function label(): string
    {
        return 'Invoices';
    }

    public function canSearch(User $user): bool
    {
        return $user->hasPermission('invoices.view');
    }

    public function search(User $user, string $term, int $limit): Collection
    {
        return Invoice::query()->with(['event:id,name', 'client:id,display_name'])
            ->whereIn('event_id', $this->events->visibleTo($user)->select('events.id'))
            ->whereHas('event.moduleSettings', fn ($settings) => $settings->where('module_key', 'invoices')->where('is_enabled', true))
            ->where(fn ($query) => $query->where('invoice_number', 'like', "{$term}%")
                ->orWhere('subject', 'like', "{$term}%")
                ->orWhere('client_name', 'like', "{$term}%"))
            ->orderByDesc('created_at')->limit($limit)->get()
            ->map(fn (Invoice $invoice) => new GlobalSearchResult(
                $invoice->invoice_number ?? 'Draft Invoice #'.$invoice->getKey(),
                route('events.invoices.show', [$invoice->event_id, $invoice]),
                ($invoice->client?->display_name ?? $invoice->client_name).' · '.$invoice->currency_code.' '.$invoice->total,
                str($invoice->displayStatus())->headline()->toString(),
            ));
    }
}
