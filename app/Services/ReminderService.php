<?php

namespace App\Services;

use App\Models\Event;
use App\Models\PaymentSchedule;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Models\VendorContract;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ReminderService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function scheduleForUser(Event $event, CarbonInterface $dueAt, User $recipient, User $actor): ReminderSchedule
    {
        return ReminderSchedule::query()->create([
            'reminder_type' => 'event_reminder', 'event_id' => $event->getKey(),
            'target_user_id' => $recipient->getKey(), 'channel' => 'in_app', 'status' => 'scheduled',
            'due_at' => $dueAt->utc(), 'idempotency_key' => 'manual-event:'.Str::uuid(),
            'created_by_user_id' => $actor->getKey(),
        ]);
    }

    /** @return array{scheduled:int,events:int,payments:int,failed:int} */
    public function dispatchDue(): array
    {
        $counts = ['scheduled' => 0, 'events' => 0, 'payments' => 0, 'failed' => 0];

        ReminderSchedule::query()->where('status', 'scheduled')->where('due_at', '<=', now())
            ->orderBy('id')->chunkById(100, function ($schedules) use (&$counts) {
                foreach ($schedules as $schedule) {
                    try {
                        DB::transaction(function () use ($schedule) {
                            $locked = ReminderSchedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
                            if ($locked->status !== 'scheduled') {
                                return;
                            }
                            $data = $this->notificationDataForSchedule($locked);
                            if ($locked->target_user_id) {
                                $this->notifications->sendToUsers([$locked->targetUser], $data, $locked->createdBy ?? null);
                            } elseif ($locked->target_role_id) {
                                $this->notifications->sendToRoles([$locked->targetRole->slug], $data, $locked->createdBy ?? null);
                            }
                            $locked->update(['status' => 'sent', 'processed_at' => now(), 'last_attempt_at' => now(), 'last_error_code' => null]);
                        });
                        $counts['scheduled']++;
                    } catch (Throwable) {
                        $schedule->update(['status' => 'failed', 'last_attempt_at' => now(), 'last_error_code' => 'processing_failed']);
                        $counts['failed']++;
                    }
                }
            });

        Event::query()->whereNull('archived_at')->whereNotIn('status', ['completed', 'cancelled'])
            ->whereBetween('starts_at', [now(), now()->addDay()])->with('manager')->chunkById(100, function ($events) use (&$counts) {
                foreach ($events as $event) {
                    $data = [
                        'type' => 'event_reminder', 'title' => 'Upcoming event: '.$event->name,
                        'body' => 'The event starts within 24 hours.', 'source' => $event, 'event_id' => $event->getKey(),
                        'route_name' => 'events.show', 'route_parameters' => ['event' => $event->getKey()],
                        'idempotency_key' => 'event-reminder:'.$event->getKey().':'.$event->starts_at->utc()->format('YmdHi'),
                    ];
                    $event->manager && $event->manager->is_active
                        ? $this->notifications->sendToUsers([$event->manager], $data)
                        : $this->notifications->sendToRoles(['administrator', 'event-manager'], $data);
                    $counts['events']++;
                }
            });

        PaymentSchedule::query()->whereIn('status', ['scheduled', 'partially_paid'])->whereDate('due_date', '<=', today())
            ->with('event')->chunkById(100, function ($schedules) use (&$counts) {
                foreach ($schedules as $schedule) {
                    if (! $schedule->event) {
                        continue;
                    }
                    $this->notifications->sendToRoles(['administrator', 'finance-accounts'], [
                        'type' => 'payment_due', 'title' => 'Payment due: '.$schedule->label,
                        'body' => 'A payment schedule is due for Event '.$schedule->event->reference_number.'.',
                        'source' => $schedule, 'event_id' => $schedule->event_id,
                        'route_name' => 'events.payments.index', 'route_parameters' => ['event' => $schedule->event_id],
                        'idempotency_key' => 'payment-due:'.$schedule->getKey().':'.$schedule->due_date->format('Ymd'),
                    ]);
                    $counts['payments']++;
                }
            });

        return $counts;
    }

    public function checkContractExpiries(int $days = 30): int
    {
        $count = 0;
        VendorContract::query()->where('status', 'active')->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [today(), today()->addDays($days)])
            ->with('assignment.event')->chunkById(100, function ($contracts) use (&$count) {
                foreach ($contracts as $contract) {
                    $assignment = $contract->assignment;
                    if (! $assignment?->event) {
                        continue;
                    }
                    $this->notifications->sendToRoles(['administrator', 'event-manager'], [
                        'type' => 'contract_expiry', 'title' => 'Vendor contract expiry approaching',
                        'body' => 'A vendor contract for Event '.$assignment->event->reference_number.' expires on '.$contract->expiry_date->toDateString().'.',
                        'source' => $contract, 'event_id' => $assignment->event_id,
                        'route_name' => 'events.vendors.show',
                        'route_parameters' => ['event' => $assignment->event_id, 'assignment' => $assignment->getKey()],
                        'idempotency_key' => 'contract-expiry:'.$contract->getKey().':'.$contract->expiry_date->format('Ymd'),
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    private function notificationDataForSchedule(ReminderSchedule $schedule): array
    {
        $event = $schedule->event;

        return [
            'type' => $schedule->reminder_type,
            'title' => $event ? 'Reminder: '.$event->name : 'Scheduled reminder',
            'body' => $event ? 'A scheduled Event reminder is due.' : 'A scheduled reminder is due.',
            'source' => $schedule, 'event_id' => $event?->getKey(),
            'route_name' => $event ? 'events.show' : 'notifications.index',
            'route_parameters' => $event ? ['event' => $event->getKey()] : [],
            'idempotency_key' => 'reminder-schedule:'.$schedule->idempotency_key,
        ];
    }
}
