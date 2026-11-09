# Travelo — Aplikasi Booking Tour & Travel

Travelo adalah aplikasi pemesanan paket wisata yang terdiri dari **aplikasi Android untuk traveller** dan **panel admin berbasis web (Laravel)**. Traveller dapat mencari destinasi, memesan paket, membayar melalui Midtrans, menerima e-ticket ber-QR, mengisi identitas pengunjung, membatalkan booking, dan menulis ulasan kunjungan terverifikasi. Admin mengelola destinasi, paket, booking, pembayaran, kuota per tanggal, pembatalan, pengguna, ulasan, serta memindai tiket di gerbang.

Dokumen ini adalah ikhtisar project. Panduan operasional yang lebih rinci (setup ngrok, Midtrans, Google Sign-In, troubleshooting, dan catatan verifikasi) ada di [`DOKUMENTASI.md`](DOKUMENTASI.md).

---

## Daftar Isi

1. [Fitur Utama](#1-fitur-utama)
2. [Arsitektur](#2-arsitektur)
3. [Teknologi](#3-teknologi)
4. [Struktur Folder](#4-struktur-folder)
5. [Prasyarat](#5-prasyarat)
6. [Menjalankan Backend](#6-menjalankan-backend)
7. [Menjalankan Aplikasi Android](#7-menjalankan-aplikasi-android)
8. [Konfigurasi Penting](#8-konfigurasi-penting)
9. [Role dan Hak Akses](#9-role-dan-hak-akses)
10. [Ringkasan API](#10-ringkasan-api)
11. [Alur Bisnis Utama](#11-alur-bisnis-utama)
12. [Struktur Database](#12-struktur-database)
13. [Testing dan Kualitas Kode](#13-testing-dan-kualitas-kode)
14. [Batasan yang Diketahui](#14-batasan-yang-diketahui)
15. [Dokumentasi Tambahan](#15-dokumentasi-tambahan)

---

## 1. Fitur Utama

### Sisi Traveller (Android)

- **Autentikasi** — login dengan Firebase/Google Sign-In, verifikasi OTP email, dan sesi berbasis token Sanctum.
- **Profil** — ubah nama, telepon, tanggal lahir, kota, dan foto profil; identitas pemesan disimpan untuk booking berikutnya.
- **Eksplorasi** — katalog destinasi dan paket wisata dengan pencarian (debounce 350 ms), filter kategori/harga/rating/durasi/tanggal, pilihan urutan, dan *infinite scroll*; semua filter, urutan, dan halaman diproses backend.
- **Favorit Saya** — simpan destinasi dan paket wisata lewat ikon hati; daftar favorit punya tab Destinasi/Paket dan tersimpan di backend per akun.
- **Promo & voucher** — masukkan kode di ringkasan booking; server memvalidasi dan menghitung subtotal, diskon, dan total. Kode yang sudah dipakai bisa dilepas, dan kuota dilepas kembali bila booking dibatalkan.
- **Booking** — pilih tanggal dan jumlah pelancong, lihat sisa kuota per tanggal, isi identitas setiap pengunjung, unggah dokumen identitas, dan setujui konsen privasi.
- **Pembayaran** - Midtrans Snap di dalam WebView, batas waktu bayar dengan hitung mundur berbasis jam server, auto-expire booking yang belum dibayar, polling status ke backend, auto-return setelah status `paid/confirmed`, serta retry sinkronisasi.
- **E-ticket** — QR token ditandatangani server, hanya terbit setelah pembayaran terkonfirmasi dan dokumen pengunjung lengkap.
- **Pembatalan** — traveller mengirim permintaan pembatalan; booking baru berubah setelah admin menyetujui. Status dan catatan admin tampil di kartu booking.
- **Ulasan terverifikasi** — hanya pengunjung yang benar-benar check-in yang dapat menilai destinasi.
- **Notifikasi push** — FCM untuk status pembayaran, tiket discan, keputusan pembatalan, moderasi ulasan, dan pengingat perjalanan H-1.

### Sisi Admin / Staff (Web)

- **Dashboard** — ringkasan booking, pembayaran, pengguna, dan aktivitas.
- **Master data** — CRUD destinasi dan paket wisata, import/export data.
- **Kuota per tanggal** — kapasitas default dari `max_people`, override kapasitas, dan penutupan tanggal tertentu.
- **Booking & pembayaran** — daftar, detail, ubah status booking/pembayaran, dan riwayat pembayaran.
- **Permintaan pembatalan** — antrean approval/rejection dengan catatan admin, estimasi refund bertingkat, dan opsi refund otomatis via Midtrans (`TRAVELO_AUTO_REFUND=true`) atau manual.
- **Promo & voucher** — CRUD kode, tipe diskon, kuota, jadwal, batas paket/destinasi, riwayat pemakaian, aktif/nonaktif, dan audit log.
- **Scanner tiket** — verifikasi dan redeem QR tiket; admin dapat mengakses semua destinasi, owner hanya destinasi yang ditugaskan.
- **Moderasi ulasan** — sembunyikan/tampilkan ulasan dengan alasan.
- **Pengguna & activity log** — kelola pengguna dan audit trail perubahan penting.

---

## 2. Arsitektur

```text
┌─────────────────────────┐         HTTPS/JSON          ┌──────────────────────────┐
│   Android App           │  ───────────────────────▶   │   Laravel 12 REST API    │
│   Kotlin + Compose      │   Authorization: Bearer     │   Sanctum + Services     │
│   Retrofit + Hilt       │  ◀───────────────────────   │                          │
└───────────┬─────────────┘                             └───────────┬──────────────┘
            │                                                       │
            │ Firebase Auth / Google Sign-In                        │ SQLite / MySQL
            │ Firebase Cloud Messaging                              │
            ▼                                                       ▼
┌─────────────────────────┐                             ┌──────────────────────────┐
│ Firebase Project        │                             │ Database                 │
│ travelo-818bc           │                             │ users, bookings,         │
└─────────────────────────┘                             │ payments, reviews, ...   │
                                                        └───────────┬──────────────┘
                                                                    │
                                              ┌─────────────────────┴───────────────────┐
                                              │                                         │
                                    ┌─────────▼─────────┐                     ┌─────────▼─────────┐
                                    │ Midtrans Snap      │                     │ Admin Panel        │
                                    │ + Webhook callback │                     │ Blade + Tailwind   │
                                    └────────────────────┘                     └────────────────────┘
```

Prinsip penting:

- Backend adalah **sumber kebenaran** untuk status pembayaran, tiket, kuota, dan pembatalan.
- Aplikasi Android tidak pernah membuat QR tiket sendiri; token diterbitkan dan ditandatangani server.
- Perubahan booking hanya terjadi lewat aksi server-side (webhook pembayaran atau approval admin).

---

## 3. Teknologi

### Backend

| Komponen | Versi / Keterangan |
|---|---|
| PHP | `^8.2` |
| Framework | Laravel `^12.0` |
| Autentikasi API | Laravel Sanctum `^4.0` |
| Firebase Admin | `kreait/laravel-firebase` `^6.2` |
| Pembayaran | `midtrans/midtrans-php` `^2.6` |
| Database development | SQLite (`database/database.sqlite`) |
| Testing | PHPUnit `^11.5` |
| Frontend admin | Blade + Tailwind (Vite) |

### Android

| Komponen | Versi / Keterangan |
|---|---|
| Bahasa | Kotlin |
| UI | Jetpack Compose (Material 3) |
| SDK | `compileSdk 35`, `targetSdk 35`, `minSdk 26` |
| DI | Dagger Hilt `2.50` |
| Networking | Retrofit `2.9.0`, OkHttp `4.11.0`, Gson |
| Auth | Firebase Auth + Google Sign-In |
| Push | Firebase Cloud Messaging |
| Gambar | Coil Compose |
| QR | ZXing Core `3.5.3` |
| Navigasi | Navigation Compose `2.7.6` |

---

## 4. Struktur Folder

```text
UJI KOMPETENSI/
├── DOKUMENTASI.md                  # Dokumentasi operasional lengkap
├── README.md                       # Ikhtisar project (file ini)
├── docs/                           # Laporan audit & catatan verifikasi historis
│
├── Travelo-Backend/                # Laravel API + panel admin
│   ├── app/
│   │   ├── Console/Commands/       # Purge dokumen, reminder, cek webhook
│   │   ├── Http/Controllers/
│   │   │   ├── Api/                # Endpoint JSON untuk Android
│   │   │   └── Admin/              # Controller panel web
│   │   ├── Models/                 # Eloquent model
│   │   ├── Services/               # Logika bisnis (payment, tiket, kuota, dll.)
│   │   └── Exceptions/
│   ├── config/travelo.php          # Konfigurasi privasi, tiket, dsb.
│   ├── database/
│   │   ├── migrations/             # Skema database
│   │   ├── seeders/                # Data awal/demo
│   │   └── database.sqlite         # DB development
│   ├── resources/views/            # Blade admin + halaman callback
│   ├── routes/
│   │   ├── api.php                 # Route REST API
│   │   ├── web.php                 # Route admin & webhook landing
│   │   └── console.php             # Scheduler
│   └── tests/                      # Feature & unit test PHPUnit
│
└── Travelo-Android/                # Aplikasi Android
    └── app/src/
        ├── main/java/com/naylaaisyah/traveloapp/
        │   ├── data/               # API, model, repository, aturan lokal
        │   ├── di/                 # Modul Hilt
        │   ├── notifications/      # FCM service & registrar
        │   ├── ui/screens/         # auth, home, explore, booking, payment, dll.
        │   ├── ui/theme/           # Design system "Cute & Dreamy"
        │   └── util/               # Session, format tanggal, dsb.
        ├── main/res/               # Resource Android
        └── test/                   # Unit test JVM
```

---

## 5. Prasyarat

- **PHP 8.2+** dengan ekstensi umum Laravel (`pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `curl`).
- **Composer 2**.
- **Node.js + npm** (untuk aset admin/Vite).
- **Android Studio** (JDK 17 disarankan) atau Android SDK + Gradle.
- **ngrok** (opsional, untuk mengakses backend dari perangkat fisik / webhook Midtrans).
- Akun **Firebase** dan **Midtrans Sandbox** untuk fitur login dan pembayaran.

---

## 6. Menjalankan Backend

```bash
cd Travelo-Backend

# 1. Install dependency
composer install
npm install

# 2. Siapkan environment
copy .env.example .env        # Windows CMD
# cp .env.example .env        # PowerShell / Git Bash
php artisan key:generate

# 3. Siapkan database SQLite
#    (lewati jika database/database.sqlite sudah ada)
type nul > database\database.sqlite      # Windows CMD
# New-Item database\database.sqlite       # PowerShell
# touch database/database.sqlite          # Git Bash / Linux / macOS

# 4. Set akun admin untuk seeder (password minimal 12 karakter) di .env
#    TRAVELO_ADMIN_EMAIL=admin@travelo.com
#    TRAVELO_ADMIN_NAME="Admin Travelo"
#    TRAVELO_ADMIN_PASSWORD=ganti-dengan-password-kuat

# 5. Migrasi + data awal
php artisan migrate
php artisan db:seed

# 6. Symlink storage publik (foto profil, gambar)
php artisan storage:link

# 7. Build aset admin
npm run build

# 8. Jalankan server
php artisan serve
```

Panel admin: `http://127.0.0.1:8000/admin`

Untuk scheduler di production/local yang berjalan lama, daftarkan cron setiap menit:

```bash
* * * * * cd /path/ke/Travelo-Backend && php artisan schedule:run >> /dev/null 2>&1
```

Atau saat pengembangan, jalankan scheduler di terminal terpisah:

```bash
php artisan schedule:work
```

Scheduler menjalankan:

- `travelo:purge-identity-documents` setiap hari pukul 02:00,
- `travelo:send-trip-reminders` setiap hari pukul 09:00,
- `travelo:expire-unpaid-bookings` setiap menit. Command ini membatalkan booking `pending` yang pembayarannya belum lunas setelah `payment_expires_at`, melepaskan kuota + redemption promo, mencatat activity log, dan mengirim push. Aman dijalankan berulang (idempoten) dan tidak menimpa booking yang sudah `paid`.

### Akses dari perangkat fisik / Midtrans

Jalankan ngrok di terminal terpisah:

```bash
ngrok http 8000
```

Lalu set URL HTTPS ngrok di `.env` backend dan base URL Android. Detail lengkap ada di [`DOKUMENTASI.md`](DOKUMENTASI.md) bagian 1.

---

## 7. Menjalankan Aplikasi Android

1. Buka folder `Travelo-Android` di Android Studio.
2. Pastikan `Travelo-Android/app/google-services.json` tersedia dan sesuai package `com.naylaaisyah.traveloapp`.
3. Atur base URL API. Debug build default memakai `http://10.0.2.2:8000/` (emulator). Untuk perangkat fisik, tambahkan ke `local.properties`:

   ```properties
   travelo.baseUrl=http://192.168.1.10:8000/
   # atau URL ngrok:
   # travelo.baseUrl=https://xxxx.ngrok-free.dev/
   ```

   Atau lewat command line:

   ```bash
   ./gradlew assembleDebug -Ptravelo.baseUrl=http://192.168.1.10:8000/
   ```

4. Jalankan:

   ```bash
   cd Travelo-Android
   ./gradlew assembleDebug
   ./gradlew installDebug
   ```

Release build **wajib** memakai URL `https://`:

```properties
travelo.releaseBaseUrl=https://api.example.com/
```

---

## 8. Konfigurasi Penting

### Firebase

| Variabel / File | Fungsi |
|---|---|
| `FIREBASE_PROJECT_ID` | Project ID untuk verifikasi ID token (`travelo-818bc`) |
| `FIREBASE_CREDENTIALS` | Path service account JSON (opsional; wajib untuk push & operasi admin Firebase) |
| `app/google-services.json` | Konfigurasi Firebase di Android |

Login memakai endpoint `POST /api/auth/login` dengan body `{"id_token": "<firebase_id_token>"}`.

### Midtrans

```env
MIDTRANS_SERVER_KEY=isi-dari-dashboard-sandbox
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_MERCHANT_ID=isi-dari-dashboard
```

Webhook:

```text
Payment Notification URL: https://<domain-anda>/api/midtrans/callback
```

Server key **hanya** boleh berada di backend, tidak pernah di aplikasi Android.

### Push Notification (FCM)

- Backend: isi `storage/app/firebase-auth.json` dengan service account valid, atau arahkan `FIREBASE_CREDENTIALS` ke file JSON lain.
- Android: channel `travelo_updates`, izin `POST_NOTIFICATIONS` (Android 13+), dan registrasi token lewat `POST /api/device-tokens`.
- Pesan dikirim **data-only**. Payload hanya berisi `type`, `screen`, `booking_id`/`destination_id`/`review_id` bila relevan, serta `title`/`body`; token tiket, dokumen identitas, dan data pribadi tidak pernah dimasukkan.
- Tap notifikasi membuka layar tujuan: `booking_detail`, `e_ticket`, `review` (destinasi + bagian ulasan), atau `destination`. Berlaku untuk cold start maupun warm start.
- Jika pengguna belum login, tujuan disimpan lalu dilanjutkan setelah login berhasil. Booking yang tidak ditemukan/bukan milik pengguna (403/404) menampilkan pesan ramah dan membuka **Booking Saya**.
- Tombol back dari layar hasil deep link selalu kembali ke **Home** (bukan menumpuk ulang stack).
- Jika Firebase belum dikonfigurasi, pengiriman push dilewati dengan aman dan tidak menggagalkan alur bisnis.

### Identitas & Privasi

```env
TRAVELO_IDENTITY_DISK=local
TRAVELO_IDENTITY_MAX_KB=5120
TRAVELO_IDENTITY_RETENTION_DAYS=90
TRAVELO_PRIVACY_VERSION=2026-10-22
TRAVELO_TICKET_TIMEZONE=Asia/Jakarta

# Batas waktu pembayaran (menit) untuk booking baru.
TRAVELO_PAYMENT_TTL_MINUTES=60
# Panggil API expire Midtrans (best-effort); kegagalan jaringan tidak
# membatalkan expiry lokal.
TRAVELO_EXPIRE_MIDTRANS=true

# Analitik admin: cache agregat (detik) dan batas rentang filter (hari).
TRAVELO_REPORTS_CACHE_TTL=300
TRAVELO_REPORTS_MAX_RANGE_DAYS=366
```

Dokumen identitas disimpan di disk privat, tidak pernah di `public`, dan dibersihkan otomatis setelah masa retensi.

---

## 9. Role dan Hak Akses

| Role | Akses |
|---|---|
| `traveller` | API Android: booking, pembayaran, e-ticket, pembatalan, ulasan, profil |
| `admin` | Seluruh panel admin, termasuk laporan/analitik, scanner semua destinasi, moderasi ulasan, refund, kelola owner |
| `owner` | Portal `/owner`: scanner tiket, kunjungan, dan laporan check-in hanya untuk destinasi yang ditugaskan |

Middleware yang dipakai:

- `auth:sanctum` untuk API traveller,
- `auth` + `role:admin` untuk halaman manajemen,
- `auth` + `role:admin,owner` untuk scanner gerbang.

---

## 10. Ringkasan API

Semua endpoint di bawah ini berada di prefix `/api`. Endpoint bertanda 🔒 membutuhkan header `Authorization: Bearer <token>`.

Endpoint katalog publik (`/destinations`, `/destinations/popular`, `/destinations/{id}`, `/tour-packages`, `/tour-packages/{id}`, `/tour-packages/destination/{destinationId}`) tetap bisa diakses tanpa token. Jika token Sanctum yang valid dikirim, respons tiap item menambahkan `is_wishlisted`; tanpa token atau token tidak valid, field itu tidak dikirim.

### Filter, sort, dan pagination katalog

`GET /destinations` dan `GET /tour-packages` menerima parameter opsional berikut. Semua nilai divalidasi; nilai di luar daftar mengembalikan `422` dengan `errors` per field.

| Parameter | Nilai | Arti |
|---|---|---|
| `q` | teks ≤ 255 | Cari nama/kota/negara/deskripsi destinasi, atau judul/deskripsi/nama destinasi paket. Alias lama `search` tetap diterima. |
| `category` | `pantai`, `gunung`, `budaya`, `kuliner`, `alam`, `kota` | Destinasi memakai kolom `category`; paket mengikuti kategori destinasi induknya. |
| `location` | teks | Cocokkan kota, negara, atau nama (paket: kota/negara/nama destinasi). |
| `country` | teks | Filter lama, pencocokan persis. |
| `destination_id` | integer | Khusus paket wisata. |
| `min_price`, `max_price` | angka ≥ 0 | Untuk destinasi berarti "punya minimal satu paket di rentang harga tersebut". |
| `min_rating` | 0–5 | Rata-rata ulasan **published**; ulasan `hidden` tidak dihitung. |
| `duration` | `1`, `2-3`, `4+` | Untuk destinasi berarti "punya minimal satu paket dengan durasi tersebut". |
| `available_date` | `YYYY-MM-DD` | Hanya item dengan sisa kursi pada tanggal itu; menghormati override kapasitas, tanggal tutup, dan pembayaran `failed`/`expired` yang melepas kursi. |
| `sort` | `newest` (default), `price_asc`, `price_desc`, `rating_desc`, `popular` | `popular` menghitung booking `paid` dengan status `confirmed`/`completed`; destinasi memakai total seluruh paketnya. |
| `page` / `per_page` | ≥ 1 / 1–50 | Default halaman 1 dan 15 baris. `per_page` di atas 50 ditolak. |

Respons selalu berbentuk:

```json
{
  "success": true,
  "data": [],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 0 }
}
```

Tanpa `page`, endpoint tetap mengembalikan halaman pertama dengan 15 baris, sehingga klien lama tidak berubah perilakunya. Filter dan urutan dijalankan lewat subquery agregat (`DestinationFilter`/`TourPackageFilter`), jadi jumlah query tidak bertambah mengikuti jumlah baris. Kolom `destinations.category` ditambahkan lewat migrasi aditif `2026_10_25_000005_add_category_to_destinations_table.php`, dan form admin destinasi kini punya pilihan kategori.

### Promo dan voucher

Kode promo divalidasi dan dihitung sepenuhnya di server. `POST /api/promos/validate` hanya pratinjau untuk ditampilkan; `POST /api/bookings` mengirim ulang `promo_code` dan memvalidasi ulang di dalam transaksi dengan `lockForUpdate` pada baris promo. Kenaikan `used_count` memakai `UPDATE ... WHERE used_count < usage_limit`, jadi dua request bersamaan tidak bisa melewati kuota.

| Aturan | Perilaku |
|---|---|
| Tipe diskon | `percent` (opsional dibatasi `max_discount`) atau `fixed` |
| Batas | `min_transaction`, `usage_limit` (null = tanpa batas), `per_user_limit` |
| Jadwal | `starts_at` / `expires_at`; `is_active` untuk mengaktifkan/menonaktifkan |
| Cakupan | Opsional `tour_package_id` dan/atau `destination_id` |
| Harga | `subtotal_amount` = harga paket × jumlah orang; `discount_amount` tidak pernah melebihi subtotal; `total_price` tidak pernah negatif |
| Pembatalan/kedaluwarsa | Redemption ditandai `released` dan `used_count` dikurangi sekali saja (idempoten, tidak pernah negatif) |
| Midtrans | `gross_amount` selalu memakai `total_price` final; baris `PROMO` negatif hanya untuk rincian |

Migrasi aditif: `2026_10_25_000006_create_promo_codes_table.php`, `2026_10_25_000007_create_promo_redemptions_table.php`, dan `2026_10_25_000008_add_promo_fields_to_bookings_table.php`. Booking lama tetap valid karena `subtotal_amount` nullable dan `discount_amount` default `0`.

### Publik

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/destinations?q=&category=&location=&min_price=&max_price=&min_rating=&duration=&available_date=&sort=&page=&per_page=` | Daftar destinasi (filter, urutan, pagination) |
| `GET` | `/destinations/popular` | Destinasi populer |
| `GET` | `/destinations/{id}` | Detail destinasi |
| `GET` | `/destinations/{id}/reviews` | Ulasan publik |
| `GET` | `/destinations/{id}/rating-summary` | Ringkasan rating |
| `GET` | `/tour-packages?q=&category=&location=&min_price=&max_price=&min_rating=&duration=&available_date=&sort=&page=&per_page=` | Daftar paket wisata (filter, urutan, pagination) |
| `GET` | `/tour-packages/{id}` | Detail paket |
| `GET` | `/tour-packages/destination/{destinationId}` | Paket per destinasi |
| `GET` | `/tour-packages/{id}/availability?month=YYYY-MM&people=2` | Kuota per tanggal |
| `POST` | `/auth/login` | Login dengan Firebase ID token |
| `POST` | `/auth/register` | Registrasi dengan Firebase ID token |
| `POST` | `/auth/otp/send` | Kirim OTP email |
| `POST` | `/auth/otp/resend` | Kirim ulang OTP |
| `POST` | `/auth/otp/verify` | Verifikasi OTP |
| `POST` | `/midtrans/callback` | Webhook Midtrans (signature wajib) |
| `POST` | `/tickets/verify` | Verifikasi token tiket |

### Traveller 🔒

| Method | Endpoint | Fungsi |
|---|---|---|
| `POST` | `/auth/logout` | Logout & unregister device token |
| `GET` | `/auth/me` | Profil saat ini |
| `PUT` | `/auth/profile` | Update profil |
| `POST` | `/auth/profile/photo` | Upload foto profil |
| `GET` | `/auth/identity` | Identitas pemesan tersimpan |
| `DELETE` | `/auth/identity` | Hapus identitas tersimpan |
| `GET` | `/my-bookings` | Daftar booking milik user |
| `POST` | `/bookings` | Buat booking |
| `GET` | `/bookings/{id}` | Detail booking |
| `GET` | `/bookings/{id}/e-ticket` | Ambil e-ticket |
| `GET` | `/bookings/{id}/change-requests` | Riwayat permintaan pembatalan |
| `POST` | `/bookings/{id}/change-requests` | Ajukan pembatalan |
| `POST` | `/device-tokens` | Daftarkan token FCM |
| `DELETE` | `/device-tokens` | Hapus token FCM |
| `GET` | `/wishlist?type=destination\|tour_package&page=1&per_page=15` | Favorit milik user, terbaru dulu, dengan ringkasan item |
| `POST` | `/wishlist/toggle` | Tambah/hapus favorit (`item_type`, `item_id`) → `{is_wishlisted}` |
| `DELETE` | `/wishlist/{id}` | Hapus satu favorit milik user |
| `POST` | `/promos/validate` | Pratinjau promo (`code`, `tour_package_id`, `people`, `date`) → subtotal/diskon/total dari server |
| `GET` | `/destinations/{id}/review-eligibility` | Cek kelayakan ulasan |
| `POST` | `/bookings/{bookingId}/review` | Kirim ulasan |
| `PATCH` | `/reviews/{reviewId}` | Edit ulasan |
| `DELETE` | `/reviews/{reviewId}` | Hapus ulasan |
| `GET` | `/reviews/mine` | Ulasan milik user |
| `GET` | `/bookings/{id}/visitors` | Daftar pengunjung |
| `PATCH` | `/bookings/{id}/visitors/{visitorId}` | Update pengunjung |
| `POST` | `/bookings/{id}/visitors/{visitorId}/identity-document` | Upload dokumen identitas |
| `DELETE` | `/bookings/{id}/visitors/{visitorId}/identity-document` | Hapus dokumen |
| `GET` | `/bookings/{id}/visitors/{visitorId}/identity-document` | Unduh dokumen |
| `GET` | `/bookings/{bookingId}/payment-status` | Status pembayaran + `payment_expires_at`, `payment_expires_in_seconds`, `payment_expired`, `server_time` |
| `POST` | `/bookings/{bookingId}/payment/verify-and-sync` | Verifikasi & sinkronisasi |
| `POST` | `/bookings/{bookingId}/payment/retry-sync` | Retry sinkronisasi |
| `POST` | `/bookings/{bookingId}/payment/mark-as-paid` | Tandai lunas (fallback) |
| `POST` | `/payments/create-snap-token` | Buat Snap token |

### Panel Admin (web)

Prefix: `/admin`

| Area | Route utama |
|---|---|
| Dashboard | `admin.dashboard` |
| Laporan & analitik | `admin.reports.index`, `admin.reports.data`, `admin.reports.export` |
| Destinasi | `admin.destinations.*` |
| Paket wisata | `admin.tour-packages.*` |
| Kuota tanggal | `admin.tour-packages.availability` |
| Booking | `admin.bookings.index`, `admin.bookings.show` |
| Promo / voucher | `admin.promos.*` (CRUD, riwayat pemakaian, aktif/nonaktif) |
| Pembatalan | `admin.change-requests.index` (approve/reject/refund) |
| Pembayaran | `admin.payments.index` |
| Pengguna | `admin.users.index` |
| Ulasan | `admin.reviews.index` |
| Scanner | `admin.scanner` |
| Activity log | `admin.activity-logs.index` |
| Import/Export | `admin.import.*`, `admin.export.*` |

---

## 11. Alur Bisnis Utama

### Booking dan pembayaran

```text
Pilih paket & tanggal
   → cek kuota (GET /availability)
   → isi data pengunjung + upload dokumen
   → POST /bookings (server mengunci baris paket & validasi kapasitas)
   → booking menerima payment_expires_at = now + TRAVELO_PAYMENT_TTL_MINUTES
   → terima snap_token
   → buka Midtrans Snap di WebView
   → app menampilkan hitung mundur mm:ss dari sisa detik versi server
   → polling GET /payment-status tiap 5 detik
   → webhook Midtrans mengubah status menjadi paid/confirmed
   → app auto-return ke layar konfirmasi
   → jika batas waktu lewat: booking cancelled, pembayaran expired, kursi dilepas
```

Kuota dihitung dari booking berstatus `pending`, `confirmed`, atau `completed` dengan pembayaran bukan `failed/expired`. Booking `cancelled` langsung melepas kursinya, sehingga booking yang kedaluwarsa tidak lagi memakai kuota walaupun scheduler baru berjalan beberapa detik kemudian.

Batas waktu:
- `payment_expires_at` disimpan di `bookings` dan dikirim juga oleh endpoint booking/payment-status.
- Scheduler `travelo:expire-unpaid-bookings` berjalan tiap menit; setiap booking diproses dalam transaksi dengan row lock dan pengecekan status terbaru, sehingga webhook Midtrans yang sudah `paid` selalu menang.
- Konvensi status: booking → `cancelled`; pembayaran → `expired` (sama dengan pemetaan webhook Midtrans `expire`).
- Webhook `paid` yang terlambat hanya menghidupkan booking bila kuota tanggal masih tersedia. Jika kursi sudah habis, pembayaran tetap dicatat `paid` untuk refund manual, booking tetap `cancelled`, dan traveller menerima notifikasi.

### E-ticket dan scan gerbang

1. E-ticket hanya terbit untuk booking `paid` + `confirmed`.
2. Semua dokumen pengunjung harus lengkap.
3. QR berisi token yang ditandatangani server dan memiliki masa berlaku.
4. Staff memindai QR di `/admin/scanner`.
5. Token hanya dapat diredeem satu kali; scan kedua ditolak.

### Pembatalan

1. Traveller mengirim permintaan lewat `POST /bookings/{id}/change-requests`.
2. Booking **tidak berubah** selama status masih `pending`.
3. Admin menyetujui → booking menjadi `cancelled`, refund ditandai `pending` (atau `processing` jika auto-refund aktif).
4. Refund diproses otomatis via Midtrans jika `TRAVELO_AUTO_REFUND=true`, atau ditandai `refunded` secara manual oleh admin setelah saldo dikembalikan. Gagal gateway ditangani sebagai `failed` dan dapat di-retry dari panel admin, tanpa refund ganda.
5. Semua transisi dicatat di `activity_logs` dan dikirim sebagai push notification.

### Ulasan terverifikasi

- Hanya booking yang sudah check-in (tiket `used`) yang layak diulas.
- Destinasi diturunkan server dari booking, bukan dari input client.
- Ulasan yang disembunyikan admin tidak muncul di publik dan tidak dihitung di agregat.

### Laporan dan analitik

Halaman `admin.reports` (khusus admin) menampilkan kartu ringkasan, enam chart, dan tabel rincian. Filter tanggal (`start_date`, `end_date`) dan pengelompokan (`period`: `day`/`week`/`month`) dikirim ke server; default-nya 30 hari terakhir dan rentang dibatasi `TRAVELO_REPORTS_MAX_RANGE_DAYS` (366 hari). Endpoint JSON `admin.reports.data` memakai filter yang sama untuk chart, sedangkan dashboard memakai `ReportService` yang sama dengan jendela 30 hari agar angka kedua layar tidak pernah berbeda.

Definisi metrik:

- **Pendapatan**: diakui saat pembayaran diterima (`payments.paid_at`, fallback `bookings.created_at` untuk data lama) dan hanya untuk booking `payment_status = paid`. Refund dihitung terpisah pada `booking_change_requests.refunded_at` dan tidak pernah dimundurkan ke tanggal penjualan. **Net revenue = gross − refund**; gross dan refund tetap ditampilkan sendiri-sendiri.
- **Top 5 paket/destinasi**: hanya booking `paid` dengan status `confirmed`/`completed`; booking `cancelled`, pending, atau gagal tidak dihitung.
- **Okupansi**: kursi terisi vs kapasitas per tanggal kunjungan, memakai aturan `AvailabilityService` yang sama (status penahan kursi, pembayaran `failed`/`expired` melepas kursi, override kapasitas, dan tanggal tutup). Kapasitas satu paket = `max_people × jumlah hari` yang disesuaikan override.
- **Tingkat pembatalan**: booking `cancelled` dibagi seluruh booking yang dibuat pada rentang tersebut, ditambah rincian status permintaan/refund dan alasan pembatalan terbanyak.
- **Pengguna baru**: hanya `users.role = traveller`; akun admin/owner tidak dihitung.
- **Rating**: hanya ulasan `published`; ulasan `hidden` tidak masuk rata-rata maupun distribusi.

Agregat dihitung dengan `GROUP BY` di database (bukan N+1), memakai indeks pelaporan dari migrasi aditif `2026_10_26_000001_add_reporting_indexes.php`. Payload ringkasan di-cache selama `TRAVELO_REPORTS_CACHE_TTL` detik dengan cache key yang memuat rentang tanggal dan period; export selalu membaca data terbaru (melewati cache). `DATE()` dipakai untuk bucket harian sehingga query yang sama berjalan di SQLite dan MySQL.

Chart.js dipasang lewat npm dan dibundel Vite (`resources/js/reports.js`, ikut `npm run build`), bukan CDN. Data chart dikirim server sebagai JSON di halaman, jadi browser tidak menerima baris booking mentah.

Export: **CSV** berisi beberapa section (ringkasan, pendapatan per period, status booking, tren, top paket/destinasi, okupansi, pembatalan/refund, pengguna baru, rating). Opsi **Print/PDF** menghasilkan HTML siap cetak; PDF dibuat lewat Print → Save as PDF di browser. Ini mengikuti mekanisme export yang sudah ada di project dan sengaja tidak menambah dependency dompdf.

---

## 12. Struktur Database

Tabel utama:

| Tabel | Isi |
|---|---|
| `users` | Akun traveller/admin/owner, profil, role, Firebase UID |
| `destinations` | Destinasi wisata, termasuk `category` (pantai/gunung/budaya/kuliner/alam/kota) |
| `tour_packages` | Paket wisata dan kapasitas default (`max_people`) |
| `bookings` | Booking, status, tanggal, jumlah orang, total harga |
| `booking_visitors` | Identitas tiap pengunjung + status dokumen |
| `payments` | Transaksi Midtrans dan status pembayaran |
| `tour_package_date_availabilities` | Override kapasitas / tanggal tutup |
| `booking_change_requests` | Permintaan pembatalan dan status refund |
| `reviews` | Ulasan kunjungan terverifikasi + moderasi |
| `saved_identities` | Identitas pemesan untuk booking berikutnya |
| `device_tokens` | Token FCM per perangkat |
| `login_otps` | Kode OTP email (hash, kedaluwarsa, percobaan) |
| `activity_logs` | Audit trail aksi penting |
| `personal_access_tokens` | Token Sanctum |

Migrations berada di `Travelo-Backend/database/migrations/`. Tidak ada reset atau operasi destruktif yang diperlukan untuk menjalankan project. Analitik admin memakai indeks aditif dari `2026_10_26_000001_add_reporting_indexes.php` (`bookings.payment_status+created_at`, `bookings.booking_status+created_at`, `bookings.tour_package_id+booking_date`, `payments.paid_at`, `booking_change_requests.refund_status+refunded_at`, `users.role+created_at`).

---

## 13. Testing dan Kualitas Kode

### Backend

```bash
cd Travelo-Backend
php artisan test
```

Hasil verifikasi terakhir:

- **267 test lulus**, 1.634 assertions.
- Cakupan utama: filter/sort/pagination katalog, availability/kuota, pembatalan, kebijakan refund bertingkat + preview endpoint, push notification, pembayaran, batas waktu & auto-expire pembayaran, lifecycle tiket, identitas pengunjung, ulasan, wishlist/favorit, promo/voucher, laporan/analitik admin, keamanan, OTP, dan profil.
- `RefundPolicyTest` (12 test) memverifikasi setiap tier refund (H-7, H-3, hari H), batas hari, timezone (Asia/Jakarta), booking belum dibayar, tiket sudah dipakai, perhitungan dengan diskon promo, dan preview endpoint hanya untuk pemilik.
- `PushNotificationTest` (16 test) memeriksa payload data-only tiap notifikasi: `type`/`screen`/id yang benar, tidak ada field sensitif, serta tidak adanya blok `notification` agar PendingIntent Android selalu dipakai.

### Android

```bash
cd Travelo-Android
./gradlew :app:testDebugUnitTest :app:lintDebug :app:assembleDebug
```

Hasil verifikasi terakhir:

- `testDebugUnitTest`: **152 test, 0 gagal** (termasuk 16 tes `DeepLinkParser` untuk semua tipe notifikasi, data tidak lengkap, dan tipe tidak dikenal; 5 tes `RefundPreview` untuk pemetaan tier dan format nominal).
- `lintDebug`: **0 error**, 31 warning (mayoritas warning lama/deprecation).
- `assembleDebug`: APK berhasil dibuat.

### Format kode PHP

```bash
cd Travelo-Backend
./vendor/bin/pint
```

---

## 14. Batasan yang Diketahui

- **Push notification nyata** belum terverifikasi karena service account Firebase di lingkungan pengembangan masih placeholder (`private_key` kosong). Pengiriman dilewati dengan aman.
- **Refund** mengikuti kebijakan bertingkat (≥7 hari: 100%, 3–6 hari: 50%, <3 hari: 0%) dihitung dari jumlah yang dibayar (termasuk diskon promo). Secara default refund disetujui manual oleh admin; dengan `TRAVELO_AUTO_REFUND=true` refund diproses otomatis lewat Midtrans Refund API (antrean dengan kunci idempotensi, tanpa refund ganda, jalur manual sebagai fallback).
- **Nilai refund laporan** memakai `bookings.total_price` karena belum ada kolom nominal refund parsial; refund diasumsikan penuh.
- **Export PDF laporan** berupa HTML siap cetak (Print → Save as PDF), sama seperti export lain di project; belum ada file PDF biner karena tidak menambah dependency dompdf.
- **Verifikasi perangkat/emulator** untuk alur pembayaran dan scan tiket belum dilakukan pada pass terakhir karena `adb`/emulator tidak tersedia di lingkungan build.
- **Release build Android** masih menggunakan URL ngrok bawaan dan `isMinifyEnabled = false`; konfigurasi produksi perlu disesuaikan sebelum rilis.
- **Reschedule/ubah tanggal tidak didukung**; pembatalan adalah satu-satunya perubahan booking.
- **Daftar Favorit Saya** memuat maksimal 50 item per tab dan belum punya UI pagination bertahap.

---

## 15. Dokumentasi Tambahan

| Dokumen | Isi |
|---|---|
| [`DOKUMENTASI.md`](DOKUMENTASI.md) | Panduan lengkap: ngrok, Google Sign-In, Midtrans, troubleshooting, brand, verifikasi, kuota, pembatalan, push |
| [`docs/privacy-identitas-pengunjung.md`](docs/privacy-identitas-pengunjung.md) | Kebijakan dan teknis perlindungan identitas pengunjung |
| [`docs/final-verification-2026-10-20.md`](docs/final-verification-2026-10-20.md) | Laporan verifikasi final |
| [`docs/laporan-audit-2026-10-21.md`](docs/laporan-audit-2026-10-21.md) | Laporan audit keamanan dan kualitas |
| [`docs/identitas-dan-tiket-2026-10-21.md`](docs/identitas-dan-tiket-2026-10-21.md) | Catatan implementasi identitas & tiket |
| [`docs/laporan-scanner-tiket-2026-10-21.md`](docs/laporan-scanner-tiket-2026-10-21.md) | Catatan implementasi scanner tiket |
| [`docs/laporan-pemulihan-upload-2026-10-21.md`](docs/laporan-pemulihan-upload-2026-10-21.md) | Catatan pemulihan upload dokumen |
| [`docs/laporan-auto-return-payment-2026-10-22.md`](docs/laporan-auto-return-payment-2026-10-22.md) | Catatan auto-return pembayaran |
| [`docs/laporan-pengembangan-profil-2026-10-22.md`](docs/laporan-pengembangan-profil-2026-10-22.md) | Catatan pengembangan profil |

---

## Status Project

Project berada dalam tahap pengembangan aktif dan **belum di-commit** pada working tree terakhir. Semua perubahan kode berada di worktree lokal; tidak ada commit, push, reset, atau operasi database destruktif yang dilakukan.
