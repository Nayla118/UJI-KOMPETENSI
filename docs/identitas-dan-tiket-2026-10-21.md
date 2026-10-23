# Identitas Pengunjung, E-Ticket, Scanner Gerbang, dan Riwayat Tiket

Tanggal implementasi: **2026-10-21**

## Ringkasan

- Data pengunjung disimpan per booking (termasuk pemesan) di tabel
  `booking_visitors`.
- Dokumen identitas disimpan di private disk dengan nama acak; upload hanya
  menandai `pending`, bukan `verified`.
- E-ticket tetap memakai token HMAC yang sudah ada. Format token untuk tiket
  versi 1 tidak berubah, sehingga QR lama tetap valid.
- Status pembayaran dan status pemakaian tiket dipisahkan:
  `payment_status` (`pending/paid/failed/expired`) dan
  `ticket_status` (`unused/used`) + status turunan `expired`.
- Endpoint redeem tiket bersifat atomik. Halaman scanner admin tersedia di
  `/admin/scanner` untuk `admin` dan `staff`; staff diarahkan ke scanner
  setelah login.
- Setelah tiket dipakai, Android memindahkannya dari daftar aktif My Bookings
  ke **Ticket history** read-only tanpa QR. Status ini berasal dari
  `ticket_usage_status` backend.

## Skema database

Migrasi: `database/migrations/2026_10_21_000001_add_visitor_identities_and_ticket_lifecycle.php`

### `booking_visitors`

| Kolom | Keterangan |
|---|---|
| `booking_id` | FK ke booking, cascade delete. |
| `is_booker` | Satu baris per booking ditandai pemesan. |
| `name`, `identity_type`, `identity_type_other`, `identity_number`, `date_of_birth`, `phone` | Data pengunjung. |
| `document_path` | Path private (tidak pernah dikirim ke API). |
| `document_mime`, `document_size` | Hasil deteksi server. |
| `document_status` | `missing/pending/verified/rejected/purged`. |
| `document_uploaded_at`, `document_reviewed_at`, `document_reviewed_by`, `document_rejection_reason`, `document_purged_at` | Audit dokumen. |

### `saved_identities`

Menyimpan data pemesan satu kali per akun agar booking berikutnya tidak perlu
mengisi ulang:

| Kolom | Keterangan |
|---|---|
| `user_id` | Unik; satu baris per akun, cascade delete. |
| `name`, `identity_type`, `identity_type_other`, `identity_number`, `date_of_birth`, `phone` | Data diri pemesan. |
| `document_path` | Salinan dokumen private milik akun (tidak pernah dikirim ke API). |
| `document_mime`, `document_size`, `document_status` | Metadata dan status salinan dokumen. |
| `document_uploaded_at`, `document_reviewed_at`, `document_reviewed_by`, `document_rejection_reason` | Audit salinan dokumen. |

Salinan akun terpisah dari salinan per booking. Retensi booking (90 hari)
tidak menghapus salinan akun; salinan akun hanya diganti/dihapus oleh pemilik
atau lewat `DELETE /api/auth/identity`.

### `bookings` (tambahan)

| Kolom | Keterangan |
|---|---|
| `ticket_status` | `unused` atau `used` (hanya diubah oleh redeem). |
| `ticket_used_at`, `ticket_used_by` | Waktu dan petugas yang melakukan redeem. |
| `ticket_version` | Versi token; naik hanya saat reissue resmi. |
| `privacy_consent_at`, `privacy_consent_version` | Bukti persetujuan. |

`expired` **tidak disimpan**; nilainya dihitung dari `booking_date` di zona
waktu `Asia/Jakarta`, sehingga tidak pernah basi.

### `users` dan `staff_destinations`

- `users.role`: `traveller` (default), `admin`, `staff`.
- `staff_destinations(user_id, destination_id)`: pembatasan destinasi petugas.
- Migrasi mempromosikan user lama yang memiliki password lokal menjadi
  `admin` agar login admin yang sudah berjalan tidak berubah.

## Endpoint API pengguna

Semua endpoint di bawah memerlukan Sanctum dan hanya mengakses booking milik
pengguna yang login (booking pengguna lain = 404).

| Method | Path | Fungsi |
|---|---|---|
| `GET` | `/api/auth/identity` | Data diri pemesan milik akun untuk prefill booking; `no-store`, tanpa path dokumen. |
| `DELETE` | `/api/auth/identity` | Hapus data diri + salinan dokumen level akun. |
| `POST` | `/api/bookings` | Membuat booking; menerima array `visitors` opsional (wajib berjumlah sama dengan `people_count`) dan `privacy_consent`. |
| `GET` | `/api/bookings/{id}/visitors` | Daftar pengunjung + status dokumen (nomor disamarkan). |
| `PATCH` | `/api/bookings/{id}/visitors/{visitorId}` | Koreksi data sebelum tiket digunakan. |
| `POST` | `/api/bookings/{id}/visitors/{visitorId}/identity-document` | Upload/ganti dokumen (multipart field `document`). |
| `DELETE` | `/api/bookings/{id}/visitors/{visitorId}/identity-document` | Hapus dokumen. |
| `GET` | `/api/bookings/{id}/visitors/{visitorId}/identity-document` | Unduh dokumen sendiri (header `no-store`, `nosniff`). |
| `GET` | `/api/bookings/{id}/e-ticket` | E-ticket; 409 jika belum dibayar atau dokumen pengunjung belum lengkap. |
| `POST` | `/api/tickets/verify` | Verifikasi publik token QR (authenticity + validitas masuk). |

### Prefill dan reuse data diri pemesan

- Baris pertama di form booking adalah pemesan (`is_booker = true`). Data
  scalar-nya disimpan ke `saved_identities` saat booking dibuat.
- `GET /api/auth/identity` mengembalikan data pemilik akun saja, termasuk nomor
  identitas asli untuk mengisi form, dengan header `no-store`. Path dokumen
  tidak pernah dikembalikan.
- Booking berikutnya dapat mengirim `reuse_saved_identity_document = true`.
  Backend menyalin dokumen private akun ke folder booking baru; aplikasi tidak
  perlu upload ulang. Salinan baru berstatus `pending`, bukan otomatis
  `verified`.
- Jika data jenis/nomor identitas diubah, salinan dokumen akun dianggap tidak
  cocok dan dihapus; pemesan perlu upload dokumen baru.
- Hanya pemesan yang diingat. Pengunjung tambahan tetap mengisi data dan
  dokumennya sendiri pada setiap booking.
- Salinan yang dipakai ulang selalu ditandai `pending`, bukan otomatis
  `verified`; petugas tetap dapat meninjau/menolak salinan pada booking baru.
- Aplikasi menampilkan data tersimpan di layar **Profile** dengan nomor
  tersamar dan status dokumen. Aksi hapus memerlukan konfirmasi dan hanya
  menghapus data level akun; riwayat booking tidak berubah.


Contoh potongan `visitors` saat membuat booking:

```json
{
  "tour_package_id": 1,
  "booking_date": "2026-11-01",
  "people_count": 2,
  "privacy_consent": true,
  "reuse_saved_identity_document": true,
  "visitors": [
    {
      "name": "Budi Santoso",
      "identity_type": "ktp",
      "identity_number": "3171234567891234",
      "is_booker": true
    },
    {
      "name": "Ani Santoso",
      "identity_type": "kia",
      "identity_number": "3171234567899999",
      "date_of_birth": "2015-05-05"
    }
  ]
}
```

`reuse_saved_identity_document` hanya memengaruhi baris pemesan. Jika akun
belum punya salinan dokumen, backend mengembalikan 422 dan aplikasi meminta
pemesan upload dokumen.

Jenis identitas yang diterima: `ktp`, `kia`, `student_card`, `other`.
Untuk `other`, `identity_type_other` wajib diisi. Untuk `kia`, tanggal lahir
wajib diisi.

## Endpoint admin scanner

Memakai session admin yang sudah ada (`auth` web) dan CSRF normal.

| Method | Path | Hak akses |
|---|---|---|
| `GET` | `/admin/scanner` | `admin` atau `staff`. Halaman kamera + input manual. |
| `POST` | `/admin/tickets/redeem` | `staff` (sesuai `staff_destinations`) atau `admin`. Body: `token`. |
| `POST` | `/admin/bookings/{booking}/ticket/reissue` | `admin` saja. |
| `PATCH` | `/admin/visitors/{visitor}/document-review` | `staff`/`admin`; body `status=verified|rejected`, `reason` wajib bila rejected. |
| `GET` | `/admin/visitors/{visitor}/document` | `staff`/`admin`, dibatasi destinasi. |

Contoh redeem:

```bash
curl -X POST https://host/admin/tickets/redeem \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: ..." \
  -b "travelo_session=..." \
  -d '{"token":"<token-dari-QR>"}'
```

Respons sukses berisi `usage_status=used`, `destination`, `visit_date`,
`reference`, dan `visitor_count`. Tidak ada dokumen atau nomor identitas di
respons.

### Halaman scanner dan riwayat tiket

Halaman `/admin/scanner` memakai kamera (`html5-qrcode`, dimuat malas) yang
mulai otomatis di HTTPS/localhost, pembacaan screenshot QR, dan input token
manual. QR yang terdeteksi langsung dicek tanpa menekan **Take QR photo**. Area
pindai mengikuti ukuran viewfinder (maksimum 360px), bukan kotak tetap 250px,
sehingga QR yang tampil lebih besar di layar ponsel tidak terpotong. Decoder
ZXing dipakai konsisten di semua browser. Screenshot didekode di browser; hanya
token hasil decode yang dikirim ke server. Hasil yang ditampilkan:
redeemed, already used, expired, payment not confirmed, wrong destination,
invalid token, sesi kedaluwarsa, dan gangguan koneksi. Token tidak disimpan di
browser; input manual dibersihkan setelah pengecekan. Nama pemesan dirender
sebagai teks, bukan HTML.

Di Android, `TicketHistoryRules` memisahkan daftar aktif dan riwayat memakai
`ticket_usage_status` dari `/api/my-bookings`:

- `used` -> selalu riwayat.
- `expired` -> riwayat jika pembayaran sukses/terkonfirmasi.
- Booking belum dibayar tidak dianggap pernah menjadi tiket.

Tiket riwayat tidak menampilkan tombol e-ticket atau QR. `ticket_used_at`
dipakai hanya untuk keterangan “Used on …”. Detail lengkap ada di
[`docs/laporan-scanner-tiket-2026-10-21.md`](laporan-scanner-tiket-2026-10-21.md).

### Atomisitas double scan

Redeem menjalankan satu conditional update:

```sql
UPDATE bookings
SET ticket_status = 'used', ticket_used_at = ?, ticket_used_by = ?
WHERE id = ?
  AND ticket_status = 'unused'
  AND payment_status = 'paid'
  AND booking_status = 'confirmed'
  AND booking_date >= ?
```

Hanya permintaan yang mengubah 1 baris yang dianggap berhasil. Pemindaian
kedua (termasuk yang bersamaan) mendapat 409 `used`. Ini diuji tanpa
mengandalkan row lock database.

## Siklus status

```
payment pending ──► paid ──► booking confirmed
                                 │
                                 ▼
                         ticket unused ──(scan sah)──► used
                                 │
                                 └─(tanggal kunjungan lewat)─► expired (turunan)
```

Pembayaran sukses **tidak** mengubah `ticket_status`. Hanya redeem sah yang
mengubahnya menjadi `used`.

## Kompatibilitas

- Booking lama tanpa baris `booking_visitors` tetap bisa memperoleh e-ticket
  (jalur legacy). Booking baru dari aplikasi selalu mengirim data pengunjung.
- Token e-ticket versi 1 tidak berubah byte-per-byte. Field `version` hanya
  ditambahkan saat tiket di-reissue (versi > 1).
- Endpoint dan alur Firebase, OTP, Midtrans webhook, serta hardening lama
  tidak diubah.

## Retensi

- Dokumen per booking: `config/travelo.php` (`retention_days`, default 90),
  dihapus oleh `php artisan travelo:purge-identity-documents` setiap hari pukul
  02.00.
- Salinan dokumen akun (`saved_identities`) **tidak** ikut terhapus oleh
  retensi booking. Salinan ini bertahan sampai pemilik mengganti/menghapusnya,
  atau memanggil `DELETE /api/auth/identity`.
- Data scalar akun tetap ada sampai akun dihapus atau endpoint di atas dipanggil.
- Kebijakan lengkap: `docs/privacy-identitas-pengunjung.md`.

## Test

- `tests/Feature/VisitorIdentityTest.php`
- `tests/Feature/SavedIdentityTest.php`
- `tests/Feature/TicketLifecycleTest.php`
- `tests/Feature/TicketScannerUiTest.php`
- Android `TicketHistoryRulesTest.kt` (5 test JVM)
- Android `SavedIdentityPrefillTest.kt` (3 test JVM)

Mencakup upload valid/tidak valid/oversize, akses lintas pengguna, booking
multi-pengunjung, booking lama, prefill data pemesan, reuse salinan dokumen
akun, penggantian/penghapusan salinan, isolasi data antar akun, e-ticket sebelum
bayar, token valid/palsu/dimodifikasi, tiket kedaluwarsa/terpakai, double scan,
pembatasan petugas, reissue, pembayaran sukses yang tidak menandai tiket
terpakai, akses scanner admin/staff/traveller, redirect login staff, serta
klasifikasi tiket aktif vs riwayat.

## Yang belum tersedia

- Data pengunjung tambahan belum disimpan per akun; yang diingat otomatis hanya
  pemesan. Booking keluarga/grup tetap mengisi data pengunjung lain.
- Halaman log semua scan admin; yang ada adalah hasil scan per tiket dan
  riwayat tiket Android.
- UI admin untuk meninjau dokumen identitas (endpoint review sudah ada, tetapi
  belum ada halaman daftar/detail).
- UI pengelolaan `staff_destinations`; penugasan masih lewat database.
- Uji kamera scanner dan alur scan -> Ticket history di perangkat fisik belum
  dilakukan; verifikasi pass ini berupa build, test akses, dan unit test.
- OCR/readability otomatis dan facial recognition — sengaja tidak dibuat.
- Halaman hukum final (identitas badan usaha, kontak DPO) masih placeholder.
