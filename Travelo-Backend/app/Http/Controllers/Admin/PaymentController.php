<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Display payments list
     */
    public function index(): View
    {
        $payments = Payment::with(['booking.user', 'booking.tourPackage'])
            ->latest()
            ->paginate(10);
        return view('admin.payments.index', compact('payments'));
    }

    /**
     * Show payment details
     */
    public function show(Payment $payment): View
    {
        $payment->load(['booking.user', 'booking.tourPackage']);
        return view('admin.payments.show', compact('payment'));
    }
}
