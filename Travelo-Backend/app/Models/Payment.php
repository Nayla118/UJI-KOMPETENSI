<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    // === Status lokal (mapping konsisten Midtrans -> lokal) ===
    // pending = menunggu tindakan customer (Midtrans: pending/authorize/capture+challenge)
    // paid    = Midtrans konfirmasi berhasil (settlement / capture+accept)
    // failed  = gagal/ditolak/dibatalkan user (Midtrans: deny / failure / cancel)
    //           -> booking ikut menjadi 'cancelled'. Status mentah Midtrans
    //           tetap tersimpan di payments.transaction_status ('cancel'
    //           berarti dibatalkan user) sehingga UI bisa membedakan
    //           Failed vs Cancelled vs Expired.
    // expired = waktu bayar habis (Midtrans: expire) -> booking 'cancelled'.
    // Nilai ini HARUS sama dengan enum DB (pending,paid,failed,expired).
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_EXPIRED,
    ];

    // === Kategori metode Midtrans ===
    // payment_type resmi Midtrans (Snap API):
    // credit_card, qris, gopay, shopeepay, ovo, dana, linkaja,
    // bank_transfer, echannel, bca_klikpay, bri_epay, cimb_clicks,
    // danamon_online, akulaku, kredivo, uob_ezpay, cstore
    public const EWALLET_TYPES = [
        'gopay',
        'shopeepay',
        'ovo',
        'dana',
        'linkaja',
        'qris',
    ];

    public const CARD_TYPES = [
        'credit_card',
    ];

    public const BANK_TRANSFER_TYPES = [
        'bank_transfer',
        'echannel', // Mandiri Bill
        'bca_klikpay',
        'bri_epay',
        'cimb_clicks',
        'danamon_online',
    ];

    public const RETAIL_TYPES = [
        'cstore', // Indomaret / Alfamart
    ];

    public const PAYLATER_TYPES = [
        'akulaku',
        'kredivo',
        'uob_ezpay',
    ];

    public const SUPPORTED_PAYMENT_TYPES = [
        'credit_card',
        'qris',
        'gopay',
        'shopeepay',
        'ovo',
        'dana',
        'linkaja',
        'bank_transfer',
        'echannel',
        'bca_klikpay',
        'bri_epay',
        'cimb_clicks',
        'danamon_online',
        'akulaku',
        'kredivo',
        'uob_ezpay',
        'cstore',
    ];

    protected $fillable = [
        // Kolom lama (JANGAN dihapus — dipakai controller/service lama)
        'booking_id',
        'midtrans_transaction_id',
        'midtrans_order_id',
        'order_history',
        'payment_status',
        'payment_method',
        'amount',
        'paid_at',
        // Kolom baru (nullable, backward-compatible)
        'snap_token',
        'payment_channel',
        'transaction_status',
        'fraud_status',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'order_history' => 'array',
        'metadata' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    // === Scopes (tambahan, tidak mengubah query lama) ===

    public function scopePaid($query)
    {
        return $query->where('payment_status', self::STATUS_PAID);
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('payment_status', [self::STATUS_FAILED, self::STATUS_EXPIRED]);
    }

    // === Helpers status (read-only, aman dipakai di mana saja) ===

    public function isPaid(): bool
    {
        return $this->payment_status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->payment_status === self::STATUS_PENDING;
    }

    public function isFinal(): bool
    {
        return in_array($this->payment_status, [
            self::STATUS_PAID,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
        ], true);
    }

    public function isEWallet(): bool
    {
        return in_array(strtolower((string) $this->payment_method), self::EWALLET_TYPES, true);
    }

    public function isCard(): bool
    {
        return in_array(strtolower((string) $this->payment_method), self::CARD_TYPES, true);
    }

    /**
     * Label bagus untuk UI: "GoPay", "QRIS", "Credit Card", "BCA Virtual Account", dst.
     * Fallback ke payment_method mentah agar data lama tetap tampil.
     */
    public function getDisplayMethodAttribute(): string
    {
        $method = strtolower((string) ($this->payment_method ?? ''));
        $channel = strtolower((string) ($this->payment_channel ?? ''));

        $labels = [
            'credit_card' => 'Credit/Debit Card',
            'qris' => 'QRIS',
            'gopay' => 'GoPay',
            'shopeepay' => 'ShopeePay',
            'ovo' => 'OVO',
            'dana' => 'DANA',
            'linkaja' => 'LinkAja',
            'bank_transfer' => $channel !== '' ? strtoupper($channel) . ' Virtual Account' : 'Bank Transfer',
            'echannel' => 'Mandiri Bill',
            'bca_klikpay' => 'BCA KlikPay',
            'bri_epay' => 'BRI ePay',
            'cimb_clicks' => 'CIMB Clicks',
            'danamon_online' => 'Danamon Online',
            'akulaku' => 'Akulaku',
            'kredivo' => 'Kredivo',
            'uob_ezpay' => 'UOB eZPay',
            'cstore' => $channel !== '' ? 'Gerai ' . ucfirst($channel) : 'Gerai Retail',
            'manual_confirmation' => 'Manual Confirmation',
        ];

        if (isset($labels[$method])) {
            return $labels[$method];
        }

        return $this->payment_method ?: 'Belum dipilih';
    }
}
