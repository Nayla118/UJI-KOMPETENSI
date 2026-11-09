# Travelo — Dokumentasi Lengkap Proyek

> Dokumen ini merangkum seluruh proyek **Travelo** secara menyeluruh: tujuan, fitur, arsitektur, teknologi, struktur folder, cara menjalankan, konfigurasi, hak akses, API, alur bisnis, skema database, notifikasi/deep link, keamanan, pengujian, troubleshooting, dan batasan.
>
> Dokumen ini melengkapi [`README.md`](README.md) (ikhtisar) dan [`DOKUMENTASI.md`](DOKUMENTASI.md) (panduan operasional rinci).

---

## Daftar Isi

1. [Ringkasan Proyek](#1-ringkasan-proyek)
2. [Fitur Utama](#2-fitur-utama)
3. [Arsitektur Sistem](#3-arsitektur-sistem)
4. [Teknologi dan Versi](#4-teknologi-dan-versi)
5. [Struktur Folder](#5-struktur-folder)
6. [Prasyarat](#6-prasyarat)
7. [Menjalankan Backend](#7-menjalankan-backend)
8. [Menjalankan Aplikasi Android](#8-menjalankan-aplikasi-android)
9. [Konfigurasi Penting](#9-konfigurasi-penting)
10. [Role dan Hak Akses](#10-role-dan-hak-akses)
11. [Ringkasan API](#11-ringkasan-api)
12. [Alur Bisnis Utama](#12-alur-bisnis-utama)
13. [Struktur Database](#13-struktur-database)
14. [Notifikasi Push dan Deep Link](#14-notifikasi-push-dan-deep-link)
15. [Keamanan dan Privasi](#15-keamanan-dan-privasi)
16. [Testing dan Kualitas Kode](#16-testing-dan-kualitas-kode)
17. [Scheduler dan Command](#17-scheduler-dan-command)
18. [Troubleshooting](#18-troubleshooting)
19. [Batasan dan Rencana Pengembangan](#19-batasan-dan-rencana-pengembangan)
20. [Dokumen Terkait](#20-dokumen-terkait)
21. [Status Proyek](#21-status-proyek)

---

## 1. Ringkasan Proyek

**Travelo** adalah aplikasi pemesanan paket wisata yang terdiri dari dua bagian:

| Bagian | Deskripsi |
|---|---|
| **Travelo-Android** | Aplikasi mobile untuk traveller: mencari destinasi, memesan paket, membayar, menerima e-ticket ber-QR, mengisi identitas pengunjung, membatalkan booking, dan menulis ulasan terverifikasi. |
| **Travelo-Backend** | REST API Laravel untuk aplikasi Android sekaligus panel admin/owner berbasis web (Blade + Tailwind) untuk mengelola destinasi, paket, booking, pembayaran, kuota, promo, pembatalan, refund, ulasan, pengguna, laporan, dan scanner tiket. |

Prinsip utama:

- **Backend adalah sumber kebenaran** untuk status pembayaran, tiket, kuota, promo, dan pembatalan.
- **Android tidak pernah membuat QR tiket sendiri.** Token tiket diterbitkan dan ditandatangani server.
- **Perubahan status booking hanya terjadi server-side**, lewat webhook pembayaran, scheduler, atau approval admin.
- **Data sensitif tidak pernah dikirim lewat push notification.** Payload FCM hanya berisi identifier.

---

## 2. Fitur Utama

### 2.1 Sisi Traveller (Android)

| Fitur | Keterangan |
|---|---|
| **Autentikasi** | Login Firebase/Google Sign-In, verifikasi OTP email, sesi token Sanctum. |
| **Profil** | Ubah nama, telepon, tanggal lahir, kota, dan foto profil; identitas pemesan disimpan untuk booking berikutnya. |
| **Eksplorasi katalog** | Pencarian dengan debounce 350 ms, filter kategori/harga/rating/durasi/tanggal, pilihan urutan, dan *infinite scroll*. Filter, urutan, dan pagination diproses backend. |
| **Favorit Saya** | Simpan destinasi/paket lewat ikon hati; tab Destinasi dan Paket; tersimpan per akun di backend. |
| **Promo dan voucher** | Validasi kode di server, perhitungan subtotal/diskon/total, pelepasan kuota saat booking dibatalkan. |
| **Booking** | Pilih tanggal dan jumlah pelancong, lihat sisa kuota, isi identitas setiap pengunjung, unggah dokumen identitas, setujui konsen privasi. |
| **Pembayaran** | Midtrans Snap di WebView, hitung mundur berbasis jam server, auto-expire booking, polling status, auto-return setelah `paid/confirmed`, dan retry sinkronisasi. |
| **E-ticket** | QR token ditandatangani server, hanya terbit setelah pembayaran terkonfirmasi dan dokumen pengunjung lengkap. |
| **Pembatalan** | Traveller mengajukan pembatalan; booking berubah hanya setelah admin menyetujui. Status dan catatan admin tampil di kartu booking. |
| **Estimasi refund** | Dialog pembatalan menampilkan persentase, nominal, tier, dan alasan bila tidak memenuhi syarat. |
| **Ulasan terverifikasi** | Hanya pengunjung yang benar-benar check-in (tiket `used`) yang boleh menilai. |
| **Notifikasi push** | FCM untuk status pembayaran, tiket discan, keputusan pembatalan, moderasi ulasan, dan pengingat perjalanan H-1. Tap notifikasi membuka layar terkait. |
| **Offline banner** | Indikator koneksi dan banner offline di seluruh aplikasi. |

### 2.2 Sisi Admin (Web)

| Fitur | Keterangan |
|---|---|
| **Dashboard** | Ringkasan booking, pembayaran, pengguna, dan aktivitas. |
| **Master data** | CRUD destinasi dan paket wisata, import/export CSV, bulk delete. |
| **Kuota per tanggal** | Kapasitas default dari `max_people`, override kapasitas, dan penutupan tanggal tertentu. |
| **Booking dan pembayaran** | Daftar, detail, ubah status booking/pembayaran, riwayat pembayaran. |
| **Permintaan pembatalan** | Antrean approval/rejection, catatan admin, estimasi refund bertingkat, auto-refund Midtrans opsional, dan retry refund. |
| **Promo dan voucher** | CRUD kode, tipe diskon, kuota, jadwal, batas paket/destinasi, riwayat pemakaian, aktif/nonaktif, audit log. |
| **Scanner tiket** | Verifikasi dan redeem QR tiket; admin bisa semua destinasi, owner hanya destinasi yang ditugaskan. |
| **Moderasi ulasan** | Sembunyikan/tampilkan ulasan dengan alasan; perubahan dicatat di audit log. |
| **Pengguna dan activity log** | Kelola pengguna dan audit trail perubahan penting. |
| **Laporan dan analitik** | Kartu ringkasan, enam chart, tabel rincian, export CSV, dan HTML siap cetak/PDF. |
| **Kelola owner** | Admin membuat/mengatur akun owner dan destinasi yang ditugaskan. |

### 2.3 Sisi Owner (Web)

| Fitur | Keterangan |
|---|---|
| **Dashboard owner** | Ringkasan untuk destinasi yang ditugaskan. |
| **Destinasi dan paket** | Hanya destinasi/paket miliknya. |
| **Kuota** | Override kapasitas dan tanggal tutup untuk paket miliknya. |
| **Booking** | Melihat booking untuk destinasi miliknya. |
| **Pembatalan** | Approve/reject/refund untuk booking di destinasi miliknya. |
| **Scanner** | Redeem tiket hanya untuk destinasi yang ditugaskan. |
| **Staf** | Kelola manager dan gate_staff untuk destinasi miliknya (tambah, ubah, nonaktifkan/aktifkan; nonaktifkan, jangan hapus). |
| **Laporan check-in** | Laporan kunjungan dan export. |
| **Profil** | Ubah profil owner. |

---

## 3. Arsitektur Sistem

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
                                    │ Midtrans Snap      │                     │ Admin / Owner      │
                                    │ + Webhook callback │                     │ Blade + Tailwind   │
                                    └────────────────────┘                     └────────────────────┘
```

### 3.1 Komponen utama

| Komponen | Peran |
|---|---|
| `AuthController` + `FirebaseTokenVerifier` | Memverifikasi Firebase ID token, membuat sesi Sanctum. |
| `BookingController` + `AvailabilityService` | Membuat booking dengan row lock, validasi kapasitas, dan kuota per tanggal. |
| `MidtransService` + `MidtransController` | Membuat Snap token, memverifikasi signature webhook, merekonsiliasi status pembayaran. |
| `BookingExpiryService` + `ExpireUnpaidBookings` | Melepas kursi dan promo untuk booking yang melewati batas bayar. |
| `TicketService` | Menerbitkan token tiket bertanda tangan, verifikasi, dan redeem sekali pakai. |
| `RefundPolicyService` + `BookingChangeService` | Menghitung refund bertingkat dan memproses approval/refund. |
| `ReviewService` | Menentukan kelayakan ulasan dan agregat rating publik. |
| `PromoService` | Validasi promo dan konsumsi kuota secara atomik. |
| `PushNotificationService` | Mengirim FCM data-only dengan payload deep link standar. |
| `ReportService` | Agregat laporan dengan `GROUP BY`, cache, dan export. |

### 3.2 Pola arsitektur

- **Service layer** memegang logika bisnis; controller tipis.
- **Database transaction + row lock** untuk booking, promo, refund, dan expiry.
- **Model constants** untuk status (`Booking`, `Payment`, `Review`, `BookingChangeRequest`, `User`).
- **Filter object** (`DestinationFilter`, `TourPackageFilter`) untuk query katalog yang konsisten.
- **Best-effort side effects**: push notification dan auto-refund tidak pernah menggagalkan alur utama.
- **DTO/Resource** mengembalikan JSON dengan amplop `{ success, message, data, meta }`.

---

## 4. Teknologi dan Versi

### 4.1 Backend

| Komponen | Versi / Keterangan |
|---|---|
| PHP | `^8.2` |
| Framework | Laravel `^12.0` |
| Autentikasi API | Laravel Sanctum `^4.0` |
| Firebase Admin | `kreait/laravel-firebase` `^6.2` |
| Pembayaran | `midtrans/midtrans-php` `^2.6` |
| Database development | SQLite (`database/database.sqlite`) |
| Database produksi | MySQL/MariaDB (didukung Eloquent) |
| Testing | PHPUnit `^11.5` |
| Formatter | Laravel Pint `^1.24` |
| Frontend admin | Blade + Tailwind CSS 4 + Vite 7 |
| Chart | Chart.js 4 |
| Scanner web | `html5-qrcode` 2.3 |

### 4.2 Android

| Komponen | Versi / Keterangan |
|---|---|
| Bahasa | Kotlin |
| UI | Jetpack Compose (Material 3) |
| SDK | `compileSdk 35`, `targetSdk 35`, `minSdk 26` |
| DI | Dagger Hilt `2.50` |
| Networking | Retrofit `2.9.0`, OkHttp `4.11.0`, Gson |
| Auth | Firebase Auth + Google Sign-In |
| Push | Firebase Cloud Messaging (BOM `32.7.1`) |
| Gambar | Coil Compose `2.5.0` |
| QR | ZXing Core `3.5.3` |
| Navigasi | Navigation Compose `2.7.6` |
| Coroutines | `1.7.3` |
| Testing | JUnit 4.13.2 |
| Package | `com.naylaaisyah.traveloapp` |

### 4.3 Design system Android

Tema **“Cute & Dreamy”** dengan palet:

| Token | Warna |
|---|---|
| `Rosewood` | `#A95372` |
| `CottonCandy` | `#E9A0B8` |
| `BlushMist` | `#F9E7ED` |
| `VanillaCream` | `#FFF9F7` |
| `PlumInk` | `#493747` |
| `SageDeep` | `#4F7D66` |
| `DangerDeep` | `#B03A4E` |

Font: **Nunito** (heading) dan **Nunito Sans** (body).

---

## 5. Struktur Folder

```text
UJI KOMPETENSI/
├── PROYEK.md                       # Dokumen lengkap ini
├── README.md                       # Ikhtisar project
├── DOKUMENTASI.md                  # Panduan operasional rinci
├── docs/                           # Laporan audit & catatan verifikasi
│
├── Travelo-Backend/                # Laravel API + panel admin/owner
│   ├── app/
│   │   ├── Console/Commands/       # Purge dokumen, reminder, expire, cek webhook
│   │   ├── Http/
│   │   │   ├── Controllers/Api/    # Endpoint JSON untuk Android
│   │   │   ├── Controllers/Admin/  # Controller panel admin
│   │   │   ├── Controllers/Owner/  # Controller portal owner
│   │   │   └── Middleware/         # Role, optional Sanctum, Firebase
│   │   ├── Jobs/                   # ProcessAutoRefund
│   │   ├── Models/                 # Eloquent model
│   │   ├── Services/               # Logika bisnis
│   │   └── Helpers/                # Status badge helper
│   ├── config/travelo.php          # Privasi, tiket, payment, refund, report
│   ├── database/
│   │   ├── migrations/             # Skema database (aditif)
│   │   ├── seeders/                # Data awal/demo
│   │   └── database.sqlite         # DB development
│   ├── resources/
│   │   ├── views/admin/            # Blade admin
│   │   ├── views/owner/            # Blade owner
│   │   └── js/                     # Chart.js & scanner
│   ├── routes/
│   │   ├── api.php                 # REST API
│   │   ├── web.php                 # Admin/owner/scanner/webhook landing
│   │   └── console.php             # Scheduler
│   └── tests/                      # Feature & unit test
│
└── Travelo-Android/
    └── app/src/
        ├── main/java/com/naylaaisyah/traveloapp/
        │   ├── data/               # API, model, repository, aturan lokal
        │   │   ├── api/            # ApiService (Retrofit)
        │   │   ├── models/         # Model data & helper
        │   │   ├── repository/     # TraveloRepository, WishlistRepository
        │   │   ├── availability/   # Aturan ketersediaan
        │   │   ├── payments/       # Countdown & outcome
        │   │   ├── promo/          # Pesan error promo
        │   │   ├── recovery/       # Aturan pemulihan dokumen
        │   │   ├── tickets/        # Riwayat & notice tiket
        │   │   └── wishlist/       # Aturan favorit
        │   ├── di/                 # Modul Hilt
        │   ├── notifications/      # FCM, parser, controller, registrar
        │   ├── ui/
        │   │   ├── navigation/     # Screen & NavGraph
        │   │   ├── screens/        # auth, home, explore, booking, payment, dll.
        │   │   ├── components/     # Design system
        │   │   └── theme/          # Warna, tipografi, shape
        │   └── util/               # Session, format, network, picker
        ├── main/res/               # Resource Android
        └── test/                   # Unit test JVM
```

---

## 6. Prasyarat

| Kebutuhan | Keterangan |
|---|---|
| **PHP 8.2+** | Ekstensi `pdo_sqlite`/`pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`. |
| **Composer 2** | Install dependency backend. |
| **Node.js + npm** | Build aset admin (Tailwind/Vite/Chart.js). |
| **Android Studio** | JDK 17 disarankan. |
| **Android SDK** | Platform 35. |
| **ngrok** (opsional) | Akses backend dari perangkat fisik dan webhook Midtrans. |
| **Firebase** | Project `travelo-818bc`, `google-services.json`, dan service account untuk push. |
| **Midtrans Sandbox** | Server key dan merchant ID untuk pembayaran. |

---

## 7. Menjalankan Backend

```bash
cd Travelo-Backend

# 1. Install dependency
composer install
npm install

# 2. Siapkan environment
copy .env.example .env        # Windows CMD
# cp .env.example .env        # PowerShell / Git Bash
php artisan key:generate

# 3. Siapkan database SQLite (lewati jika sudah ada)
type nul > database\database.sqlite      # Windows CMD
# New-Item database\database.sqlite       # PowerShell
# touch database/database.sqlite          # Git Bash / Linux / macOS

# 4. Set akun admin di .env (password minimal 12 karakter)
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

Akses:

- API: `http://127.0.0.1:8000/api`
- Panel admin: `http://127.0.0.1:8000/admin`
- Portal owner: `http://127.0.0.1:8000/owner`

### 7.1 Scheduler

Untuk production/local yang berjalan lama, daftarkan cron setiap menit:

```bash
* * * * * cd /path/ke/Travelo-Backend && php artisan schedule:run >> /dev/null 2>&1
```

Saat pengembangan, jalankan di terminal terpisah:

```bash
php artisan schedule:work
```

### 7.2 Queue worker

Diperlukan untuk auto-refund (`ProcessAutoRefund`):

```bash
php artisan queue:work
```

Tanpa queue worker, refund otomatis akan tetap berstatus `processing` sampai job dijalankan.

### 7.3 Akses dari perangkat fisik / Midtrans

```bash
ngrok http 8000
```

Set URL HTTPS ngrok di `.env` backend (`APP_URL`) dan base URL Android. Detail lengkap ada di [`DOKUMENTASI.md`](DOKUMENTASI.md).

---

## 8. Menjalankan Aplikasi Android

1. Buka folder `Travelo-Android` di Android Studio.
2. Pastikan `Travelo-Android/app/google-services.json` tersedia dan sesuai package `com.naylaaisyah.traveloapp`.
3. Atur base URL API.

   Debug build default memakai `http://10.0.2.2:8000/` (emulator). Untuk perangkat fisik, tambahkan ke `local.properties`:

   ```properties
   travelo.baseUrl=http://192.168.1.10:8000/
   # atau URL ngrok:
   # travelo.baseUrl=https://xxxx.ngrok-free.dev/
   ```

   Atau lewat command line:

   ```bash
   ./gradlew assembleDebug -Ptravelo.baseUrl=http://192.168.1.10:8000/
   ```

4. Build dan install:

   ```bash
   cd Travelo-Android
   ./gradlew assembleDebug
   ./gradlew installDebug
   ```

Release build **wajib** memakai URL `https://`:

```properties
travelo.releaseBaseUrl=https://api.example.com/
```

Build release akan gagal jika URL tidak diawali `https://`.

### 8.1 Menjalankan test Android

```bash
cd Travelo-Android
./gradlew :app:testDebugUnitTest :app:lintDebug :app:assembleDebug
```

---

## 9. Konfigurasi Penting

### 9.1 Firebase

| Variabel / File | Fungsi |
|---|---|
| `FIREBASE_PROJECT_ID` | Project ID untuk verifikasi ID token (`travelo-818bc`). |
| `FIREBASE_CREDENTIALS` | Path service account JSON (opsional; wajib untuk push dan operasi admin Firebase). |
| `app/google-services.json` | Konfigurasi Firebase di Android. |
| `storage/app/firebase-auth.json` | Lokasi default service account backend. |

Login memakai endpoint:

```http
POST /api/auth/login
Content-Type: application/json

{ "id_token": "<firebase_id_token>" }
```

### 9.2 Midtrans

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

### 9.3 Push Notification (FCM)

- Backend: isi `storage/app/firebase-auth.json` dengan service account valid, atau arahkan `FIREBASE_CREDENTIALS` ke file JSON lain.
- Android: channel `travelo_updates`, izin `POST_NOTIFICATIONS` (Android 13+), dan registrasi token lewat `POST /api/device-tokens`.
- Pesan dikirim **data-only**. Payload hanya berisi `type`, `screen`, `booking_id`/`destination_id`/`review_id` bila relevan, serta `title`/`body`.
- Tap notifikasi membuka layar tujuan: `booking_detail`, `e_ticket`, `review`, atau `destination`.
- Jika Firebase belum dikonfigurasi, pengiriman push dilewati dengan aman dan tidak menggagalkan alur bisnis.

### 9.4 Identitas dan Privasi

```env
TRAVELO_IDENTITY_DISK=local
TRAVELO_IDENTITY_MAX_KB=5120
TRAVELO_IDENTITY_RETENTION_DAYS=90
TRAVELO_PRIVACY_VERSION=2026-10-22
TRAVELO_TICKET_TIMEZONE=Asia/Jakarta
```

Dokumen identitas disimpan di disk privat (`storage/app/private`), tidak pernah di `public`, dan dibersihkan otomatis setelah masa retensi.

### 9.5 Pembayaran dan Expiry

```env
TRAVELO_PAYMENT_TTL_MINUTES=60
TRAVELO_EXPIRE_MIDTRANS=true
```

- `payment_expires_at = now + TTL` untuk setiap booking baru.
- Scheduler `travelo:expire-unpaid-bookings` berjalan tiap menit.
- Kegagalan memanggil API expire Midtrans tidak membatalkan expiry lokal.

### 9.6 Refund

```env
TRAVELO_AUTO_REFUND=false
```

| Batas (hari sebelum kunjungan) | Persentase refund |
|---|---|
| ≥ 7 hari | 100% |
| 3–6 hari | 50% |
| < 3 hari | 0% |

- Dihitung dari `total_price` (jumlah yang benar-benar dibayar, termasuk diskon promo).
- Timezone mengikuti `TRAVELO_TICKET_TIMEZONE`.
- Booking belum dibayar, tiket sudah dipakai, atau tanggal kunjungan lewat → tidak refundable.
- `TRAVELO_AUTO_REFUND=true` memproses refund via Midtrans Refund API dengan kunci idempotensi.

### 9.7 Laporan

```env
TRAVELO_REPORTS_CACHE_TTL=300
TRAVELO_REPORTS_MAX_RANGE_DAYS=366
```

### 9.8 Akun awal (seeder)

```env
TRAVELO_ADMIN_EMAIL=admin@travelo.com
TRAVELO_ADMIN_NAME="Admin Travelo"
TRAVELO_ADMIN_PASSWORD=ganti-dengan-password-kuat
TRAVELO_ADMIN_FIREBASE_UID=
```

Password admin minimal 12 karakter.

---

## 10. Role dan Hak Akses

| Role | Akses |
|---|---|
| `traveller` | API Android: booking, pembayaran, e-ticket, pembatalan, ulasan, profil. Tidak punya akses web portal. |
| `admin` | Seluruh panel admin, termasuk laporan/analitik, scanner semua destinasi, moderasi ulasan, refund, dan kelola owner. Semua permission (`*`). |
| `owner` | Portal `/owner`: scanner tiket, kunjungan, dan laporan check-in hanya untuk destinasi yang ditugaskan. Boleh kelola staf (manager, gate_staff) untuk destinasinya. |
| `manager` | Portal `/owner`: redeem tiket, edit destinasi/paket miliknya, kuota & tanggal tutup, lihat booking, approve/reject pembatalan, refund, pembayaran & laporan pendapatan, laporan check-in, dokumen identitas (dilihat saja). Tidak boleh CRUD destinasi baru/hapus, promo, atau kelola staf. |
| `gate_staff` | Portal `/owner`: HANYA scanner tiket dan riwayat scan miliknya sendiri. Tidak ada data nominal, email/telepon, atau identitas di layar scan. |

### 10.1 Middleware

| Middleware | Fungsi |
|---|---|
| `auth:sanctum` | Melindungi endpoint API traveller. |
| `optional.sanctum` | Menempelkan user bila token valid, tanpa mewajibkan token (katalog + `is_wishlisted`). |
| `auth` + `permission:<nama>` | Halaman portal admin/owner; permission adalah satu-satunya kebenaran (tidak menumpuk `role:` di route portal). |
| `auth` + `active` | Menolak akun yang dinonaktifkan (web login & token API); token Cabut saat dinonaktifkan. |
| `throttle:*` | Rate limit per endpoint sensitif. |

### 10.2 Relasi owner–destinasi

Owner, manager, dan gate_staff dihubungkan ke destinasi lewat pivot `destination_user`. Role menentukan APA yang boleh dilakukan; pivot menentukan DI MANA. Admin tidak terbatas oleh pivot. Setiap staff (owner/manager/gate_staff) wajib punya minimal satu destinasi di pivot. User dengan `is_active = false` tidak bisa login web maupun memakai token API.

### 10.3 Akun demo (seeder, hanya local/testing)

`DemoStaffSeeder` hanya jalan saat `APP_ENV` adalah `local` atau `testing`; di production dilewati dengan peringatan. Password di bawah ini **hanya untuk demo** (kurang dari 12 karakter) dan tidak boleh ada di production.

| Role | Email | Password | Masuk lewat |
|---|---|---|---|
| `admin` | admin@travelo.com | admin123 | Web `/admin` |
| `owner` | owner@travelo.com | owner123 | Web `/owner` |
| `manager` | manager@travelo.com | manager123 | Web `/owner` |
| `gate_staff` | gate@travelo.com | gate123 | Web `/owner` (hanya scanner) |
| `traveller` | traveller@travelo.com | traveller123 | Aplikasi Android (Firebase) |

Owner, manager, dan gate_staff ditugaskan ke minimal satu destinasi demo yang sama lewat pivot `destination_user`. Aturan password minimal 12 karakter tetap berlaku untuk akun admin lewat `.env` (`TRAVELO_ADMIN_PASSWORD`) dan form.

---

## 11. Ringkasan API

Semua endpoint di bawah ini berada di prefix `/api`. Endpoint bertanda 🔒 membutuhkan header:

```http
Authorization: Bearer <token>
```

Endpoint katalog publik tetap bisa diakses tanpa token. Jika token Sanctum valid dikirim, respons tiap item menambahkan `is_wishlisted`.

### 11.1 Format respons

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0
  }
}
```

### 11.2 Filter, sort, dan pagination katalog

`GET /destinations` dan `GET /tour-packages` menerima parameter opsional berikut. Nilai di luar daftar mengembalikan `422` dengan `errors` per field.

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
| `available_date` | `YYYY-MM-DD` | Hanya item dengan sisa kursi pada tanggal itu; menghormati override kapasitas, tanggal tutup, dan pembayaran `failed`/`expired`. |
| `sort` | `newest`, `price_asc`, `price_desc`, `rating_desc`, `popular` | `popular` menghitung booking `paid` dengan status `confirmed`/`completed`. |
| `page` / `per_page` | ≥ 1 / 1–50 | Default halaman 1 dan 15 baris. `per_page` > 50 ditolak. |

### 11.3 Endpoint publik

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/destinations` | Daftar destinasi (filter, urutan, pagination). |
| `GET` | `/destinations/popular` | Destinasi populer. |
| `GET` | `/destinations/{id}` | Detail destinasi. |
| `GET` | `/destinations/{id}/reviews` | Ulasan publik. |
| `GET` | `/destinations/{id}/rating-summary` | Ringkasan rating. |
| `GET` | `/tour-packages` | Daftar paket wisata. |
| `GET` | `/tour-packages/{id}` | Detail paket. |
| `GET` | `/tour-packages/destination/{destinationId}` | Paket per destinasi. |
| `GET` | `/tour-packages/{id}/availability?month=YYYY-MM&people=2` | Kuota per tanggal. |
| `POST` | `/auth/login` | Login dengan Firebase ID token. |
| `POST` | `/auth/register` | Registrasi dengan Firebase ID token. |
| `POST` | `/auth/otp/send` | Kirim OTP email. |
| `POST` | `/auth/otp/resend` | Kirim ulang OTP. |
| `POST` | `/auth/otp/verify` | Verifikasi OTP. |
| `POST` | `/midtrans/callback` | Webhook Midtrans (signature wajib). |
| `POST` | `/midtrans/notification` | Alias webhook. |
| `GET` | `/midtrans/health` | Health check konfigurasi Midtrans. |
| `POST` | `/tickets/verify` | Verifikasi token tiket. |

### 11.4 Endpoint traveller 🔒

| Method | Endpoint | Fungsi |
|---|---|---|
| `POST` | `/auth/logout` | Logout & unregister device token. |
| `GET` | `/auth/me` | Profil saat ini. |
| `PUT` | `/auth/profile` | Update profil. |
| `POST` | `/auth/profile/photo` | Upload foto profil. |
| `GET` | `/auth/identity` | Identitas pemesan tersimpan. |
| `DELETE` | `/auth/identity` | Hapus identitas tersimpan. |
| `GET` | `/my-bookings` | Daftar booking milik user. |
| `POST` | `/bookings` | Buat booking. |
| `GET` | `/bookings/{id}` | Detail booking. |
| `GET` | `/bookings/{id}/e-ticket` | Ambil e-ticket. |
| `GET` | `/bookings/{id}/change-requests` | Riwayat permintaan pembatalan. |
| `POST` | `/bookings/{id}/change-requests` | Ajukan pembatalan. |
| `GET` | `/bookings/{id}/refund-preview` | Estimasi refund sebelum pengajuan. |
| `POST` | `/device-tokens` | Daftarkan token FCM. |
| `DELETE` | `/device-tokens` | Hapus token FCM. |
| `GET` | `/wishlist?type=destination\|tour_package&page=1&per_page=15` | Favorit milik user. |
| `POST` | `/wishlist/toggle` | Tambah/hapus favorit (`item_type`, `item_id`). |
| `DELETE` | `/wishlist/{id}` | Hapus satu favorit. |
| `POST` | `/promos/validate` | Pratinjau promo. |
| `GET` | `/destinations/{id}/review-eligibility` | Cek kelayakan ulasan. |
| `POST` | `/bookings/{bookingId}/review` | Kirim ulasan. |
| `PATCH` | `/reviews/{reviewId}` | Edit ulasan. |
| `DELETE` | `/reviews/{reviewId}` | Hapus ulasan. |
| `GET` | `/reviews/mine` | Ulasan milik user. |
| `GET` | `/bookings/{id}/visitors` | Daftar pengunjung. |
| `PATCH` | `/bookings/{id}/visitors/{visitorId}` | Update pengunjung. |
| `POST` | `/bookings/{id}/visitors/{visitorId}/identity-document` | Upload dokumen identitas. |
| `DELETE` | `/bookings/{id}/visitors/{visitorId}/identity-document` | Hapus dokumen. |
| `GET` | `/bookings/{id}/visitors/{visitorId}/identity-document` | Unduh dokumen. |
| `GET` | `/bookings/{bookingId}/payment-status` | Status pembayaran + expiry. |
| `POST` | `/bookings/{bookingId}/payment/verify-and-sync` | Verifikasi & sinkronisasi. |
| `POST` | `/bookings/{bookingId}/payment/retry-sync` | Retry sinkronisasi. |
| `POST` | `/bookings/{bookingId}/payment/mark-as-paid` | Tandai lunas (fallback). |
| `POST` | `/payments/create-snap-token` | Buat Snap token. |
| `POST` | `/bookings/{bookingId}/sync-payment-status` | **Legacy** (deprecated). |
| `GET` | `/bookings/{bookingId}/legacy-payment-status` | **Legacy** (deprecated). |

### 11.5 Panel admin (web)

Prefix: `/admin`

| Area | Route utama |
|---|---|
| Dashboard | `admin.dashboard` |
| Laporan & analitik | `admin.reports.index`, `admin.reports.data`, `admin.reports.export` |
| Destinasi | `admin.destinations.*` |
| Paket wisata | `admin.tour-packages.*` |
| Kuota tanggal | `admin.tour-packages.availability` |
| Booking | `admin.bookings.index`, `admin.bookings.show` |
| Promo / voucher | `admin.promos.*` |
| Pembatalan | `admin.change-requests.index` (approve/reject/refund/retry) |
| Pembayaran | `admin.payments.index`, `admin.payments.show` |
| Pengguna | `admin.users.index` |
| Ulasan | `admin.reviews.index`, `admin.reviews.moderate` |
| Scanner | `admin.scanner`, `admin.tickets.redeem` |
| Reissue / dokumen | `admin.tickets.reissue`, `admin.visitors.document-review` |
| Activity log | `admin.activity-logs.index` |
| Import/Export | `admin.import.*`, `admin.export.*` |
| Kelola owner | `admin.owners.*` |

### 11.6 Portal owner (web)

Prefix: `/owner`

| Area | Route utama |
|---|---|
| Dashboard | `owner.dashboard` |
| Destinasi sendiri | `owner.destinations.*` |
| Paket sendiri | `owner.tour-packages.*` |
| Kuota | `owner.tour-packages.availability` |
| Booking | `owner.bookings.index`, `owner.bookings.show` |
| Pembatalan | `owner.change-requests.*` |
| Scanner | `owner.scanner`, `owner.tickets.redeem` |
| Staf | `owner.staff.*` |
| Profil | `owner.profile.*` |
| Laporan check-in | `owner.laporan.index`, `owner.laporan.export` |

---

## 12. Alur Bisnis Utama

### 12.1 Booking dan pembayaran

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

**Aturan kuota:**

- Kursi dihitung dari booking berstatus `pending`, `confirmed`, atau `completed` dengan pembayaran bukan `failed`/`expired`.
- Booking `cancelled` langsung melepas kursinya.
- Override kapasitas dan tanggal tutup dihormati.

**Batas waktu:**

- `payment_expires_at` disimpan di `bookings` dan dikirim juga oleh endpoint booking/payment-status.
- Scheduler `travelo:expire-unpaid-bookings` berjalan tiap menit dengan row lock.
- Konvensi status: booking → `cancelled`; pembayaran → `expired`.
- Webhook `paid` yang terlambat hanya menghidupkan booking bila kuota tanggal masih tersedia. Jika kursi habis, pembayaran tetap dicatat `paid` untuk refund manual, booking tetap `cancelled`, dan traveller menerima notifikasi.

### 12.2 E-ticket dan scan gerbang

1. E-ticket hanya terbit untuk booking `paid` + `confirmed`.
2. Semua dokumen pengunjung harus lengkap.
3. QR berisi token yang ditandatangani server dan memiliki masa berlaku.
4. Staff memindai QR di `/admin/scanner` atau `/owner/scanner`.
5. Token hanya dapat diredeem satu kali; scan kedua ditolak.
6. Setelah redeem, status tiket menjadi `used` dan push notification dikirim.

### 12.3 Pembatalan dan refund

1. Traveller mengirim permintaan lewat `POST /bookings/{id}/change-requests`.
2. Booking **tidak berubah** selama status masih `pending`.
3. Admin/owner menyetujui → booking menjadi `cancelled`.
4. Saat persetujuan, `RefundPolicyService` menghitung refund dari **nominal yang benar-benar dibayar** (`bookings.total_price`, sudah termasuk diskon promo), lalu snapshot-nya disimpan di `booking_change_requests`:
   - `refund_percentage` — persentase tier (100 / 50 / 0);
   - `refund_amount` — nominal refund pada saat itu (bukan dihitung ulang saat laporan).
5. Status refund mengikuti snapshot:
   - tidak eligible/0% → `refund_status = not_applicable`;
   - auto-refund aktif dan nominal > 0 → `processing`, job `ProcessAutoRefund` diantrekan;
   - manual → `pending`.
6. Refund otomatis via Midtrans mengirim **`refund_amount`**, bukan `total_price`, sehingga tier parsial (misalnya 50%) tidak berubah menjadi refund penuh. Kunci idempotensi tetap `travelo_refund_{change_request_id}`.
7. Gagal gateway → `failed` + alasan, bisa di-retry dari panel admin; booking tetap `cancelled`.
8. Jalur manual tetap tersedia: admin menandai `refunded` setelah dana dikembalikan (`refunded_at` diisi pada saat itu).
9. Semua transisi dicatat di `activity_logs` dan dikirim sebagai push notification.
10. Nominal dan persentase snapshot ditampilkan di daftar/detail pembatalan panel admin & owner, serta pada kartu booking Android (`latest_change_request.refund_percentage` dan `refund_amount`).
11. Backfill aditif untuk data lama: baris `refunded` yang `refund_amount`-nya masih `NULL` diisi dari `bookings.total_price` dengan persentase 100% (perilaku lama = refund penuh). Tidak ada baris yang dihapus atau ditimpa bila snapshot sudah ada.

### 12.4 Ulasan terverifikasi

- Hanya booking yang sudah check-in (tiket `used`) yang layak diulas.
- Destinasi diturunkan server dari booking, bukan dari input client.
- Ulasan yang disembunyikan admin tidak muncul di publik dan tidak dihitung di agregat.
- Moderasi `hide`/`restore` mengirim push `review_moderated` dan mencatat audit log.

### 12.5 Promo dan voucher

| Aturan | Perilaku |
|---|---|
| Tipe diskon | `percent` (opsional dibatasi `max_discount`) atau `fixed`. |
| Batas | `min_transaction`, `usage_limit` (null = tanpa batas), `per_user_limit`. |
| Jadwal | `starts_at` / `expires_at`; `is_active` untuk mengaktifkan. |
| Cakupan | Opsional `tour_package_id` dan/atau `destination_id`. |
| Harga | `subtotal_amount` = harga paket × jumlah orang; diskon tidak pernah melebihi subtotal; total tidak pernah negatif. |
| Pembatalan/kedaluwarsa | Redemption ditandai `released` dan `used_count` dikurangi sekali saja (idempoten). |
| Midtrans | `gross_amount` selalu memakai `total_price` final. |

### 12.6 Laporan dan analitik

Halaman `admin.reports` menampilkan kartu ringkasan, enam chart, dan tabel rincian. Filter tanggal (`start_date`, `end_date`) dan pengelompokan (`period`: `day`/`week`/`month`) dikirim ke server; default 30 hari terakhir dan rentang dibatasi 366 hari.

Definisi metrik:

- **Pendapatan**: diakui saat pembayaran diterima (`payments.paid_at`, fallback `bookings.created_at`), hanya booking `payment_status = paid`. Refund dihitung terpisah dan tidak memundurkan tanggal penjualan. **Net revenue = gross − refund**.
- **Refund**: `SUM(booking_change_requests.refund_amount)` untuk `refund_status = 'refunded'`, dikelompokkan berdasarkan `refunded_at` (tanggal dana benar-benar kembali). Karena memakai snapshot, refund parsial (tier 50%) hanya mengurangi sebesar nominal yang disetujui, bukan seluruh `total_price`. Baris lama yang belum punya snapshot sudah di-backfill oleh migrasi.
- **Top 5 paket/destinasi**: hanya booking `paid` dengan status `confirmed`/`completed`.
- **Okupansi**: kursi terisi vs kapasitas per tanggal kunjungan, memakai aturan `AvailabilityService`.
- **Tingkat pembatalan**: booking `cancelled` dibagi seluruh booking pada rentang tersebut.
- **Pengguna baru**: hanya `users.role = traveller`.
- **Rating**: hanya ulasan `published`.

Agregat memakai `GROUP BY` di database (bukan N+1) dan indeks pelaporan. Payload ringkasan di-cache selama `TRAVELO_REPORTS_CACHE_TTL` detik; export selalu membaca data terbaru. CSV berisi beberapa section; opsi **Print/PDF** menghasilkan HTML siap cetak.

---

## 13. Struktur Database

### 13.1 Tabel utama

| Tabel | Isi |
|---|---|
| `users` | Akun traveller/admin/owner/manager/gate_staff, profil, role, `is_active`, `created_by`, Firebase UID. |
| `destinations` | Destinasi wisata, termasuk `category`. |
| `tour_packages` | Paket wisata dan kapasitas default (`max_people`). |
| `bookings` | Booking, status, tanggal, jumlah orang, total harga, expiry. |
| `booking_visitors` | Identitas tiap pengunjung + status dokumen. |
| `payments` | Transaksi Midtrans dan status pembayaran. |
| `tour_package_date_availabilities` | Override kapasitas / tanggal tutup. |
| `booking_change_requests` | Permintaan pembatalan dan status refund. |
| `reviews` | Ulasan kunjungan terverifikasi + moderasi. |
| `saved_identities` | Identitas pemesan untuk booking berikutnya. |
| `device_tokens` | Token FCM per perangkat. |
| `login_otps` | Kode OTP email (hash, kedaluwarsa, percobaan). |
| `activity_logs` | Audit trail aksi penting. |
| `wishlists` | Favorit destinasi/paket per user. |
| `promo_codes` | Kode promo dan aturannya. |
| `promo_redemptions` | Riwayat pemakaian promo. |
| `destination_user` | Pivot penugasan owner ke destinasi. |
| `personal_access_tokens` | Token Sanctum. |
| `cache`, `jobs` | Cache dan antrean Laravel. |

### 13.2 Status penting

| Entitas | Status |
|---|---|
| Booking | `pending`, `confirmed`, `completed`, `cancelled` |
| Pembayaran | `pending`, `paid`, `failed`, `expired` |
| Tiket | `unused`, `used`, `expired` |
| Ulasan | `published`, `hidden` |
| Change request | `pending`, `approved`, `rejected`, `cancelled` |
| Refund | `not_applicable`, `pending`, `processing`, `refunded`, `failed` |
| Role | `traveller`, `admin`, `owner`, `manager`, `gate_staff` |

### 13.3 Catatan migrasi

- Semua migrasi bersifat **aditif**; tidak ada reset atau operasi destruktif yang diperlukan.
- Migrasi terbaru mencakup refund, owner pivot, kategori destinasi, promo, expiry, dan indeks pelaporan. Tabel `users` ditambah kolom `is_active` (boolean, default true) dan `created_by` (nullable FK ke `users`) untuk jejak audit staf; tabel `bookings` ditambah `ticket_used_by` (nullable FK ke `users`) untuk mencatat siapa yang memindai tiket.
- Indeks pelaporan: `bookings.payment_status+created_at`, `bookings.booking_status+created_at`, `bookings.tour_package_id+booking_date`, `payments.paid_at`, `booking_change_requests.refund_status+refunded_at`, `users.role+created_at`.

---

## 14. Notifikasi Push dan Deep Link

### 14.1 Format payload

Pesan dikirim **data-only** (tanpa blok `notification`) agar Android selalu membangun notifikasi dan `PendingIntent` sendiri.

Contoh payload:

```json
{
  "type": "payment_paid",
  "screen": "e_ticket",
  "booking_id": "42",
  "title": "Payment confirmed",
  "body": "Your payment for Bali Escape is confirmed. Your e-ticket is ready."
}
```

### 14.2 Pemetaan tipe notifikasi

| `type` | `screen` | ID | Tujuan |
|---|---|---|---|
| `payment_paid` | `e_ticket` | `booking_id` | E-ticket |
| `payment_failed` | `booking_detail` | `booking_id` | Detail booking |
| `payment_expired` | `booking_detail` | `booking_id` | Detail booking |
| `payment_after_expiry` | `booking_detail` | `booking_id` | Detail booking |
| `payment_update` | `booking_detail` | `booking_id` | Detail booking |
| `ticket_used` | `e_ticket` | `booking_id` | E-ticket |
| `trip_reminder` | `e_ticket` | `booking_id` | E-ticket |
| `change_request_approved` | `booking_detail` | `booking_id`, `change_request_id` | Detail booking |
| `change_request_rejected` | `booking_detail` | `booking_id`, `change_request_id` | Detail booking |
| `change_request_pending` | `booking_detail` | `booking_id`, `change_request_id` | Detail booking |
| `change_request_updated` | `booking_detail` | `booking_id`, `change_request_id` | Detail booking |
| `review_moderated` | `review` | `destination_id`, `review_id` | Destinasi + bagian ulasan |

### 14.3 Aturan keamanan payload

Payload **tidak pernah** memuat:

- token tiket (`ticket_token`),
- dokumen identitas atau path-nya,
- nomor identitas,
- email/telepon,
- nominal pembayaran,
- daftar pengunjung.

Data sensitif hanya bisa diambil lewat API yang memeriksa kepemilikan.

### 14.4 Alur deep link Android

```text
FCM data-only
   → TraveloMessagingService (foreground/background/cold start)
   → TraveloNotifications membuat notifikasi + PendingIntent ke MainActivity
   → extras: type, screen, booking_id / destination_id / review_id
   → tap:
       cold start  → MainActivity.onCreate → DeepLinkController
       warm start  → MainActivity.onNewIntent (singleTop) → DeepLinkController
   → DeepLinkParser memetakan extras ke DeepLinkTarget
   → NavGraph navigasi dengan popUpTo(Home)
```

### 14.5 Perilaku penting

| Skenario | Perilaku |
|---|---|
| **Cold start** | Target disimpan di `DeepLinkController` sampai NavHost siap, lalu dijalankan sekali. |
| **Warm start** | `MainActivity` `singleTop` menerima `onNewIntent`; tidak membuat stack/activity baru. |
| **Belum login** | Target disimpan, login ditampilkan, lalu target dilanjutkan setelah login sukses. |
| **Booking 403/404** | Pesan ramah ditampilkan dan pengguna diarahkan ke **Booking Saya**. |
| **Back dari target** | Kembali ke **Home**, bukan menumpuk ulang stack. |
| **Extras tidak lengkap/unknown** | `DeepLinkParser` mengembalikan `null`; aplikasi tidak navigasi ke id `0`. |
| **Recreation/config change** | Target yang sudah dijalankan tidak diputar ulang. |

### 14.6 Parser dan target

`DeepLinkParser` adalah Kotlin murni (tanpa Android) sehingga bisa diuji unit. Target yang dihasilkan:

- `DeepLinkTarget.BookingDetail(bookingId)`
- `DeepLinkTarget.ETicket(bookingId)`
- `DeepLinkTarget.Review(destinationId, reviewId?)`
- `DeepLinkTarget.Destination(destinationId)`

Parser menerima `screen` eksplisit sebagai prioritas (agar tipe baru bisa deep-link tanpa update aplikasi), lalu jatuh ke pemetaan `type`. Nilai `type`/`screen` tidak peka huruf besar-kecil, dan id harus bilangan positif.

### 14.7 Route Android

| Route | Layar |
|---|---|
| `login`, `register` | Autentikasi |
| `home` | Home |
| `explore` | Eksplorasi |
| `destination/{destinationId}` | Detail destinasi |
| `tour_package/{tourPackageId}` | Detail paket |
| `booking/{tourPackageId}/{title}/{price}/{maxPeople}` | Form booking |
| `booking_detail/{bookingId}` | Detail booking (deep link) |
| `e_ticket/{bookingId}` | E-ticket (deep link) |
| `review/{destinationId}?reviewId={reviewId}` | Ulasan destinasi (deep link) |
| `my_bookings?notice={notice}` | Booking Saya + pesan fallback |
| `favorites` | Favorit |
| `ticket_history` | Riwayat tiket |
| `complete_documents/{bookingId}` | Lengkapi dokumen |
| `profile` | Profil |
| `payment/{bookingId}` | Pembayaran |
| `payment_result/{bookingId}` | Hasil pembayaran |

---

## 15. Keamanan dan Privasi

### 15.1 Autentikasi dan otorisasi

- Login Android memakai **Firebase ID token** yang diverifikasi backend (`FirebaseTokenVerifier`), lalu diterbitkan token **Sanctum**.
- Endpoint traveller memakai `auth:sanctum`.
- Panel admin memakai `auth` + `role:admin`.
- Scanner memakai `auth` + `role:admin,owner`.
- Portal owner memakai `role:owner,admin` dan query dibatasi ke pivot `destination_user`.
- Kepemilikan booking/ulasan/tiket selalu dicek server-side (403/404).

### 15.2 Tiket

- Token tiket ditandatangani server dan memiliki masa berlaku.
- Token hanya dapat diredeem satu kali.
- Aplikasi tidak pernah membuat QR sendiri.
- Reissue tiket tercatat di audit log.

### 15.3 Identitas pengunjung

- Dokumen disimpan di disk privat (`TRAVELO_IDENTITY_DISK=local`).
- Validasi berbasis konten file, bukan ekstensi/nama.
- Ukuran maksimum `TRAVELO_IDENTITY_MAX_KB`.
- Retensi `TRAVELO_IDENTITY_RETENTION_DAYS`; command harian menghapus dokumen setelah tanggal kunjungan + retensi.
- Nomor identitas dimasking di respons.
- Endpoint unduh dokumen memeriksa kepemilikan/role.

### 15.4 Pembayaran

- Server key Midtrans hanya di backend.
- Webhook memverifikasi signature.
- `gross_amount` memakai total final setelah promo.
- Rekonsiliasi memakai row lock agar webhook dan expiry tidak saling menimpa.
- Auto-refund memakai idempotency key.

### 15.5 Promo

- Validasi dan perhitungan di server.
- Kuota dikonsumsi secara atomik (`UPDATE ... WHERE used_count < usage_limit`).
- Pelepasan kuota idempoten.

### 15.6 Rate limiting

Endpoint sensitif memakai `throttle`, antara lain:

| Endpoint | Limit |
|---|---|
| OTP send/resend | 10/menit per IP |
| OTP verify | 30/menit |
| Upload foto/dokumen | 10/menit |
| Booking | default |
| Change request | 10/menit |
| Wishlist toggle | 30/menit |
| Device token | 20/menit |
| Ulasan | 10–60/menit |

### 15.7 Path traversal

Route `/storage/{path}` me-resolve path dan memastikan file tetap di dalam `storage/app/public`, sehingga `../` tidak bisa mencapai dokumen privat atau `.env`.

---

## 16. Testing dan Kualitas Kode

### 16.1 Backend

```bash
cd Travelo-Backend
php artisan test
```

Hasil verifikasi terakhir (2026-10-26):

- **267 test lulus**, **1.634 assertions**.
- Cakupan: filter/sort/pagination katalog, availability/kuota, pembatalan, kebijakan refund bertingkat + preview, push notification, pembayaran, batas waktu & auto-expire, lifecycle tiket, identitas pengunjung, ulasan, wishlist, promo/voucher, laporan/analitik, keamanan, OTP, dan profil.

Test penting:

| Test | Fokus |
|---|---|
| `RefundPolicyTest` | Semua tier, batas H-7/H-3/hari H, timezone, unpaid, used ticket, promo, ownership preview. |
| `PushNotificationTest` | Payload data-only setiap tipe, `type`/`screen`/id benar, tidak ada field sensitif, tidak ada blok `notification`. |
| `ReportServiceTest` | Pendapatan, refund, top paket, okupansi, pembatalan, pengguna baru, rating, cache, export. |
| `SecurityFlowTest` | Otorisasi dan akses terlarang. |
| `TicketLifecycleTest` | Penerbitan, verifikasi, redeem sekali pakai. |
| `PromoCodeTest` | Validasi, kuota, batas per user, pelepasan. |
| `OwnerAccessTest` | Batas akses owner ke destinasi sendiri. |
| `ManagerGateStaffAccessTest` | Matriks akses manager & gate_staff, isolasi destinasi, kelola staf oleh owner, akun nonaktif, payload scan gate_staff, dan seeder demo. |

### 16.2 Android

```bash
cd Travelo-Android
./gradlew :app:testDebugUnitTest :app:lintDebug :app:assembleDebug
```

Hasil verifikasi terakhir:

- `testDebugUnitTest`: **152 test, 0 gagal**.
- `lintDebug`: **0 error**, 31 warning (mayoritas warning lama/deprecation).
- `assembleDebug`: APK berhasil dibuat.

Test penting:

| Test | Fokus |
|---|---|
| `DeepLinkParserTest` | Semua tipe notifikasi, screen override, extras tidak lengkap, id malformed, tipe unknown. |
| `RefundPreviewTest` | Pemetaan tier dan format nominal. |
| `PaymentCountdownTest` | Sisa waktu dan format countdown. |
| `AvailabilityRulesTest` | Aturan ketersediaan. |
| `BookingChangeEligibilityTest` | Kelayakan pembatalan. |
| `CatalogQueryTest` | Query katalog. |
| `WishlistRulesTest` | Aturan favorit. |
| `TicketUsageNoticeRulesTest` | Notice tiket. |
| `DocumentRecoveryRulesTest` | Pemulihan upload dokumen. |
| `FormattersTest` | Format tanggal/rupiah. |

### 16.3 Format kode PHP

```bash
cd Travelo-Backend
./vendor/bin/pint
./vendor/bin/pint --test
```

### 16.4 Build aset admin

```bash
cd Travelo-Backend
npm run build
```

---

## 17. Scheduler dan Command

| Waktu | Command | Fungsi |
|---|---|---|
| Setiap menit | `travelo:expire-unpaid-bookings` | Batalkan booking `pending` yang melewati `payment_expires_at`, lepas kuota + promo, catat log, kirim push. Idempoten dan tidak menimpa booking `paid`. |
| 02:00 | `travelo:purge-identity-documents` | Hapus dokumen identitas setelah tanggal kunjungan + retensi. |
| 09:00 | `travelo:send-trip-reminders` | Kirim pengingat H-1 untuk trip `paid` + `confirmed` + tiket `unused`. Idempoten per hari. |

Command lain:

| Command | Fungsi |
|---|---|
| `midtrans:check-webhook` | Cek status konfigurasi/webhook Midtrans dan transaksi terbaru. |
| `php artisan queue:work` | Menjalankan job, termasuk `ProcessAutoRefund`. |

---

## 18. Troubleshooting

### 18.1 Android tidak bisa konek ke backend

| Gejala | Solusi |
|---|---|
| Emulator gagal konek | Gunakan `http://10.0.2.2:8000/` (default debug). |
| Perangkat fisik gagal | Gunakan IP LAN komputer, bukan `localhost`; pastikan firewall mengizinkan port 8000. |
| Ngrok warning page | Tambahkan header `ngrok-skip-browser-warning: true` (sudah dilakukan untuk API dan Coil). |
| Release build error | `travelo.releaseBaseUrl` wajib `https://`. |

### 18.2 Login gagal

| Gejala | Solusi |
|---|---|
| `401` dari `/auth/login` | Pastikan Firebase ID token valid dan `FIREBASE_PROJECT_ID` benar. |
| Google Sign-In gagal | Periksa `google-services.json`, SHA-1 keystore, dan package `com.naylaaisyah.traveloapp`. |
| OTP tidak masuk | Cek konfigurasi `MAIL_*`; pada development `MAIL_MAILER=log` sehingga kode tertulis di log. |

### 18.3 Pembayaran

| Gejala | Solusi |
|---|---|
| Snap tidak muncul | Cek `MIDTRANS_SERVER_KEY`, `MIDTRANS_MERCHANT_ID`, dan `MIDTRANS_IS_PRODUCTION`. |
| Status tidak berubah | Pastikan webhook Midtrans mengarah ke `/api/midtrans/callback` dan dapat diakses publik (ngrok). |
| Booking kedaluwarsa padahal sudah bayar | Cek log webhook; webhook `paid` yang datang setelah expiry hanya menghidupkan booking bila kursi masih tersedia. |
| Auto-refund tidak jalan | Pastikan `TRAVELO_AUTO_REFUND=true`, queue worker berjalan, dan server key valid. |

### 18.4 Push notification

| Gejala | Solusi |
|---|---|
| Push tidak diterima | Isi service account Firebase dengan `private_key` valid; tanpa itu pengiriman dilewati. |
| Token tidak terdaftar | Pastikan user login dan `POST /api/device-tokens` berhasil. |
| Notifikasi tampil tanpa deep link | Pastikan backend mengirim **data-only** (tanpa blok `notification`) dan aplikasi memakai versi terbaru. |
| Android 13+ tidak menampilkan | Berikan izin `POST_NOTIFICATIONS`. |

### 18.5 Database dan storage

| Gejala | Solusi |
|---|---|
| `database does not exist` | Buat `database/database.sqlite` lalu `php artisan migrate`. |
| Gambar/foto tidak tampil | Jalankan `php artisan storage:link`. |
| Dokumen identitas 404 | Dokumen mungkin sudah dihapus oleh retensi; cek log command purge. |

### 18.6 Laporan

| Gejala | Solusi |
|---|---|
| Angka laporan berbeda dengan dashboard | Keduanya memakai `ReportService` yang sama; pastikan filter tanggal/period sama. |
| Export lambat | Rentang dibatasi `TRAVELO_REPORTS_MAX_RANGE_DAYS` (366 hari). |
| Chart kosong | Jalankan `npm run build`; Chart.js dibundel lewat Vite, bukan CDN. |

---

## 19. Batasan dan Rencana Pengembangan

### 19.1 Batasan saat ini

- **Push notification nyata** belum terverifikasi karena service account Firebase di lingkungan pengembangan masih placeholder (`private_key` kosong). Pengiriman dilewati dengan aman.
- **Auto-refund nyata** lewat Midtrans sandbox belum diuji end-to-end karena membutuhkan server key valid dan transaksi berbayar. Logika job dan nominal yang dikirim sudah diuji dengan mock `MidtransService`.
- **Verifikasi perangkat/emulator** untuk alur pembayaran, scan tiket, dan tap notifikasi belum dilakukan pada pass terakhir karena `adb`/emulator tidak tersedia.
- **Nilai refund laporan** memakai snapshot `booking_change_requests.refund_amount` (mendukung tier 100%/50%/0%). Baris `refunded` lama yang belum memiliki snapshot di-backfill dari `bookings.total_price` dengan asumsi refund penuh 100%.
- **Export PDF laporan** berupa HTML siap cetak (Print → Save as PDF), belum file PDF biner.
- **Release build Android** masih memakai URL ngrok bawaan dan `isMinifyEnabled = false`; konfigurasi produksi perlu disesuaikan.
- **Reschedule/ubah tanggal tidak didukung**; pembatalan adalah satu-satunya perubahan booking.
- **Daftar Favorit Saya** memuat maksimal 50 item per tab dan belum punya pagination bertahap.
- **Queue worker produksi** untuk auto-refund belum dikonfigurasi.

### 19.2 Rekomendasi selanjutnya

1. Isi service account Firebase asli dan uji push cold/warm start di perangkat.
2. Aktifkan queue worker jika ingin auto-refund produksi.
3. Uji auto-refund dan retry refund dengan Midtrans sandbox.
4. Siapkan konfigurasi release Android (HTTPS, minify, signing).
5. Tambahkan pagination bertahap untuk Favorit Saya.
6. Tambahkan rekonsiliasi otomatis status refund dari webhook/status Midtrans agar `refunded` tidak perlu ditandai manual setelah auto-refund.
7. Commit perubahan yang masih berada di working tree setelah review final.

---

## 20. Dokumen Terkait

| Dokumen | Isi |
|---|---|
| [`README.md`](README.md) | Ikhtisar project, fitur, arsitektur, API, testing. |
| [`DOKUMENTASI.md`](DOKUMENTASI.md) | Panduan lengkap: ngrok, Google Sign-In, Midtrans, troubleshooting, brand, verifikasi. |
| [`docs/privacy-identitas-pengunjung.md`](docs/privacy-identitas-pengunjung.md) | Kebijakan dan teknis perlindungan identitas pengunjung. |
| [`docs/final-verification-2026-10-20.md`](docs/final-verification-2026-10-20.md) | Laporan verifikasi final. |
| [`docs/laporan-audit-2026-10-21.md`](docs/laporan-audit-2026-10-21.md) | Laporan audit keamanan dan kualitas. |
| [`docs/identitas-dan-tiket-2026-10-21.md`](docs/identitas-dan-tiket-2026-10-21.md) | Catatan implementasi identitas dan tiket. |
| [`docs/laporan-scanner-tiket-2026-10-21.md`](docs/laporan-scanner-tiket-2026-10-21.md) | Catatan implementasi scanner tiket. |
| [`docs/laporan-auto-return-payment-2026-10-22.md`](docs/laporan-auto-return-payment-2026-10-22.md) | Catatan auto-return pembayaran. |
| [`docs/laporan-pengembangan-profil-2026-10-22.md`](docs/laporan-pengembangan-profil-2026-10-22.md) | Catatan pengembangan profil. |
| [`docs/laporan-fitur-baru-2026-10-26.md`](docs/laporan-fitur-baru-2026-10-26.md) | Laporan refund bertingkat dan auto-refund. |

---

## 21. Status Proyek

| Aspek | Status |
|---|---|
| Backend API | Selesai dan teruji. |
| Panel admin | Selesai dan teruji. |
| Portal owner | Selesai dan teruji. |
| Aplikasi Android | Selesai dan teruji unit test. |
| Push + deep link | Terimplementasi dan teruji parser/payload; verifikasi perangkat tertunda. |
| Refund bertingkat | Terimplementasi dan teruji; auto-refund nyata tertunda. |
| Dokumentasi | Lengkap (README, DOKUMENTASI, PROYEK, docs/). |
| Git | Perubahan masih di working tree; belum di-commit. |

> **Catatan:** Proyek berada dalam tahap pengembangan aktif. Tidak ada commit, push, reset, atau operasi database destruktif yang dilakukan pada pass terakhir.
