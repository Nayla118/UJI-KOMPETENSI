<?php

namespace App\Services;

use App\Jobs\ProcessAutoRefund;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Traveller-initiated cancellation with admin approval.
 *
 * A request never mutates the booking. Only an admin approval cancels the
 * booking, and every transition is written to activity_logs.
 *
 * Refund policy is applied at approval time: the snapshot (percentage +
 * amount) is stored on the change request. When TRAVELO_AUTO_REFUND=true and
 * the amount is greater than zero, the refund is queued for automatic
 * processing via Midtrans; otherwise it stays manual and an admin marks it
 * refunded once the money has actually been returned.
 */
class BookingChangeService
{
    public function __construct(
        protected RefundPolicyService $refundPolicy,
    ) {}

    /**
     * @return array{ok: bool, message: string, code?: string, request?: BookingChangeRequest}
     */
    public function request(Booking $booking, User $user, ?string $reason = null): array
    {
        if ((int) $booking->user_id !== (int) $user->id) {
            return $this->fail('You can only cancel your own booking.', 'forbidden');
        }

        if (in_array($booking->booking_status, ['cancelled', 'completed'], true)) {
            return $this->fail('This booking can no longer be changed.', 'not_changeable');
        }

        if ($booking->payment_status !== 'paid' || $booking->booking_status !== 'confirmed') {
            return $this->fail(
                'Cancellation is available after your payment is confirmed.',
                'not_paid'
            );
        }

        // Report a past visit before the derived ticket status, because an
        // elapsed visit date also makes the ticket "expired".
        $today = CarbonImmutable::now((string) config('travelo.ticket.timezone', config('app.timezone')))
            ->startOfDay();

        if ($booking->booking_date !== null && $booking->booking_date->lt($today)) {
            return $this->fail('This visit date has already passed.', 'past_visit');
        }

        if ($booking->ticket_usage_status !== Booking::TICKET_UNUSED) {
            return $this->fail('A used or expired ticket cannot be cancelled.', 'ticket_finished');
        }

        if ($booking->changeRequests()
            ->where('status', BookingChangeRequest::STATUS_PENDING)
            ->exists()) {
            return $this->fail('You already have a pending request for this booking.', 'pending_exists');
        }

        $request = BookingChangeRequest::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'type' => BookingChangeRequest::TYPE_CANCELLATION,
            'status' => BookingChangeRequest::STATUS_PENDING,
            'original_booking_date' => $booking->booking_date?->toDateString(),
            'reason' => $this->cleanText($reason),
        ]);

        ActivityLog::log(
            'Booking cancellation requested',
            Booking::class,
            $booking->id,
            User::class,
            $user->id,
            [
                'change_request_id' => $request->id,
                'type' => $request->type,
                'original_booking_date' => $request->original_booking_date?->toDateString(),
            ]
        );

        return [
            'ok' => true,
            'message' => 'Cancellation request submitted. Our team will review it shortly.',
            'request' => $request->fresh(),
        ];
    }

    /**
     * @return array{ok: bool, message: string, code?: string, request?: BookingChangeRequest}
     */
    public function approve(BookingChangeRequest $change, User $admin, ?string $adminNote = null): array
    {
        $result = DB::transaction(function () use ($change, $admin, $adminNote) {
            $locked = BookingChangeRequest::query()
                ->whereKey($change->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->isPending()) {
                return $this->fail('This request has already been reviewed.', 'already_reviewed');
            }

            $booking = Booking::query()
                ->whereKey($locked->booking_id)
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                return $this->fail('The booking no longer exists.', 'missing_booking');
            }

            if ($booking->ticket_usage_status !== Booking::TICKET_UNUSED
                || in_array($booking->booking_status, ['cancelled', 'completed'], true)) {
                return $this->fail('This booking can no longer be changed.', 'not_changeable');
            }

            $booking->update([
                'booking_status' => 'cancelled',
            ]);

            // Snapshot the refund policy at approval time. The snapshot is the
            // source of truth for how much should be refunded, so a later
            // policy change cannot retroactively alter an approved refund.
            $calculation = $this->refundPolicy->calculate($booking);
            $locked->refund_percentage = $calculation['percentage'];
            $locked->refund_amount = $calculation['refund_amount'];

            $autoRefund = (bool) config('travelo.refund.auto_refund', false);

            if (! $calculation['eligible'] || $calculation['percentage'] === 0) {
                $locked->refund_status = BookingChangeRequest::REFUND_NOT_APPLICABLE;
            } elseif ($autoRefund) {
                $locked->refund_status = BookingChangeRequest::REFUND_PROCESSING;
            } else {
                $locked->refund_status = BookingChangeRequest::REFUND_PENDING;
            }

            ActivityLog::log(
                'Booking cancelled by approved request',
                Booking::class,
                $booking->id,
                User::class,
                $admin->id,
                [
                    'change_request_id' => $locked->id,
                    'refund_status' => $locked->refund_status,
                    'refund_percentage' => $locked->refund_percentage,
                    'refund_amount' => $locked->refund_amount,
                    'auto_refund' => $autoRefund,
                ]
            );

            $locked->status = BookingChangeRequest::STATUS_APPROVED;
            $locked->reviewed_by = $admin->id;
            $locked->reviewed_at = now();
            $locked->admin_note = $this->cleanText($adminNote);
            $locked->save();

            $shouldAutoRefund = $autoRefund
                && $calculation['eligible']
                && $calculation['refund_amount'] > 0
                && $locked->refund_status === BookingChangeRequest::REFUND_PROCESSING;

            return [
                'ok' => true,
                'message' => 'Cancellation approved. The booking is cancelled and the refund is marked '
                    .($shouldAutoRefund ? 'processing' : 'pending').'.',
                'request' => $locked->fresh(['booking.tourPackage', 'user']),
                'auto_refund' => $shouldAutoRefund,
            ];
        });

        if ($result['ok'] && isset($result['request'])) {
            app(PushNotificationService::class)->notifyChangeRequest(
                $result['request'],
                BookingChangeRequest::STATUS_APPROVED
            );

            if (! empty($result['auto_refund'])) {
                ProcessAutoRefund::dispatch($result['request'])->onQueue('refunds');
            }
        }

        return $result;
    }

    /**
     * @return array{ok: bool, message: string, code?: string, request?: BookingChangeRequest}
     */
    public function reject(BookingChangeRequest $change, User $admin, string $adminNote): array
    {
        $result = DB::transaction(function () use ($change, $admin, $adminNote) {
            $locked = BookingChangeRequest::query()
                ->whereKey($change->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->isPending()) {
                return $this->fail('This request has already been reviewed.', 'already_reviewed');
            }

            $locked->status = BookingChangeRequest::STATUS_REJECTED;
            $locked->reviewed_by = $admin->id;
            $locked->reviewed_at = now();
            $locked->admin_note = $this->cleanText($adminNote);
            $locked->save();

            ActivityLog::log(
                'Booking cancellation rejected',
                Booking::class,
                $locked->booking_id,
                User::class,
                $admin->id,
                [
                    'change_request_id' => $locked->id,
                    'type' => $locked->type,
                    'note' => $locked->admin_note,
                ]
            );

            return [
                'ok' => true,
                'message' => 'Request rejected. The traveller has been notified.',
                'request' => $locked->fresh(['booking.tourPackage', 'user']),
            ];
        });

        if ($result['ok'] && isset($result['request'])) {
            app(PushNotificationService::class)->notifyChangeRequest(
                $result['request'],
                BookingChangeRequest::STATUS_REJECTED
            );
        }

        return $result;
    }

    /**
     * @return array{ok: bool, message: string, code?: string, request?: BookingChangeRequest}
     */
    public function markRefunded(BookingChangeRequest $change, User $admin, ?string $note = null): array
    {
        if ($change->type !== BookingChangeRequest::TYPE_CANCELLATION
            || $change->status !== BookingChangeRequest::STATUS_APPROVED) {
            return $this->fail('Only an approved cancellation can be marked refunded.', 'not_refundable');
        }

        if ($change->refund_status === BookingChangeRequest::REFUND_REFUNDED) {
            return $this->fail('This refund is already marked as refunded.', 'already_refunded');
        }

        $change->forceFill([
            'refund_status' => BookingChangeRequest::REFUND_REFUNDED,
            'refunded_at' => now(),
            'refund_note' => $this->cleanText($note) ?? $change->refund_note,
        ])->save();

        ActivityLog::log(
            'Booking refund marked as refunded',
            Booking::class,
            $change->booking_id,
            User::class,
            $admin->id,
            [
                'change_request_id' => $change->id,
                'refund_note' => $change->refund_note,
            ]
        );

        return [
            'ok' => true,
            'message' => 'Refund marked as refunded.',
            'request' => $change->fresh(),
        ];
    }

    private function cleanText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{ok: false, message: string, code: string}
     */
    private function fail(string $message, string $code): array
    {
        return ['ok' => false, 'message' => $message, 'code' => $code];
    }
}
