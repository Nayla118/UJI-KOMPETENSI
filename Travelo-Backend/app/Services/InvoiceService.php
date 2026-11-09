<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Carbon\CarbonInterface;

/**
 * Builds the traveller-facing invoice for a paid booking.
 *
 * The invoice number is derived from the booking, never generated randomly, so
 * repeated requests (JSON payload, download, later re-download) always return
 * the same number: INV-YYYYMM-000123.
 */
class InvoiceService
{
    public const PREFIX = 'INV';

    /**
     * Only a settled payment has an invoice. The paid_at timestamp is part of
     * the invoice date and the number's month, so it must exist too.
     */
    public function isInvoiceable(Booking $booking): bool
    {
        return $booking->payment_status === Payment::STATUS_PAID
            && $booking->payment !== null
            && $booking->payment->paid_at !== null;
    }

    /**
     * Deterministic invoice number, e.g. INV-202611-000123.
     */
    public function invoiceNumber(Booking $booking): string
    {
        return sprintf(
            '%s-%s-%06d',
            self::PREFIX,
            $this->paidAt($booking)->format('Ym'),
            $booking->getKey()
        );
    }

    /**
     * Invoice data for the JSON endpoint and the print-ready HTML document.
     * Visitor identity numbers are intentionally never included.
     *
     * @return array<string, mixed>
     */
    public function forBooking(Booking $booking): array
    {
        $payment = $booking->payment;
        $paidAt = $this->paidAt($booking);
        $tourPackage = $booking->tourPackage;
        $destination = $tourPackage?->destination;

        return [
            'invoice_number' => $this->invoiceNumber($booking),
            'issued_at' => $paidAt->toIso8601ZuluString(),
            'paid_at' => $paidAt->toIso8601ZuluString(),
            'booking_id' => (int) $booking->id,
            'customer_name' => $booking->user?->name,
            'tour_package' => [
                'id' => $tourPackage?->id,
                'title' => $tourPackage?->title,
                'destination' => $destination !== null
                    ? [
                        'name' => $destination->name,
                        'city' => $destination->city,
                        'country' => $destination->country,
                    ]
                    : null,
            ],
            'visit_date' => $booking->booking_date?->toDateString(),
            'people_count' => (int) $booking->people_count,
            'currency' => 'IDR',
            // Legacy bookings never stored a subtotal; effectiveSubtotal()
            // derives it from total + discount.
            'subtotal_amount' => $booking->effectiveSubtotal(),
            'discount_amount' => (float) $booking->discount_amount,
            'promo_code' => $booking->promo?->code,
            'total_amount' => (float) $booking->total_price,
            'payment_method' => $payment?->display_method,
            'payment_type' => $payment?->payment_type,
            'payment_status' => $booking->payment_status,
            'status' => 'paid',
        ];
    }

    /**
     * Print-ready HTML. The backend ships no PDF library, so the document is a
     * self-contained HTML page with @media print rules that browsers and
     * Android viewers can print/save as PDF.
     */
    public function renderHtml(Booking $booking): string
    {
        return view('invoices.booking', [
            'invoice' => $this->forBooking($booking),
        ])->render();
    }

    public function downloadFileName(Booking $booking): string
    {
        return 'invoice-'.$this->invoiceNumber($booking).'.html';
    }

    private function paidAt(Booking $booking): CarbonInterface
    {
        // isInvoiceable() guarantees the payment timestamp; the fallbacks keep
        // the document printable for legacy rows instead of crashing.
        return $booking->payment?->paid_at
            ?? $booking->updated_at
            ?? now();
    }
}
