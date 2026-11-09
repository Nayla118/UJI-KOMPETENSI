<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingVisitor;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Traveller invoice: paid-only access, ownership, price breakdown, stable
 * invoice numbers, identity-number privacy, HTML download, and the raw
 * Midtrans payment_type captured from the webhook.
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $traveller;

    private User $otherTraveller;

    private User $admin;

    private Destination $destination;

    private TourPackage $tourPackage;

    private Booking $paidBooking;

    private Payment $paidPayment;

    private Booking $unpaidBooking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->traveller = User::create([
            'firebase_uid' => 'uid-traveller-'.uniqid(),
            'name' => 'Nayla Aisyah',
            'email' => 'nayla-'.uniqid().'@example.com',
        ]);

        $this->otherTraveller = User::create([
            'firebase_uid' => 'uid-other-'.uniqid(),
            'name' => 'Other Traveller',
            'email' => 'other-'.uniqid().'@example.com',
        ]);

        $this->admin = User::create([
            'firebase_uid' => 'uid-admin-'.uniqid(),
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->destination = Destination::create([
            'name' => 'Bromo',
            'city' => 'Probolinggo',
            'country' => 'Indonesia',
        ]);

        $this->tourPackage = TourPackage::create([
            'destination_id' => $this->destination->id,
            'title' => 'Bromo Sunrise Tour',
            'price' => 1000000,
            'duration_days' => 2,
            'max_people' => 10,
        ]);

        $this->paidBooking = Booking::create([
            'user_id' => $this->traveller->id,
            'tour_package_id' => $this->tourPackage->id,
            'booking_date' => '2026-11-20',
            'people_count' => 2,
            'subtotal_amount' => 2000000,
            'discount_amount' => 150000,
            'total_price' => 1850000,
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
        ]);

        $this->paidPayment = Payment::create([
            'booking_id' => $this->paidBooking->id,
            'payment_status' => 'paid',
            'payment_method' => 'qris',
            'payment_type' => 'qris',
            'amount' => 1850000,
            'paid_at' => '2026-11-05 10:30:00',
        ]);

        $this->unpaidBooking = Booking::create([
            'user_id' => $this->traveller->id,
            'tour_package_id' => $this->tourPackage->id,
            'booking_date' => '2026-11-25',
            'people_count' => 1,
            'subtotal_amount' => 1000000,
            'discount_amount' => 0,
            'total_price' => 1000000,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);
    }

    public function test_paid_booking_returns_invoice_data(): void
    {
        $expectedNumber = 'INV-202611-'.str_pad((string) $this->paidBooking->id, 6, '0', STR_PAD_LEFT);

        $response = $this->actingAs($this->traveller, 'sanctum')
            ->getJson('/api/bookings/'.$this->paidBooking->id.'/invoice')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice_number', $expectedNumber)
            ->assertJsonPath('data.booking_id', $this->paidBooking->id)
            ->assertJsonPath('data.customer_name', 'Nayla Aisyah')
            ->assertJsonPath('data.tour_package.title', 'Bromo Sunrise Tour')
            ->assertJsonPath('data.tour_package.destination.name', 'Bromo')
            ->assertJsonPath('data.visit_date', '2026-11-20')
            ->assertJsonPath('data.people_count', 2)
            ->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.payment_method', 'QRIS')
            ->assertJsonPath('data.payment_type', 'qris')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.status', 'paid');

        $data = $response->json('data');
        $this->assertEquals(2000000, $data['subtotal_amount']);
        $this->assertEquals(150000, $data['discount_amount']);
        $this->assertEquals(1850000, $data['total_amount']);
    }

    public function test_unpaid_booking_has_no_invoice(): void
    {
        $this->actingAs($this->traveller, 'sanctum')
            ->getJson('/api/bookings/'.$this->unpaidBooking->id.'/invoice')
            ->assertStatus(409)
            ->assertJsonPath('message', 'An invoice is available only after the payment is confirmed.');

        $this->actingAs($this->traveller, 'sanctum')
            ->get('/api/bookings/'.$this->unpaidBooking->id.'/invoice/download')
            ->assertStatus(409);
    }

    public function test_paid_booking_without_payment_timestamp_has_no_invoice(): void
    {
        // paid flag without a paid_at timestamp cannot produce a valid invoice
        // date/number, so it is rejected instead of inventing one.
        $this->paidPayment->update(['paid_at' => null]);

        $this->actingAs($this->traveller, 'sanctum')
            ->getJson('/api/bookings/'.$this->paidBooking->id.'/invoice')
            ->assertStatus(409);
    }

    public function test_invoice_is_owner_scoped(): void
    {
        $this->actingAs($this->otherTraveller, 'sanctum')
            ->getJson('/api/bookings/'.$this->paidBooking->id.'/invoice')
            ->assertNotFound();

        $this->actingAs($this->otherTraveller, 'sanctum')
            ->get('/api/bookings/'.$this->paidBooking->id.'/invoice/download')
            ->assertNotFound();
    }

    public function test_invoice_discount_breakdown_is_server_derived(): void
    {
        // total_price is authoritative; subtotal + discount must reconcile.
        $data = $this->actingAs($this->traveller, 'sanctum')
            ->getJson('/api/bookings/'.$this->paidBooking->id.'/invoice')
            ->assertOk()
            ->json('data');

        $this->assertEquals(
            (float) $data['total_amount'],
            (float) $data['subtotal_amount'] - (float) $data['discount_amount']
        );

        $this->paidBooking->update([
            'subtotal_amount' => null,
            'discount_amount' => 100000,
            'total_price' => 900000,
        ]);

        $legacy = $this->actingAs($this->traveller, 'sanctum')
            ->getJson('/api/bookings/'.$this->paidBooking->id.'/invoice')
            ->assertOk()
            ->json('data');

        // Legacy rows without a subtotal derive it from total + discount.
        $this->assertEquals(1000000, $legacy['subtotal_amount']);
        $this->assertEquals(100000, $legacy['discount_amount']);
        $this->assertEquals(900000, $legacy['total_amount']);
    }

    public function test_invoice_number_is_stable_and_has_the_expected_format(): void
    {
        $first = $this->actingAs($this->traveller, 'sanctum')
            ->getJson('/api/bookings/'.$this->paidBooking->id.'/invoice')
            ->json('data.invoice_number');

        $second = $this->actingAs($this->traveller, 'sanctum')
            ->getJson('/api/bookings/'.$this->paidBooking->id.'/invoice')
            ->json('data.invoice_number');

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', $first);
        $this->assertSame(app(InvoiceService::class)->invoiceNumber($this->paidBooking), $first);

        // The download uses the same deterministic number in its filename.
        $this->actingAs($this->traveller, 'sanctum')
            ->get('/api/bookings/'.$this->paidBooking->id.'/invoice/download')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="invoice-'.$first.'.html"');
    }

    public function test_download_returns_print_ready_html_without_identity_numbers(): void
    {
        BookingVisitor::create([
            'booking_id' => $this->paidBooking->id,
            'is_booker' => true,
            'name' => 'Nayla Aisyah',
            'identity_type' => BookingVisitor::TYPE_KTP,
            'identity_number' => '3271234567890001',
            'document_status' => BookingVisitor::DOC_MISSING,
        ]);

        $response = $this->actingAs($this->traveller, 'sanctum')
            ->get('/api/bookings/'.$this->paidBooking->id.'/invoice/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $html = $response->getContent();

        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString('INV-202611-', $html);
        $this->assertStringContainsString('Bromo Sunrise Tour', $html);
        $this->assertStringContainsString('Rp 2.000.000', $html);
        $this->assertStringContainsString('Rp 1.850.000', $html);
        $this->assertStringContainsString('QRIS', $html);

        // Privacy: identity numbers never appear on an invoice.
        $this->assertStringNotContainsString('3271234567890001', $html);
        $this->assertStringNotContainsString('identity_number', $html);
    }

    public function test_paid_booking_json_never_includes_identity_numbers(): void
    {
        BookingVisitor::create([
            'booking_id' => $this->paidBooking->id,
            'is_booker' => true,
            'name' => 'Nayla Aisyah',
            'identity_type' => BookingVisitor::TYPE_KTP,
            'identity_number' => '3271234567890001',
            'document_status' => BookingVisitor::DOC_MISSING,
        ]);

        $response = $this->actingAs($this->traveller, 'sanctum')
            ->getJson('/api/bookings/'.$this->paidBooking->id.'/invoice')
            ->assertOk();

        $this->assertStringNotContainsString('3271234567890001', $response->getContent());
        $this->assertArrayNotHasKey('visitors', $response->json('data'));
    }

    public function test_webhook_stores_the_raw_midtrans_payment_type_and_admin_sees_it(): void
    {
        $booking = Booking::create([
            'user_id' => $this->traveller->id,
            'tour_package_id' => $this->tourPackage->id,
            'booking_date' => '2026-12-01',
            'people_count' => 1,
            'subtotal_amount' => 500000,
            'discount_amount' => 0,
            'total_price' => 500000,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);

        // Drive the same sync path the Midtrans webhook uses.
        $result = (new MidtransService)->syncBookingPaymentStatus($booking, [
            'order_id' => 'BOOKING-'.$booking->id.'-20261201000000-1234',
            'transaction_id' => 'txn-invoice-test',
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'gross_amount' => '500000',
            'status_code' => '200',
            'va_numbers' => [['bank' => 'bca', 'va_number' => '1234567890']],
        ]);

        $this->assertSame('paid', $result['payment_status']);

        $payment = $booking->fresh(['payment'])->payment;
        $this->assertSame('bank_transfer', $payment->payment_type);
        $this->assertSame('bca', $payment->payment_channel);

        $this->actingAs($this->admin)
            ->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertSee('bank_transfer')
            ->assertSee('BCA Virtual Account');

        // The invoice exposes both the raw type and the human label.
        $invoice = $this->actingAs($this->traveller, 'sanctum')
            ->getJson('/api/bookings/'.$booking->id.'/invoice')
            ->assertOk()
            ->json('data');

        $this->assertSame('bank_transfer', $invoice['payment_type']);
        $this->assertSame('BCA Virtual Account', $invoice['payment_method']);
    }

    public function test_invoice_download_is_throttled(): void
    {
        $this->actingAs($this->traveller, 'sanctum');

        for ($i = 0; $i < 10; $i++) {
            $this->get('/api/bookings/'.$this->paidBooking->id.'/invoice/download')
                ->assertOk();
        }

        $this->get('/api/bookings/'.$this->paidBooking->id.'/invoice/download')
            ->assertStatus(429);
    }
}
