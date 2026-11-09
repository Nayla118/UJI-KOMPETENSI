# Laporan Fitur Baru — Refund Bertingkat & Auto-Refund Midtrans

Tanggal: 2026-10-26
Ruang lingkup: Backend (Laravel) + Android (Kotlin Compose) + Dokumentasi
Status: Semua fitur diimplementasikan, 267 test backend + 152 test Android lulus, Pint/lint/build bersih.
Commit: Belum ada commit (per permintaan user).

---

## 1. Ringkasan Perubahan per Fitur

### 1.1 Kebijakan Refund Bertingkat (Backend)

Konfigurasi di `config/travelo.php` bagian `refund.tiers`, override via env:

| Batas (hari sebelum kunjungan) | Persentase refund | Label |
|---|---|---|
| >= 7 hari | 100% | Full refund |
| 3–6 hari | 50% | Partial refund |
| < 3 hari | 0% | No refund |

- Hitung dari `TRAVELO_TICKET_TIMEZONE` (default `Asia/Jakarta`).
- Refund dihitung dari `total_price` (jumlah yang benar-benar dibayar, termasuk diskon promo), bukan subtotal.
- Booking yang belum dibayar (`payment_status != paid`) → tidak refundable (`reason = not_paid`).
- Tiket sudah dipakai (`ticket_usage_status != unused`) → tidak refundable (`reason = ticket_used`).
- Tanggal kunjungan sudah lewat → tidak refundable (`reason = past_visit`).

### 1.2 RefundPolicyService

File baru: `app/Services/RefundPolicyService.php`

- `calculate(Booking $booking, ?DateTimeInterface $at = null): array`
  Mengembalikan `percentage`, `refund_amount`, `tier_label`, `tier`, `days_before`, `eligible`, dan opsional `reason`.
- `policySummary(): array` — ringkasan tier untuk klien (Android).

### 1.3 Migration Aditif

File baru: `database/migrations/2026_10_26_000002_add_refund_columns_to_booking_change_requests_table.php`

Kolom baru pada `booking_change_requests` (semua nullable, tidak merusak data lama):

| Kolom | Tipe | Keterangan |
|---|---|---|
| `refund_percentage` | integer | snapshot persentase refund saat approval |
| `refund_amount` | decimal(15,2) | snapshot nominal refund saat approval |
| `refund_reference` | string(255) | referensi Midtrans atau catatan manual |
| `refund_failure_reason` | text | alasan gagal refund otomatis |
| `refund_status` | string(20) | diperluas: `processing`, `failed` (default `not_applicable`) |

Index ditambahkan pada `refund_status`. Down() aman (hanya rollback, tidak dipakai produksi).

### 1.4 Endpoint Baru: Refund Preview

`GET /api/bookings/{id}/refund-preview` (auth, hanya pemilik booking)

- Tidak ada efek samping (read-only).
- Mengembalikan `booking_id`, `total_price`, `discount_amount`, `promo_code`, `percentage`, `refund_amount`, `tier`, `tier_label`, `days_before`, `eligible`, `reason`.
- Bukan pemilik → 404.

### 1.5 Integrasi Refund ke Approval Admin

`BookingChangeService::approve()` diupdate:

- Saat approve, snapshot perhitungan `RefundPolicyService` disimpan (`refund_percentage`, `refund_amount`).
- Jika tidak eligible atau 0% → `refund_status = not_applicable`.
- Jika `TRAVELO_AUTO_REFUND=true` dan nominal > 0 → `refund_status = processing`, job `ProcessAutoRefund` diantrekan.
- Jika manual (default) → `refund_status = pending`.
- Activity log ditulis dengan detail refund + flag auto_refund.
- Push notification `notifyChangeRequest` dikirim setelah approve.
- Locking `lockForUpdate()` pada change request dan booking mencegah double-approve.

### 1.6 Auto-Refund via Midtrans (Opsi, default off)

File baru: `app/Jobs/ProcessAutoRefund.php`

- `TRAVELO_AUTO_REFUND=true` diaktifkan via `.env` (default `false`).
- Memanggil `MidtransService::refundTransaction()` → `POST /v2/{transaction_id}/refund`.
- Kunci idempotensi: `travelo_refund_{change_request_id}` — Midtrans menolak duplikat, queue retry (3x, backoff 60s) tidak menghasilkan refund ganda.
- Sukses → `refund_status = refunded`, `refund_reference`, `refunded_at`, push notifikasi sukses.
- Gagal → `refund_status = failed`, `refund_failure_reason` disimpan, booking tetap `cancelled`, keputusan admin tidak dibatalkan.
- Admin panel: tombol **Retry refund** untuk status `failed` (re-dispatch job yang sama).
- Jalur manual tetap tersedia: tombol **Mark refund as sent** → `refund_status = refunded`.

`MidtransService::refundTransaction(string $transactionId, string $refundKey, int $amount, string $reason): array`

- Mengembalikan `success`, `reference`, atau `error`.
- Server key tidak terkonfigurasi → gagal aman (tidak throw).

### 1.7 Admin Panel

View `resources/views/admin/change-requests/index.blade.php` diupdate:

- Menampilkan `refund_status`, persentase, nominal, tier.
- Status `failed` menampilkan alasan gagal + tombol **Retry refund**.
- Status `pending`/`processing` menampilkan form **Mark refund as sent** (catatan opsional).
- Semua transisi dicatat `activity_logs`.

### 1.8 Android — Estimasi Refund di Layar Pengajuan Pembatalan

File baru: `Travelo-Android/.../ui/screens/my_bookings/BookingChangeDialogs.kt`

- `CancellationRequestDialog` memuat `RefundPreview` via `viewModel.loadRefundPreview(bookingId)` saat dibuka.
- Kartu "Estimasi refund": persentase, nominal (format rupiah), label tier, penjelasan (mis. "Up to 50% ..."), hari sebelum kunjungan, total yang dibayar, indikator promo.
- Jika tidak eligible: menampilkan alasan (mis. "Tiket sudah dipakai").
- Teks kebijakan jelas sebelum konfirmasi.
- `MyBookingsViewModel.getRefundPreview()` + `RefundPreview` model di `Models.kt`.
- Endpoint `GET /api/bookings/{id}/refund-preview` ditambahkan ke `ApiService.kt` + `TraveloRepository.kt`.

### 1.9 Android — Status Refund di Detail Booking

`MyBookingsScreen.kt`:

- Kartu booking yang `approved` menampilkan `Refund: <status> · IDR <nominal>`.
- Model `BookingChangeRequest` diperluas dengan `refundStatus`, `refundPercentage`, `refundAmount`, `refundReference`, `refundedAt`, `refundFailureReason`, dan helper `refundStatusLabel()`.

---

## 2. Daftar Migration Baru (Hanya Refund)

| File | Tipe | Aman di DB lama? |
|---|---|---|
| `2026_10_26_000002_add_refund_columns_to_booking_change_requests_table.php` | Aditif (tambah kolom, index) | Ya — semua nullable, tidak ada data yang hilang, default `not_applicable` untuk baris lama |

Semua migrasi sebelumnya (`2026_10_25_*` dan `2026_10_26_000001`) sudah di-apply dan tidak diubah.

Seeder tidak diubah; tidak ada duplikasi data (seeders pakai `firstOrCreate` / hanya `APP_ENV=local`).

---

## 3. Endpoint Baru

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| `GET` | `/api/bookings/{id}/refund-preview` | Login, pemilik booking | Estimasi refund sebelum pengajuan pembatalan |

Endpoint pembatalan yang sudah ada (`POST /api/bookings/{id}/change-requests`) tetap, dengan snapshot refund ditambahkan saat admin approve.

---

## 4. Asumsi

1. Midtrans sandbox/server key sudah dikonfigurasi di `.env` untuk auto-refund nyata. Tanpa key, auto-refund gagal aman (dicatat log, status `failed`).
2. `booking_date` di `bookings` adalah tanggal kunjungan; zona waktu pakai `TRAVELO_TICKET_TIMEZONE`.
3. `total_price` sudah mencakup diskon promo; refund dihitung dari nomor ini.
4. Satu refund per change request (idempotensi per change request id).
5. Job antrean butuh queue worker berjalan (`php artisan queue:work` atau scheduler) untuk auto-refund terlaksana.
6. Push notification hasil refund adalah best-effort; gagal Firebase tidak membatalkan refund.

---

## 5. Hal yang Belum Terverifikasi

| Item | Alasan |
|---|---|
| Auto-refund nyata lewat Midtrans sandbox | Membutuhkan server key valid + transaksi berbayar |
| Push notification nyata (Firebase) | `storage/app/firebase-auth.json` masih `private_key` kosong |
| Verifikasi manual di emulator/perangkat fisik | `adb`/emulator tidak tersedia di lingkungan build |
| Queue worker produksi untuk auto-refund | Belum dikonfigurasi di server produksi |
| Retry refund dari panel admin (UI visual) | Backend + route siap, view sudah diupdate, belum diuji visual |

---

## 6. Hasil Test (Audit Akhir 2026-10-26)

### Backend (`php artisan test`)
- **267 passed**, 1.634 assertions (0 gagal).
- `RefundPolicyTest`: 12 passed — semua tier, batas H-7/H-3/hari H, timezone, unpaid, used ticket, past visit, promo, preview ownership.

### Pint (PHP formatting)
- `pint --test`: **pass** (setelah auto-fix 7 file).

### Frontend Admin (`npm run build`)
- Vite build **berhasil**; semua chunk termuat.

### Android (`./gradlew :app:testDebugUnitTest :app:lintDebug :app:assembleDebug`)
- Unit test: **152 passed**, 0 gagal (termasuk 5 `RefundPreviewTest`).
- Lint: **BUILD SUCCESSFUL**, 0 error.
- Assemble: **BUILD SUCCESSFUL**, APK terbuat.

---

## 7. Dokumentasi yang Diperbarui

- `README.md` — batasan refund manual diganti dengan deskripsi kebijakan bertingkat + opsi auto-refund; test count sinkron.
- `DOKUMENTASI.md` — bagian 12.2 (refund status, tier, auto-refund, retry), 12.4 (test count 267/1634, RefundPolicyTest 12, Android 152), batasan (dihapus "refund tetap manual"), prasyarat env (`TRAVELO_AUTO_REFUND`).

---

## 8. Rekomendasi Selanjutnya

1. Aktifkan queue worker di produksi jika ingin auto-refund (`TRAVELO_AUTO_REFUND=true`).
2. Konfigurasi service account Firebase asli untuk push nyata.
3. Uji auto-refund dengan Midtrans sandbox (transaksi berbayar).
4. Uji retry refund dari panel admin secara visual.
5. Commit semua perubahan (belum dilakukan per permintaan user).
