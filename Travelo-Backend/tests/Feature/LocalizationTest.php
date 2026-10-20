<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_otp_email_uses_the_active_locale(): void
    {
        app()->setLocale('en');

        $html = (new LoginOtpMail('123456', 10))->render();

        $this->assertStringContainsString('Login verification code', $html);
        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('10 minutes', $html);

        app()->setLocale('id');

        $html = (new LoginOtpMail('654321', 5))->render();

        $this->assertStringContainsString('Kode verifikasi login', $html);
        $this->assertStringContainsString('654321', $html);
        $this->assertStringContainsString('5 menit', $html);
    }

    public function test_payment_callback_copy_follows_the_active_locale(): void
    {
        app()->setLocale('en');

        $this->get('/booking/success?booking_id=42')
            ->assertOk()
            ->assertSee('Payment successful')
            ->assertSee('42');

        app()->setLocale('id');

        $this->get('/booking/pending?booking_id=42')
            ->assertOk()
            ->assertSee('Menunggu pembayaran')
            ->assertSee('42');
    }
}
