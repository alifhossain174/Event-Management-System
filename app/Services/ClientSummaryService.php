<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ClientSummaryService
{
    /** @return array<string, array{label: string, implemented: bool, count: int}> */
    public function for(Client $client): array
    {
        $definitions = [
            'events' => ['label' => 'Events', 'table' => 'events'],
            'bookings' => ['label' => 'Bookings', 'table' => 'bookings'],
            'invoices' => ['label' => 'Invoices', 'table' => 'invoices'],
            'payments' => ['label' => 'Payments', 'table' => 'payments'],
            'communications' => ['label' => 'Communications', 'table' => 'communication_logs'],
        ];
        $summary = [];

        foreach ($definitions as $key => $definition) {
            $implemented = Schema::hasTable($definition['table']) && Schema::hasColumn($definition['table'], 'client_id');
            $summary[$key] = [
                'label' => $definition['label'],
                'implemented' => $implemented,
                'count' => $implemented ? DB::table($definition['table'])->where('client_id', $client->getKey())->count() : 0,
            ];
        }

        $summary['documents'] = [
            'label' => 'Documents',
            'implemented' => Schema::hasTable('document_links'),
            'count' => $client->documentLinks()->count(),
        ];

        return $summary;
    }
}
