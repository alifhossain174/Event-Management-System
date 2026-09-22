<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\PromoCodeRequest;
use App\Models\Event;
use App\Models\PromoCode;
use App\Models\Ticket;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PromoCodeController extends Controller
{
    public function store(PromoCodeRequest $request, Event $event, AuditService $audit): RedirectResponse
    {
        $promo = $event->promoCodes()->create($request->validated() + ['created_by_user_id' => $request->user()->getKey(), 'updated_by_user_id' => $request->user()->getKey()]);
        $audit->record('ticket.promo_created', $promo, [], $promo->only(['event_id', 'code', 'discount_type', 'discount_value', 'usage_limit', 'is_active']), $request->user());

        return back()->with('status', 'Promo Code created.');
    }

    public function update(PromoCodeRequest $request, Event $event, PromoCode $promoCode, AuditService $audit): RedirectResponse
    {
        $this->guard($event, $promoCode);
        $before = $promoCode->only(['code', 'name', 'discount_type', 'discount_value', 'valid_from', 'valid_until', 'usage_limit', 'is_active']);
        $promoCode->update($request->validated() + ['updated_by_user_id' => $request->user()->getKey()]);
        $audit->record('ticket.promo_updated', $promoCode, $before, $promoCode->only(array_keys($before)), $request->user());

        return back()->with('status', 'Promo Code updated.');
    }

    public function archive(Request $request, Event $event, PromoCode $promoCode, AuditService $audit): RedirectResponse
    {
        $this->guard($event, $promoCode);
        Gate::authorize('configure', [Ticket::class, $event]);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $promoCode->update(['is_active' => false, 'archived_at' => now(), 'archived_by_user_id' => $request->user()->getKey(), 'archive_reason' => $data['reason'], 'updated_by_user_id' => $request->user()->getKey()]);
        $audit->record('ticket.promo_archived', $promoCode, ['is_active' => true], ['is_active' => false, 'reason' => $data['reason']], $request->user());

        return back()->with('status', 'Promo Code archived; redemption history was preserved.');
    }

    private function guard(Event $event, PromoCode $promo): void
    {
        abort_unless($promo->event_id === $event->getKey(), 404);
    }
}
