<?php

namespace App\Http\Controllers\Guests;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\InvitationRequest;
use App\Http\Requests\Guests\RevokeInvitationRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Invitation;
use App\Services\GuestInvitationService;
use App\Services\GuestQrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class GuestInvitationController extends Controller
{
    public function store(InvitationRequest $request, Event $event, Guest $guest, GuestInvitationService $service): RedirectResponse
    {
        $this->guard($event, $guest);
        $service->issue($guest, $request->validated(), $request->user());

        return back()->with('status', 'Invitation and private check-in token issued.');
    }

    public function revoke(RevokeInvitationRequest $request, Event $event, Guest $guest, Invitation $invitation, GuestInvitationService $service): RedirectResponse
    {
        $this->guard($event, $guest, $invitation);
        $service->revoke($invitation, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Invitation revoked. Its prior status history remains available.');
    }

    public function qr(Event $event, Guest $guest, Invitation $invitation, GuestInvitationService $invitations, GuestQrCodeService $qr): Response
    {
        $this->guard($event, $guest, $invitation);
        Gate::authorize('viewInvitation', $guest);
        abort_unless($invitation->isUsable(), 404);

        return response($qr->svg($invitations->checkInUrl($invitation)), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="guest-check-in-'.$invitation->getKey().'.svg"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function guard(Event $event, Guest $guest, ?Invitation $invitation = null): void
    {
        abort_unless($guest->event_id === $event->getKey(), 404);
        if ($invitation) {
            abort_unless($invitation->event_id === $event->getKey() && $invitation->guest_id === $guest->getKey(), 404);
        }
    }
}
