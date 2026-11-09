<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Traveller invoice for one of the caller's paid bookings.
 *
 * Ownership is enforced by scoping the lookup through the authenticated user's
 * bookings, so another account gets a 404 and never sees an invoice number.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $booking = $this->loadInvoiceableBooking($request, $id);

        return response()->json([
            'success' => true,
            'data' => $this->invoices->forBooking($booking),
        ]);
    }

    public function download(Request $request, int $id): Response
    {
        $booking = $this->loadInvoiceableBooking($request, $id);

        return response($this->invoices->renderHtml($booking), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->invoices->downloadFileName($booking).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function loadInvoiceableBooking(Request $request, int $id): Booking
    {
        $booking = $request->user()
            ->bookings()
            ->with(['tourPackage.destination', 'payment', 'promo', 'user'])
            ->findOrFail($id);

        abort_if(
            ! $this->invoices->isInvoiceable($booking),
            409,
            'An invoice is available only after the payment is confirmed.'
        );

        return $booking;
    }
}
