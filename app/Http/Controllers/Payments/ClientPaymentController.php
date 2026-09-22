<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Refund;
use App\Services\PaymentService;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ClientPaymentController extends Controller
{
    public function __invoke(Request $request, Client $client, PaymentService $service, SettingsService $settings): View
    {
        Gate::authorize('view', $client);
        abort_unless($request->user()->hasPermission('payments.view'), 403);
        $enabledEvents = fn (Builder $query) => $query->whereHas('moduleSettings', fn (Builder $settings) => $settings
            ->where('module_key', 'payments')->where('is_enabled', true));

        return view('clients.payments', [
            'client' => $client,
            'payments' => Payment::query()->where('client_id', $client->getKey())->whereHas('event', $enabledEvents)
                ->with(['event', 'refunds'])->orderByDesc('received_at')->paginate(20, ['*'], 'payments_page')->withQueryString(),
            'schedules' => PaymentSchedule::query()->where('client_id', $client->getKey())->whereHas('event', $enabledEvents)
                ->orderBy('due_date')->paginate(20, ['*'], 'schedules_page')->withQueryString(),
            'refunds' => Refund::query()->where('client_id', $client->getKey())->whereHas('event', $enabledEvents)
                ->with('payment')->orderByDesc('refunded_at')->paginate(20, ['*'], 'refunds_page')->withQueryString(),
            'summary' => $service->clientSummary($client),
            'organizationTimezone' => $settings->string('general.timezone'),
        ]);
    }
}
