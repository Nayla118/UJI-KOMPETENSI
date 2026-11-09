<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Services\MidtransService;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process an approved cancellation refund automatically via Midtrans.
 *
 * Idempotency: every refund attempt uses a stable refund_key derived from the
 * change request id, so Midtrans rejects duplicate refunds even if the job is
 * retried. A gateway failure marks the refund as `failed` with the reason
 * stored, and never changes the booking status or the admin approval decision.
 * Admin can retry via the admin panel, which re-dispatches this job.
 */
class ProcessAutoRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public BookingChangeRequest $changeRequest,
    ) {}

    public function handle(MidtransService $midtrans, PushNotificationService $push): void
    {
        $change = $this->changeRequest->fresh();

        // `processing` is the status BookingChangeService writes before it
        // dispatches this job, so it must be accepted here. `pending` and
        // `failed` cover a manual retry. Anything else (not_applicable /
        // refunded) must never call the gateway again.
        $processable = $change->isRefundable()
            || (
                $change->type === BookingChangeRequest::TYPE_CANCELLATION
                && $change->status === BookingChangeRequest::STATUS_APPROVED
                && $change->refund_status === BookingChangeRequest::REFUND_PROCESSING
            );

        if (! $processable) {
            Log::info('Auto-refund skipped: change request is not refundable', [
                'change_request_id' => $change->id,
                'refund_status' => $change->refund_status,
            ]);

            return;
        }

        $booking = $change->booking()->first();

        if ($booking === null) {
            Log::warning('Auto-refund skipped: booking not found', [
                'change_request_id' => $change->id,
            ]);

            return;
        }

        $payment = $booking->payment;
        $transactionId = $payment?->midtrans_transaction_id;

        if ($transactionId === null || trim($transactionId) === '') {
            $this->markFailed($change, 'No Midtrans transaction id on file');

            return;
        }

        $refundKey = 'travelo_refund_'.$change->id;
        // IDR has no minor unit. Round instead of truncating so a decimal
        // snapshot such as 999999.99 is not sent as 999999.
        $amount = (int) round((float) ($change->refund_amount ?? 0));

        try {
            $result = $midtrans->refundTransaction(
                $transactionId,
                $refundKey,
                $amount,
                'Cancellation refund for booking #'.$booking->id
            );

            if ($result['success']) {
                $change->forceFill([
                    'refund_status' => BookingChangeRequest::REFUND_REFUNDED,
                    'refund_reference' => $result['reference'] ?? $refundKey,
                    'refunded_at' => now(),
                    'refund_failure_reason' => null,
                ])->save();

                Log::info('Auto-refund succeeded', [
                    'change_request_id' => $change->id,
                    'booking_id' => $booking->id,
                    'refund_amount' => $change->refund_amount,
                    'reference' => $change->refund_reference,
                ]);

                $this->notifyRefundResult($change, $booking, true);
            } else {
                $this->markFailed($change, $result['error'] ?? 'Midtrans refund rejected');
            }
        } catch (\Throwable $e) {
            Log::warning('Auto-refund threw an exception', [
                'change_request_id' => $change->id,
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            $this->markFailed($change, $e->getMessage());

            // Re-throw so the queue retries with backoff, up to $tries.
            throw $e;
        }
    }

    /**
     * Admin-initiated retry for a failed refund. Safe to call multiple times:
     * the idempotency key prevents double refunds at the gateway.
     */
    public static function retry(BookingChangeRequest $changeRequest): void
    {
        if (! $changeRequest->isRefundable()) {
            return;
        }

        self::dispatch($changeRequest)->onQueue('refunds');
    }

    private function markFailed(BookingChangeRequest $change, string $reason): void
    {
        $change->forceFill([
            'refund_status' => BookingChangeRequest::REFUND_FAILED,
            'refund_failure_reason' => $reason,
        ])->save();

        Log::warning('Auto-refund failed', [
            'change_request_id' => $change->id,
            'reason' => $reason,
        ]);
    }

    private function notifyRefundResult(
        BookingChangeRequest $change,
        Booking $booking,
        bool $success
    ): void {
        try {
            app(PushNotificationService::class)->notifyRefundResult(
                $booking,
                $change->refund_amount ?? 0,
                $success,
                $change->refund_failure_reason
            );
        } catch (\Throwable $e) {
            Log::warning('Refund result push notification failed', [
                'change_request_id' => $change->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
