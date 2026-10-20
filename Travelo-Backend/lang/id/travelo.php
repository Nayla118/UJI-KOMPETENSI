<?php

return [
    'otp' => [
        'subject' => ':app — kode verifikasi login kamu',
        'title' => 'Kode verifikasi login',
        'preheader' => 'Gunakan kode satu kali ini untuk masuk ke Travelo.',
        'subtitle' => 'Masukkan kode di bawah ini untuk menyelesaikan proses login.',
        'greeting' => 'Hai, kamu mencoba masuk ke Travelo. Gunakan kode berikut (berlaku :minutes menit):',
        'security' => 'Jangan bagikan kode ini kepada siapa pun. Travelo tidak pernah meminta kode verifikasi lewat telepon atau pesan.',
        'ignore' => 'Tidak sedang login? Abaikan email ini — tidak ada yang bisa masuk tanpa kode ini.',
        'footer' => '© :year Travelo · Email otomatis, mohon jangan dibalas',
        'text' => [
            'subject' => ':app — kode verifikasi login kamu',
            'intro' => 'Kode verifikasi kamu:',
            'expiry' => 'Kode ini berlaku :minutes menit dan hanya bisa dipakai satu kali.',
            'security' => 'Jangan bagikan kode ini kepada siapa pun. Travelo tidak pernah meminta kode verifikasi lewat telepon atau pesan.',
            'ignore' => 'Tidak sedang login? Abaikan email ini — tidak ada yang bisa masuk tanpa kode ini.',
            'footer' => 'Email otomatis dari Travelo, mohon jangan dibalas.',
        ],
        'api' => [
            'missing_parameters' => 'Parameter tidak lengkap.',
            'id_token_required' => 'id_token wajib diisi.',
            'code_format' => 'Kode harus 6 digit angka.',
            'no_email' => 'Akun Firebase kamu tidak memiliki email.',
            'invalid_session' => 'Sesi tidak valid. Silakan login ulang.',
            'verification_unavailable' => 'Verifikasi sedang tidak tersedia. Coba lagi.',
            'code_invalid' => 'Kode tidak berlaku atau sudah kedaluwarsa. Minta kode baru.',
            'too_many_attempts' => 'Terlalu banyak percobaan salah. Kode dibatalkan, minta kode baru.',
            'wrong_code' => 'Kode salah. Sisa percobaan: :attempts.',
            'verified' => 'Verifikasi berhasil',
            'sent' => 'Kode verifikasi sudah dikirim ke email kamu.',
            'resend_wait' => 'Tunggu :seconds detik sebelum meminta kode baru.',
            'mail_failed' => 'Gagal mengirim email verifikasi. Periksa konfigurasi email backend lalu coba lagi.',
        ],
    ],

    'callback' => [
        'success' => [
            'title' => 'Pembayaran berhasil',
            'body' => 'Terima kasih. Pembayaran kamu sudah diterima dan booking akan dikonfirmasi otomatis.',
        ],
        'pending' => [
            'title' => 'Menunggu pembayaran',
            'body' => 'Transaksi belum selesai. Selesaikan pembayaran sesuai instruksi metode yang kamu pilih, lalu kembali ke aplikasi Travelo untuk memperbarui status.',
        ],
        'error' => [
            'title' => 'Pembayaran belum berhasil',
            'body' => 'Transaksi tidak dapat diproses. Kembali ke aplikasi Travelo untuk mencoba lagi.',
        ],
        'booking_id' => 'Booking ID',
        'open_app' => 'Buka kembali aplikasi Travelo untuk melihat status terbaru.',
    ],
];
