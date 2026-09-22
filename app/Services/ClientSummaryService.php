<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ClientSummaryService
{
    /** @return array<string, array{label: string, implemented: bool, authorized: bool, count: int}> */
    public function for(Client $client, ?User $user = null): array
    {
        $definitions = [
            'events' => ['label' => 'Events', 'table' => 'events'],
            'bookings' => ['label' => 'Bookings', 'table' => 'bookings'],
            'invoices' => ['label' => 'Invoices', 'table' => 'invoices'],
            'payments' => ['label' => 'Payments', 'table' => 'payments'],
            'communications' => ['label' => 'Communications', 'table' => 'outbound_messages'],
        ];
        $summary = [];

        foreach ($definitions as $key => $definition) {
            $implemented = Schema::hasTable($definition['table']) && Schema::hasColumn($definition['table'], 'client_id');
            $authorized = match ($key) {
                'payments' => (bool) $user?->hasPermission('payments.view'),
                'invoices' => (bool) $user?->hasPermission('invoices.view'),
                'communications' => (bool) $user?->hasPermission('communications.view'),
                default => true,
            };
            $countQuery = $implemented && $authorized ? DB::table($definition['table'])->where($definition['table'].'.client_id', $client->getKey()) : null;
            if ($key === 'payments' && $countQuery) {
                $countQuery->join('event_module_settings', function ($join) {
                    $join->on('event_module_settings.event_id', '=', 'payments.event_id')
                        ->where('event_module_settings.module_key', 'payments')
                        ->where('event_module_settings.is_enabled', true);
                });
            }
            if ($key === 'invoices' && $countQuery) {
                $countQuery->join('event_module_settings', function ($join) {
                    $join->on('event_module_settings.event_id', '=', 'invoices.event_id')
                        ->where('event_module_settings.module_key', 'invoices')
                        ->where('event_module_settings.is_enabled', true);
                });
            }
            if ($key === 'communications' && $countQuery) {
                $countQuery->join('event_module_settings', function ($join) {
                    $join->on('event_module_settings.event_id', '=', 'outbound_messages.event_id')
                        ->where('event_module_settings.module_key', 'communications')
                        ->where('event_module_settings.is_enabled', true);
                });
            }
            $summary[$key] = [
                'label' => $definition['label'],
                'implemented' => $implemented,
                'authorized' => $authorized,
                'count' => $countQuery?->count() ?? 0,
            ];
        }

        $summary['documents'] = [
            'label' => 'Documents',
            'implemented' => Schema::hasTable('document_links'),
            'authorized' => true,
            'count' => $client->documentLinks()->count(),
        ];

        return $summary;
    }
}
