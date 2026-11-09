# Travelo — Dokumentasi Gabungan

Satu file pengganti semua `.md` yang sebelumnya tersebar di project.
File-file lama yang redundan / histori fix sudah dihapus.

Terakhir diperbarui: 2026-10-26

---

## Daftar Isi
1. [Jalankan Backend + ngrok](#1-jalankan-backend--ngrok)
2. [Google Sign-In (Android + Firebase)](#2-google-sign-in-android--firebase)
3. [Midtrans Payment (Sandbox)](#3-midtrans-payment-sandbox)
4. [Endpoint Payment Penting](#4-endpoint-payment-penting)
5. [Troubleshooting](#5-troubleshooting)
6. [Logo & Brand](#6-logo--brand)
7. [Redesign "Cute & Dreamy" (Android + Admin)](#7-redesign-cute--dreamy-android--admin)
8. [Verifikasi, Keamanan, Batasan, dan Tindak Lanjut](#8-verifikasi-keamanan-batasan-dan-tindak-lanjut)
9. [Identitas Pengunjung & Lifecycle Tiket](#9-identitas-pengunjung--lifecycle-tiket)
10. [Ulasan Kunjungan Terverifikasi](#10-ulasan-kunjungan-terverifikasi)
11. [Foto Profil](#11-foto-profil)
12. [Kuota per Tanggal, Pembatalan Booking, dan Push](#12-kuota-per-tanggal-pembatalan-booking-dan-push)
13. [Wishlist / Favorit Saya](#13-wishlist--favorit-saya)
14. [Filter, Sort, dan Pagination Katalog](#14-filter-sort-dan-pagination-katalog)
15. [Promo dan Voucher](#15-promo-dan-voucher)
16. [Batas Waktu Pembayaran dan Auto-Expire](#16-batas-waktu-pembayaran-dan-auto-expire)
17. [Laporan dan Analitik Admin](#17-laporan-dan-analitik-admin)

---

## 1. Jalankan Backend + ngrok

Setup authtoken cukup 1x per laptop:

```cmd
ngrok config add-authtoken PASTE_AUTHTOKEN_DISINI
ngrok config check
```

Ambil authtoken dari: https://dashboard.ngrok.com/get-started/your-authtoken

Jalankan tiap kali ngoding (2 terminal):

**Terminal 1 — Laravel:**
```cmd
cd "C:\Users\Administrator\Documents\UJI KOMPETENSI\Travelo-Backend"
php artisan serve
```

**Terminal 2 — ngrok:**
```cmd
ngrok http 8000
```

Copy URL https dari ngrok (contoh: `https://xxxx.ngrok-free.dev`) lalu pakai sebagai base URL di Android (`NetworkModule` / `Constants`) dan di `.env` backend:

```env
APP_URL=https://xxxx.ngrok-free.dev
FRONTEND_URL=https://xxxx.ngrok-free.dev
TRUSTED_PROXIES="*"
```

Setelah ganti URL: `php artisan config:clear && php artisan cache:clear`

Catatan:
- URL ngrok free berubah setiap restart. Update di 3 tempat: `.env` backend, base URL Android, webhook Midtrans dashboard.
- Cek traffic webhook di: http://127.0.0.1:4040 (ngrok inspector).
- Kalau port 8000 kepakai: `php artisan serve --port=8001` + `ngrok http 8001`.

---

## 2. Google Sign-In (Android + Firebase)

Config yang benar:
- Firebase project: `travelo-818bc`
- Android package: `com.naylaaisyah.traveloapp`
- SHA-1 debug: `3C:FF:24:8C:FA:E5:E7:34:82:04:52:42:80:66:B0:07:82:AF:71:92`
- Backend endpoint: `POST /api/auth/login` dengan body `{"id_token": "<firebase_id_token>"}`

Checklist kalau login gagal:
1. Google Cloud Console → APIs & Services → Credentials → pastikan ada Android OAuth Client dengan package + SHA-1 di atas. Kalau belum ada, buat baru.
2. Firebase Console → Authentication → Sign-in method → Google harus ENABLED.
3. Firebase Console → Project Settings → Android app → SHA fingerprint harus terdaftar. Kalau habis ganti laptop, tambahkan lagi lalu download ulang `google-services.json` ke `Travelo-Android/app/`.
4. Pastikan ngrok masih jalan dan HP bisa akses URL-nya (test dengan curl).
5. Tambahkan header `ngrok-skip-browser-warning: true` di OkHttp (sudah ada di `NetworkModule.kt`).
6. Error `com.transsion.*` abaikan saja — itu bawaan HP Transsion, bukan error login.
7. Rebuild: `cd Travelo-Android && ./gradlew clean assembleDebug`

Test cepat backend:
```bash
curl -X POST https://xxxx.ngrok-free.dev/api/auth/login \
  -H "Content-Type: application/json" \
  -H "ngrok-skip-browser-warning: true" \
  -d '{"id_token":"test-token"}'
```
Hasil wajar untuk token palsu: `{"success":false,"message":"Invalid token format"}` — artinya koneksi OK.

---

## 3. Midtrans Payment (Sandbox)

`.env` backend:
```env
MIDTRANS_SERVER_KEY=isi-dari-dashboard-sandbox
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_MERCHANT_ID=isi-dari-dashboard
```

Jangan copy-paste server key dari dokumentasi lama — ambil dari https://dashboard.midtrans.com → Settings → Access Keys (mode Sandbox).

Alur:
```
Android POST /api/bookings → backend bikin booking + snap_token
Android buka Snap (WebView) dengan snap_token → user bayar
Selama Snap terbuka, app poll GET /payment-status tiap 5 detik
Begitu backend bilang paid, WebView menutup sendiri → layar "Confirming your payment"
Midtrans kirim webhook → POST /api/midtrans/callback → DB jadi paid/confirmed
Setelah kembali, app tetap panggil verify-and-sync (jangan hanya andalkan webhook)
```

Auto-return hanya dipicu oleh status **paid/confirmed dari backend**, bukan dari
URL Snap. Jadi user tidak perlu menekan tombol Close setelah pembayaran
selesai; halaman Menunggu proses yang muncul adalah layar konfirmasi milik app.

Webhook di dashboard Midtrans → Settings → Configuration:
```
Payment Notification URL: https://xxxx.ngrok-free.dev/api/midtrans/callback
```

Test kartu sandbox: `4811 1111 1111 1114`, exp `12/2030`, CVV `123`. Atau pakai menu Simulation di dashboard Midtrans.

Aturan penting di Android: **selama Snap terbuka, poll status backend tiap 5 detik**. Kalau backend sudah `paid/confirmed`, WebView menutup sendiri dan app menampilkan layar konfirmasi. Setelah Snap ditutup, app tetap memanggil `verify-and-sync`; kalau masih pending, status di-poll terbatas lalu result screen menampilkan opsi cek ulang.

Implementasi auto-return (22/10/2026) hanya memakai status backend, bukan URL
Snap. Verifikasi yang dilakukan adalah unit test mapping status, kompilasi,
lint, dan build APK; alur pembayaran nyata di emulator/perangkat belum diuji
ulang pada pass ini karena membutuhkan akun dan transaksi Midtrans.

### 3.1 Batas waktu pembayaran & auto-expire

Setiap booking baru mendapat `payment_expires_at = now + TRAVELO_PAYMENT_TTL_MINUTES`
(default 60 menit). Respons `POST /api/bookings`, `GET /api/bookings/{id}`, dan
`GET /api/bookings/{id}/payment-status` menyertakan:

- `payment_expires_at` — deadline absolut (ISO-8601 UTC, akhiran `Z`),
- `payment_expires_in_seconds` — sisa detik dihitung jam server,
- `payment_expired` — `true` bila deadline sudah lewat (dan pembayaran bukan `paid`),
- `server_time` — jam server sebagai pembanding.

Android memakai `payment_expires_in_seconds` sebagai anchor dan hanya mengurangi
waktu monotonik perangkat; jam dinding perangkat tidak dipercaya. Saat mencapai
`00:00`, layar pembayaran dan kartu booking menampilkan **Kedaluwarsa**,
menonaktifkan pembayaran, dan menawarkan **Pesan ulang** ke detail paket.

Konfigurasi `.env` backend:

```env
TRAVELO_PAYMENT_TTL_MINUTES=60
TRAVELO_EXPIRE_MIDTRANS=true
```

Arti status saat kedaluwarsa:

- `bookings.booking_status` → `cancelled` (konvensi yang sama dengan webhook
  Midtrans `expire`),
- `bookings.payment_status` dan `payments.payment_status` → `expired`,
- kuota dan redemption promo dilepas otomatis (observer booking + pengecekan
  `AvailabilityService`).

Scheduler:

```bash
# cron produksi, setiap menit
* * * * * cd /path/ke/Travelo-Backend && php artisan schedule:run >> /dev/null 2>&1

# alternatif saat development
php artisan schedule:work

# menjalankan manual satu kali
php artisan travelo:expire-unpaid-bookings
```

`travelo:expire-unpaid-bookings` terdaftar di `routes/console.php` dengan
`everyMinute()->withoutOverlapping()`. Setiap booking diproses dalam transaksi
dengan `lockForUpdate()` dan pengecekan status terbaru, sehingga:

- booking yang sudah `paid` tidak pernah dibatalkan walau baris booking sempat
  terlihat `pending` (webhook menang),
- dua kali eksekusi tidak menghasilkan efek ganda (idempoten),
- kegagalan API expire Midtrans hanya dicatat di log dan tidak membatalkan
  pelepasan kuota lokal.

Webhook `paid` yang datang setelah expiry tidak serta-merta menghidupkan tiket:
kapasitas tanggal dicek ulang. Bila masih ada kursi, booking dihidupkan kembali
(`confirmed`/`paid`) dan promo yang sempat dilepas dipulihkan. Bila kursi sudah
habis, pembayaran tetap dicatat `paid` untuk refund manual, booking tetap
`cancelled`, activity log `Late payment requires refund` ditulis, dan traveller
menerima push. Keputusan ini juga dicatat di `storage/logs/laravel.log`.

---

## 4. Endpoint Payment Penting

Semua butuh header `Authorization: Bearer <token>`.

| Endpoint | Fungsi | Kapan dipanggil |
|---|---|---|
| `GET /api/bookings/{id}/payment-status` | Cek status di DB | Buka detail booking / polling |
| `POST /api/bookings/{id}/payment/verify-and-sync` | Tanya Midtrans langsung + update DB | **Wajib setelah bayar** |
| `POST /api/bookings/{id}/payment/retry-sync` | Coba sync 3x | Kalau verify macet |
| `POST /api/bookings/{id}/payment/mark-as-paid` | Override manual | Darurat saja |
| `POST /api/midtrans/callback` | Webhook Midtrans | Otomatis, jangan dipanggil manual |
| `POST /api/payments/create-snap-token` | Bikin ulang snap token | Kalau token expired |

Contoh:
```bash
curl -X GET "http://localhost:8000/api/bookings/1/payment-status" \
  -H "Authorization: Bearer TOKEN"

curl -X POST "http://localhost:8000/api/bookings/1/payment/verify-and-sync" \
  -H "Authorization: Bearer TOKEN"
```

Status: `pending` → nunggu bayar, `paid` → booking `confirmed`, `failed`/`expired`
→ booking `cancelled`. `expired` juga dipakai untuk booking yang melewati
`payment_expires_at` (lihat bagian 3.1).

DB: tabel `payments` punya kolom `midtrans_order_id` + `paid_at`. Migrasi sudah di-apply (`php artisan migrate`).

---

## 5. Troubleshooting

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| Payment stuck `pending` padahal sudah bayar | App tidak panggil verify, webhook tidak masuk ngrok | Panggil `verify-and-sync`, cek ngrok inspector, pastikan webhook URL di Midtrans = URL ngrok aktif |
| `500` di `/api/destinations/popular` | Dulu kolom `is_popular` tidak ada di DB | Sudah difix: pakai `latest()->take(6)`. Kalau mau fitur popular, bikin migrasi `is_popular` |
| API balas HTML bukan JSON | Error Laravel / route cache | Cek `storage/logs/laravel.log`, `php artisan optimize:clear` |
| `401 Unauthorized` | Token salah format | Harus `Bearer <token>` |
| `404 create-snap-token` | Route cache basi | `php artisan route:clear` |
| ngrok `authentication failed` | Authtoken belum diset | `ngrok config add-authtoken ...` |
| ngrok `not recognized` | Belum install / PATH | Pakai full path `C:\ngrok\ngrok.exe http 8000` atau `npx ngrok http 8000` |
| Build Android KAPT error | Cache Gradle rusak | Android Studio → Invalidate Caches, atau hapus `.gradle` lalu rebuild |

Debug cepat:
```bash
cd Travelo-Backend
type storage\logs\laravel.log
php artisan tinker
>>> Booking::with('payment')->latest()->take(5)->get()
```

---

## 6. Logo & Brand

Sumber logo final dari pemilik project: `travelo-logo-source.jpeg`
(`Logo-Travelo.jpeg`). Latar putih di sekitar logo sudah dihapus sehingga
menjadi PNG transparan. Aset di `Travelo-Backend/public/images/logos/`:
- `travelo-logo-primary.png` — logo 512×512 transparan untuk admin header/login
- `travelo-favicon.png` — favicon transparan
- `travelo-logo-192.png` — ikon Apple touch / preview
- `travelo-logo-primary.svg`, `travelo-favicon.svg` — aset vektor lama, tidak dipakai

Aset Android memakai PNG transparan yang sama:
`res/drawable-nodpi/travelo_logo.png`, foreground adaptive icon
`travelo_launcher_foreground.png`, dan ikon launcher adaptif di
`res/mipmap-anydpi-v26/` dengan background pink agar tidak ada area putih.
File `travelo-logo-journey.svg`, `discover.svg`, dan `mono.svg` adalah konsep
lama dan tidak dipakai oleh UI.

### Token warna "Cute & Dreamy"

| Token | Hex | Pemakaian utama |
|---|---|---|
| Cotton Candy | `#E9A0B8` | aksen lembut, ilustrasi, badge |
| Blush Mist | `#F9E7ED` | kartu lembut, chip, latar kode OTP |
| Vanilla Cream | `#FFF9F7` | latar aplikasi/admin/email |
| Rosewood | `#A95372` | tombol utama, tautan, fokus |
| Plum Ink | `#493747` | teks utama, QR e-ticket |
| Sage Mint | `#A9C9B7` | status sukses/tenang |

Turunan status: sukses `#4F7D66` + tint `#EAF3EE`; peringatan `#8F5F36` +
`#FDF3E7`; gagal `#B03A4E` + `#FBEBED`.

### Tipografi, bentuk, dan spasi

- Heading: **Nunito**; UI/body: **Nunito Sans**.
- Android: font dibundel di `res/font` agar tidak bergantung internet.
- Admin/email: Google Fonts CDN + fallback system font.
- Spasi: 4 / 8 / 12 / 16 / 24 / 32 dp.
- Radius: 12 / 16 / 20 / 24 dp.
- Bayangan tipis, tanpa gradient dekoratif atau glassmorphism.
- Target sentuh minimal 48dp, kontras teks memenuhi WCAG AA untuk teks normal.
- Tema light-only (`dynamicColor = false`), status bar Vanilla Cream dengan ikon gelap.

```html
<img src="/images/logos/travelo-logo-primary.png" alt="Travelo" width="48" height="48">
<link rel="icon" type="image/png" href="/images/logos/travelo-favicon.png">
<link rel="apple-touch-icon" href="/images/logos/travelo-logo-192.png">
```

---

## 7. Redesign "Cute & Dreamy" (Android + Admin)

Ruang lingkup: aplikasi Android, admin web, email OTP, dan halaman callback
Midtrans. Tidak ada website customer baru. Alur bisnis booking/pembayaran
tetap sama; perubahan pada pass ini adalah redesign dan hardening keamanan
(lihat bagian 8).

### Android

- Design system baru: `Colors.kt`, `Type.kt`, `Shape.kt`, `Dimens.kt`,
  `Theme.kt`, serta komponen bersama `TraveloButton`, `TraveloSurfaces`,
  `TraveloFields`, `TraveloStates`, `TraveloCards`, dan `TraveloBottomNav`.
- Font Nunito + Nunito Sans dibundel di `res/font`; tema light-only.
- Layar yang dirapikan: login, register, OTP, splash, home, explore, detail
  destinasi, detail tour, booking, payment, hasil payment, my bookings,
  profile, dan webview pembayaran.
- Home memakai satu hero + grid adaptif; Explore memakai pencarian, filter
  harga, dan sorting yang benar-benar mengubah query/daftar.
- Detail hanya menampilkan field yang benar-benar dikirim API. Daftar
  "Included" hardcoded dihapus, bukan diisi data karangan.
- Booking mengirim `maxPeople` dari paket ke stepper, menambah catatan
  permintaan khusus (opsional), dan tetap memakai payload API yang sama.
- Payment: chip metode pembayaran dihapus karena metode yang tersedia
  bergantung akun/region Midtrans. Layar menjelaskan bahwa instruksi final
  muncul di halaman Midtrans, bukan menjanjikan metode tertentu.
- My Bookings memakai status pembayaran dari backend, auto-refresh saat layar
  aktif, pull-to-refresh, dan tombol bayar/ulang yang mengarah ke layar
  Payment.
- Profile menampilkan header akun dengan badge terverifikasi, ringkasan trip,
  indikator kelengkapan profil, kartu **next trip** dari booking server, quick
  actions (My Bookings, Ticket history, Edit profile, Help & support), serta
  pull-to-refresh. **Privacy & data** menjelaskan data akun, salinan identitas
  pemesan, dan retensi dokumen; **About Travelo** menampilkan versi aplikasi.
  Semua aksi membuka layar/dialog yang berfungsi; tidak ada item dekoratif yang
  memanggil lambda kosong.
- **Log out** membersihkan token/sesi lokal dan user Firebase lebih dulu,
  kemudian membuka `LoginActivity` dengan task/back stack bersih. Urutan ini
  mencegah layar Login mendeteksi sesi lama lalu memantulkan pengguna kembali
  ke Home sebagai akun tanpa sesi.
- Explore juga kehilangan grid/list toggle, share per kartu, favorit
  sementara, dan dialog pencarian duplikat. Fitur-fitur itu belum punya
  persistence/API; bisa dikembalikan nanti dengan data nyata.
- Offline: `NetworkMonitor` + `ConnectivityViewModel` menampilkan banner
  global di `MainActivity` dengan tombol Retry. Pull-to-refresh ada di Home,
  My Bookings, dan Profile (Material pull-refresh karena Material3 1.1.2 belum
  punya `PullToRefreshBox`).

### E-ticket dari backend

Booking yang sudah `paid` + `confirmed` menampilkan tombol **View e-ticket**.
App memanggil `GET /api/bookings/{id}/e-ticket`; backend memeriksa ownership,
status pembayaran, status booking, `payments.paid_at`, **dan kelengkapan
dokumen pengunjung** (booking baru), lalu menerbitkan reference `TRV-######`
beserta QR token yang ditandatangani HMAC. App hanya merender token dari
server, tidak lagi membuat tiket lokal. Token dapat diverifikasi lewat
`POST /api/tickets/verify`. QR dirender hitam-putih dengan quiet zone empat
modul, ukuran mengikuti lebar dialog, dan filter `None` agar modul tetap tajam
saat ditampilkan di layar.

Sejak 2026-10-21, status pembayaran dan status pemakaian tiket dipisahkan:

- `payment_status`: `pending`, `paid`, `failed`, `expired`.
- `ticket_status`: `unused` atau `used`; `expired` dihitung dari tanggal
  kunjungan di zona waktu `Asia/Jakarta`.
- Pembayaran sukses hanya mengubah status pembayaran/booking; tiket tetap
  `unused` sampai dipindai sah di gerbang.
- Dialog e-ticket menampilkan **Active ticket / Ticket already used / Ticket
  expired**, bukan klaim “verified” identitas.
- Setelah tiket dipakai, booking tidak lagi tampil di daftar aktif My Bookings
  dan tidak menampilkan tombol e-ticket. Tiket berpindah ke **Ticket history**
  (read-only, tanpa QR) berdasarkan `ticket_usage_status` dari backend.
- My Bookings menyimpan snapshot tiket aktif dan melakukan refresh senyap tiap
  10 detik selama layar aktif. Saat snapshot berubah menjadi `used`, traveller
  mendapat popup **Tiket sudah dipindai** dengan tombol ke riwayat. Tiket lama
  di riwayat tidak memicu popup saat aplikasi pertama dibuka.

Booking lama yang tidak memiliki baris pengunjung tetap dapat e-ticket
(kompatibilitas). Detail lengkap ada di bagian 9.

### Admin web

- Layout admin memakai token brand, Nunito/Nunito Sans, sidebar responsif
  dengan menu mobile, backdrop, tombol Escape, focus-visible, dan form logout.
- Warna Tailwind lama di-remap ke palet brand; gradient dekoratif dan
  glassmorphism di header dihapus. Chart dashboard memakai garis Rosewood
  dan area fill datar, bukan gradient biru.
- Login admin punya label, autocomplete, target 48px, pesan error
  danger-tinted, dan logo existing. Staff diarahkan ke `/admin/scanner`;
  admin tetap ke dashboard.
- Halaman `/admin/scanner` tersedia untuk `admin` dan `owner`: kamera QR
  yang mulai otomatis di HTTPS/localhost, pemilih kamera (preferensi kamera
  belakang), upload screenshot QR, input token manual, cakupan destinasi, dan
  popup hasil sukses/used/expired/not-paid/forbidden. QR yang terdeteksi
  langsung dicek tanpa menekan **Take QR photo**. Popup memakai `<dialog>`
  native sehingga bisa ditutup lewat Escape, backdrop, Close, atau X. Library
  `html5-qrcode` dimuat malas saat auto-start kamera atau pembacaan gambar
  dimulai.
  Area pindai mengikuti ukuran viewfinder (bukan kotak tetap 250px), dan
  decoder ZXing dipakai konsisten di semua browser. Menu manajemen
  disembunyikan dari sidebar staff.
- Export/print memakai header Rosewood, border Plum, dan font brand.
- Layout admin dan login memakai `@vite('resources/css/app.css')`; Tailwind
  CDN sudah dihapus. Jalankan `npm run build` setelah mengubah CSS/Blade agar
  `public/build` ikut diperbarui. Semua halaman admin yang dicek memuat CSS
  hasil build dan tidak lagi memanggil CDN.

### Email OTP & callback Midtrans

- Email OTP: header Rosewood datar (tanpa gradient), kotak kode Blush Mist,
  teks Plum, footer lembut, dan font stack brand.
- Callback: kartu putih di atas Vanilla Cream, badge status memakai SVG
  (check/clock/alert), bukan karakter `✓ … !`, shadow tipis, dan tone status
  sudah memakai Sage/Warning/Danger brand.
- Copy email OTP, pesan API OTP, dan halaman callback tersedia di
  `lang/en/travelo.php` dan `lang/id/travelo.php`. Default `APP_LOCALE=en`;
  set `APP_LOCALE=id` untuk tampilan Bahasa Indonesia. String Android baru
  tersedia di `values/strings.xml` dan `values-in/strings.xml`.

---

## 8. Verifikasi, Keamanan, Batasan, dan Tindak Lanjut

### Hardening keamanan (2026-10-20)

- Firebase ID token diverifikasi di server lewat `IdTokenVerifier`
  (signature RS256, issuer, audience/project ID, expiry, `iat`, `auth_time`).
  Tidak ada lagi decode JWT manual. Verifikasi hanya membutuhkan project ID
  publik, bukan private key service account.
- `/api/auth/register` wajib memakai Firebase token yang sudah verified.
  `/api/auth/login` menolak relink email ke UID Firebase lain dan menolak
  akun email/password baru yang belum terverifikasi. Akun lama dengan
  `firebase_uid` yang cocok tetap bisa login.
- OTP disimpan sebagai hash, berlaku 5 menit, hanya sekali pakai, maksimal 5
  percobaan, dan memiliki cooldown 60 detik untuk **send maupun resend**.
  Respons API tidak pernah mengembalikan kode; email di log disamarkan.
- `PaymentController::retrySync` dibatasi ke booking milik user yang login;
  booking milik user lain menghasilkan 404 (IDOR tertutup).
- Webhook Midtrans wajib memiliki signature valid. Signature hilang/salah
  menghasilkan 401. Nominal yang tidak cocok tidak boleh mengubah status
  menjadi `paid`; notifikasi duplikat/stale bersifat idempotent dan tidak
  menurunkan status yang sudah `paid`.
- E-ticket diterbitkan backend setelah cek ownership, `payment_status=paid`,
  `booking_status=confirmed`, `payments.paid_at`, dan (untuk booking baru)
  kelengkapan dokumen pengunjung. QR memakai token HMAC;
  endpoint `POST /api/tickets/verify` tersedia untuk verifikasi.
- Panel admin memakai middleware role: menu manajemen (user, booking, export,
  pembayaran, log) hanya untuk `admin`; scanner dan endpoint tiket/review tetap
  tersedia untuk `admin` dan `staff`, dengan `staff_destinations` ditegakkan
  lagi saat redeem. Login password lokal juga menolak akun traveller.
- Android memakai `ticket_usage_status` dari backend untuk memindahkan tiket
  used/expired dari My Bookings ke Ticket history; tidak ada QR aktif di
  riwayat.
- Disk `local` (tempat dokumen identitas) tidak lagi memakai `serve => true`,
  sehingga Laravel tidak membuat route `/storage` bertanda tangan untuk file
  privat. Fallback `/storage/{path}` hanya menyajikan file di dalam
  `storage/app/public` setelah validasi `realpath`, dan mengirim `nosniff`.
- Identitas pengunjung (2026-10-21): dokumen hanya di private disk dengan nama
  acak; tipe/ukuran divalidasi server; download dibatasi pemilik booking;
  petugas dibatasi `staff_destinations`; nomor identitas dan path dokumen tidak
  pernah masuk JSON. Upload sukses berstatus `pending`, bukan `verified`.
  Redeem tiket memakai satu conditional update atomik dan hanya boleh
  dilakukan staff/admin sesuai destinasi.
- Android: OkHttp logging `BASIC` hanya di debug dan `NONE` di release;
  release mewajibkan base URL `https://`. Respons 401 pada request
  terautentikasi memicu `SessionManager.expireSession()` dan kembali ke
  `LoginActivity`. URL pembayaran tidak lagi ditulis ke log di luar debug.
- Admin memakai pipeline Vite; Tailwind CDN dihapus. Script debug/seeder
  membaca password dari environment dan menolak password pendek/kosong.
- Log backend baru tidak lagi memuat payload Snap, snap token, prefix server
  key, response body Midtrans, email penerima Brevo, atau email user Firebase.
  Log lama di `storage/logs` masih mungkin berisi data historis; bersihkan
  sebelum environment atau log dibagikan.
- `.env` dan service-account Firebase bersifat gitignored. Nilai secret tidak
  boleh ditulis ke dokumentasi, screenshot, atau commit.

### Hasil verifikasi

| Pemeriksaan | Perintah | Hasil |
|---|---|---|
| Test backend | `php artisan test` | **127 passed**, 729 assertions |
| Test foto profil | `php artisan test --filter=ProfilePhotoTest` | **4 passed**, 19 assertions |
| Test keamanan + identitas + tiket | `php artisan test tests/Feature/SecurityFlowTest.php tests/Feature/TicketLifecycleTest.php tests/Feature/VisitorIdentityTest.php` | **50 passed**, 236 assertions |
| Test scanner web | `php artisan test tests/Feature/TicketScannerUiTest.php` | **7 passed**, 29 assertions |
| Uji kamera scanner | Edge headless + fake media device | **auto-start tanpa klik**, kamera aktif 640×480, kontrol stop tampil |
| Uji popup scanner | halaman `/admin/scanner` nyata | **sukses, used, Close/X/backdrop/Escape** berfungsi |
| Uji decode QR | `html5-qrcode.scanFile()` di Edge headless | **QR 1024px dan 720px terbaca tepat** |
| Test OTP khusus | `php artisan test --filter=OtpFlowTest` | **7 passed**, 41 assertions |
| Test Firebase claims | `php artisan test --filter=FirebaseTokenVerifierTest` | **3 passed** |
| Test JVM Android | `gradlew :app:testDebugUnitTest` | **48 passed**, 0 failures |
| Build Android | `gradlew :app:assembleDebug` | **BUILD SUCCESSFUL** |
| Lint Android | `gradlew :app:lintDebug` | **BUILD SUCCESSFUL**, 0 error |
| Build frontend | `npm run build` | **berhasil** (Vite); chunk scanner dimuat malas |
| Admin pages | login, dashboard, scanner, bookings, payments, users, destinations, tour packages, activity logs, import | **200**, CSS build termuat, **tanpa CDN** |
| Lokalisasi | `LocalizationTest` | email OTP + callback **en/id** lolos |
| Migrasi | `php artisan migrate` | kolom password/remember token + identitas/tiket ditambahkan |

Smoke emulator terakhir:

- Login email/password berhasil dengan Firebase token yang benar-benar
  diverifikasi server (bukan token palsu), lalu masuk ke Home.
- Explore → detail destinasi → detail tour → form booking (tanggal,
  travelers, total) berhasil.
- Snap Midtrans terbuka di `PaymentWebViewActivity` dan menampilkan metode
  pembayaran. Booking uji `#34` dibuat, lalu webhook `settlement` bertanda
  tangan valid memindahkannya ke `paid`/`confirmed`.
- Layar hasil pembayaran menampilkan “Payment successful”, lalu My Bookings
  menampilkan status Paid dan e-ticket `TRV-000034` dengan QR dari backend.
- Banner offline muncul saat jaringan dimatikan dan hilang setelah pulih.
- Setelah token Sanctum dicabut, pull-to-refresh memicu 401 dan app kembali
  ke `LoginActivity`.
- Setelah verifikasi, user/booking/payment uji dihapus kembali; DB dev
  kembali ke 6 user, 22 booking, 4 paid booking. Akun Firebase uji tidak
  dibuat ulang.

> **Catatan pass audit 21/10/2026 malam:** smoke di atas adalah hasil pass
> sebelumnya, bukan hasil pass ini. Pada pass ini hanya instalasi APK dan
> layar login emulator yang terverifikasi. Login, booking, upload, pembayaran,
> dan e-ticket **tidak** dijalankan ulang karena tidak tersedia kredensial
> Firebase sintetis dan emulator dipakai bersama/terkena ANR System UI.
> Perangkat fisik juga tidak terhubung. DB dev saat audit berisi 8 user,
> 25 booking, 0 baris pengunjung, dan 25 payment; tidak ada reset atau
> penghapusan data oleh pass ini.

Verifikasi OTP live: `POST /api/auth/otp/send` mengembalikan **200** melalui
Brevo (email disamarkan di respons), dan `POST /api/auth/otp/resend` langsung
mengembalikan **429** karena cooldown. Isi inbox tidak diperiksa otomatis.

### Migrasi & prasyarat

- Migrasi `2026_10_20_000001_add_password_columns_to_users_table` menambahkan
  kolom nullable `password` dan `remember_token`; aman dijalankan di DB lama
  (no-op jika kolom sudah ada) dan diperlukan untuk fresh install/test.
- Migrasi `2026_10_21_000001_add_visitor_identities_and_ticket_lifecycle`
  menambahkan `booking_visitors`, kolom lifecycle tiket pada `bookings`,
  `users.role`, dan pivot `staff_destinations`. Migrasi mempromosikan user
  lama yang punya password lokal menjadi `admin` agar login admin lama tetap
  bekerja. **Risiko:** audit dulu apakah ada akun traveller lama dengan
  password lokal; akun seperti itu akan ikut menjadi admin. Jangan jalankan
  promosi otomatis di environment yang tidak yakin.
- Jalankan `php artisan migrate` dan `npm run build` sebelum menyalakan admin.
- Variabel yang perlu diisi di `.env` (contoh placeholder ada di
  `.env.example`): `FIREBASE_PROJECT_ID`, `MIDTRANS_SERVER_KEY`,
  `MIDTRANS_IS_PRODUCTION`, `MIDTRANS_MERCHANT_ID`, `BREVO_API_KEY`,
  `MAIL_MAILER`, dan `TRAVELO_ADMIN_PASSWORD` (untuk seeder).
  Untuk refund otomatis: `TRAVELO_AUTO_REFUND` (default `false`; set `true`
  untuk memproses refund lewat Midtrans Refund API via antrean).
  Untuk identitas/tiket opsional: `TRAVELO_IDENTITY_DISK` (default `local`),
  `TRAVELO_IDENTITY_MAX_KB` (default `5120`),
  `TRAVELO_IDENTITY_RETENTION_DAYS` (default `90`),
  `TRAVELO_PRIVACY_VERSION`, dan `TRAVELO_TICKET_TIMEZONE`.
- Scheduler untuk retensi dokumen perlu cron `php artisan schedule:run`
  (atau `schedule:work` saat development); perintahnya
  `travelo:purge-identity-documents`, dijadwalkan 02.00.
- `storage/app/firebase-auth.json` saat ini hanya placeholder dengan private
  key kosong. Verifikasi ID token tetap berjalan tanpa file itu; Firebase
  Admin user-management (create/delete user) membutuhkan service account asli.

### Batasan yang diketahui

- Pengiriman OTP nyata, Brevo/SMTP, dan pembayaran Midtrans end-to-end tetap
  bergantung kredensial/network eksternal. Uji live di atas membuktikan
  request/cooldown, bukan pembacaan inbox.
- Firebase Admin SDK belum bisa dipakai untuk mengelola user karena service
  account masih placeholder.
- Password admin dev masih memakai hash lama; rotasi dengan `set_password.php`
  dan `TRAVELO_NEW_PASSWORD` sebelum environment dibagikan.
- Brevo API key di `.env` sempat terlihat di output tool lokal; rotasi key di
  dashboard Brevo, lalu perbarui `.env`. Jangan commit `.env`.
- Akun Firebase `test@travelo.com` masih `emailVerified=false`; jangan pakai
  sebagai akun produksi.
- Foto destinasi seed masih bermasalah (URL mati/salah foto) dan logo final
  belum tersedia. Keduanya sengaja ditunda.
- Diferensiasi visual dari Traveloka sengaja ditunda atas permintaan pemilik
  project.
- Dokumen identitas baru divalidasi tipe dan ukurannya di server; keterbacaan
  isi tidak dinilai otomatis. Status `verified` hanya lewat review manual
  petugas, dan bukan verifikasi resmi dari instansi penerbit identitas.
- **UI scanner QR web admin sudah dibuat (2026-10-21)** di `/admin/scanner`
  dengan kamera yang mulai otomatis, pemilih kamera, input manual, dan popup
  hasil redeem. **Keterbatasan:** auto-start sudah diuji di Edge headless
  dengan fake media device, termasuk simulasi izin ditolak dan halaman
  non-HTTPS; kamera fisik, dialog izin perangkat, kamera sibuk, dan pemilihan
  dua kamera di ponsel belum diuji. Halaman log semua scan admin belum dibuat.
- Popup **Tiket sudah dipindai** di Android bergantung pada polling 10 detik
  saat My Bookings aktif, bukan push notification. Popup muncul pada
  polling/refresh berikutnya. Snapshot tiket aktif disimpan di preferences
  sesi, jadi tiket lama di riwayat tidak memicu popup palsu.
- Halaman antrean review dokumen di web admin belum dibuat. Endpoint
  `/admin/visitors/{visitor}/document-review`, reissue, dan download dokumen
  sudah tersedia beserta service-nya.
- Penugasan `staff` ke destinasi masih lewat database (`staff_destinations`);
  belum ada halaman admin untuk mengelolanya. Akun staff juga harus dibuat
  dengan password lokal dan `role = staff` (belum ada UI manajemen staff).
- Alur form pengunjung + upload dokumen di Android sudah lolos build, unit
  test, dan lint, tetapi belum diuji ulang end-to-end di emulator/perangkat
  pada pass ini. Backend flow-nya sudah diuji otomatis.
- **Pemulihan upload setelah aplikasi ditutup sudah diimplementasikan
  (2026-10-21).** Referensi booking disimpan di SharedPreferences terpisah
  (`travelo_document_recovery`), tanpa nomor identitas, isi dokumen, URI, nama
  file asli, path privat, atau token. Home dan My Bookings menampilkan aksi
  **Lengkapi Dokumen**; layar pemulihan memuat status visitor dari backend dan
  hanya mengunggah dokumen yang masih kosong. URI lama tidak dipakai ulang —
  pengguna memilih file lagi setelah restart. Logout tidak menghapus antrean,
  tetapi akses tetap dibatasi oleh user id dan ownership backend.
  Keterbatasan: belum diuji pada perangkat/emulator nyata karena sesi
  terautentikasi tidak tersedia pada pass ini.
- Satu QR e-ticket mewakili **satu booking** (`TRV-######`), bukan satu QR per
  pengunjung. Jumlah pengunjung tetap tercantum di data tiket; jangan mengubah
  skema token tanpa migrasi/kompatibilitas.
- Scheduler retensi dokumen hanya berjalan jika cron/scheduler aktif; tanpa
  itu dokumen tidak terhapus otomatis.

### Tindak lanjut yang disarankan

1. Rotasi password admin dev dan API key Brevo; pastikan `.env` tidak pernah
   masuk commit.
2. Isi service account Firebase asli hanya jika fitur Admin SDK diperlukan;
   simpan di luar repo/gitignore.
3. Uji OTP sampai verifikasi kode dengan inbox nyata dan uji pembayaran
   Midtrans dengan metode yang benar-benar diselesaikan.
4. Tambahkan test formatter/mapper/ViewModel Android dan jalankan di CI.
5. Ganti logo final dan perbaiki data/foto seed destinasi.
6. Kerjakan diferensiasi Travelo vs Traveloka pada pass terpisah.
7. Uji scanner QR di browser/perangkat dengan HTTPS, izin kamera nyata,
   pemilih dua kamera, dan fallback **Scan QR image**. Uji juga popup tiket
   dipindai di Android dengan sesi nyata; pertimbangkan halaman log scan admin
   atau push notification jika dibutuhkan operasional.
8. Buat halaman admin untuk antrean review dokumen dan pengelolaan
   `staff_destinations`.
9. Uji end-to-end alur booking multi-pengunjung + upload dokumen di perangkat,
   termasuk retry saat upload gagal dan perilaku saat app ditutup di tengah
   upload.
10. Tinjau kebijakan privasi dengan penasihat hukum dan isi identitas badan
    usaha/kontak DPO sebelum produksi.

---

## 9. Identitas Pengunjung & Lifecycle Tiket

Implementasi: **2026-10-21**. Pembaruan data diri akun: **2026-10-22**.
Dokumen rinci ada di
[`docs/identitas-dan-tiket-2026-10-21.md`](docs/identitas-dan-tiket-2026-10-21.md)
dan kebijakan privasi di
[`docs/privacy-identitas-pengunjung.md`](docs/privacy-identitas-pengunjung.md).
Laporan audit dan test plan lengkap ada di
[`docs/laporan-audit-2026-10-21.md`](docs/laporan-audit-2026-10-21.md).
Laporan scanner dan riwayat tiket ada di
[`docs/laporan-scanner-tiket-2026-10-21.md`](docs/laporan-scanner-tiket-2026-10-21.md).

### Data pengunjung

- Satu booking dapat memiliki banyak pengunjung (tabel `booking_visitors`),
  termasuk pemesan sebagai baris dengan `is_booker = true`.
- Jenis identitas yang diterima: `ktp`, `kia`, `student_card`, dan `other`
  (wajib menyebut nama dokumen resmi). KIA wajib tanggal lahir.
- Jumlah baris pengunjung harus sama dengan `people_count`; nama, nomor, dan
  tipe identitas divalidasi server.
- Nomor identitas dan path dokumen tidak pernah dikirim ke klien; API hanya
  mengembalikan nomor tersamar (4 karakter terakhir), status dokumen, dan
  metadata aman.
- Data diri **pemesan** disimpan sekali per akun di `saved_identities`. Booking
  berikutnya memakai `GET /api/auth/identity` untuk prefill dan
  `reuse_saved_identity_document = true` agar dokumen private disalin ke
  booking baru tanpa upload ulang. Pengunjung tambahan tetap diisi manual.
- Layar **Profile** menampilkan data diri tersimpan dengan nomor identitas
  tersamar, status dokumen berupa chip (siap dipakai, menunggu review, perlu
  diganti, atau belum ada), tanggal pembaruan, dan aksi **Delete saved
  identity** (konfirmasi terlebih dahulu). Penghapusan hanya menghapus salinan
  level akun; booking lama beserta dokumennya tetap utuh.
- **Profile strength** dihitung dari nama, email, telepon, tanggal lahir,
  kota, dan ada/tidaknya saved identity. **Next trip** adalah booking
  non-cancelled terdekat dengan tanggal kunjungan hari ini atau setelahnya.
  Keduanya hanya memakai data API; tidak ada nilai yang dikarang di UI.

### Upload dokumen privat

- File disimpan di disk `local` (`storage/app/private`) dengan nama acak,
  bukan di `public/storage`; tidak ada URL publik.
- Validasi server: JPG/PNG/PDF, maksimal 5 MB (dapat diubah lewat env), dan
  hanya pemilik booking yang boleh upload/unduh/hapus.
- Setelah tiket dipakai, data pengunjung dan dokumen tidak dapat diubah.
- Upload sukses hanya menandai `document_status = pending`. Status `verified`
  hanya dapat diatur petugas lewat review manual; `rejected` menyimpan alasan.
- Dokumen dihapus otomatis 90 hari setelah tanggal kunjungan lewat
  `travelo:purge-identity-documents` (jadwal 02.00), atau manual oleh pemilik
  sebelum tiket dipakai.
- Salinan dokumen level akun untuk pemesan disimpan terpisah dan tidak ikut
  retensi booking; salinan itu diganti/dihapus oleh pemilik, atau lewat
  `DELETE /api/auth/identity`.
- Keterbacaan dokumen tidak dinilai otomatis; tidak ada OCR/facial
  recognition.

### Status pembayaran vs pemakaian tiket

| Dimensi | Nilai | Diubah oleh |
|---|---|---|
| Pembayaran | `pending`, `paid`, `failed`, `expired` | Midtrans webhook/sync |
| Pemakaian tiket | `unused`, `used` | Hanya redeem QR yang sah |
| Kedaluwarsa tiket | `expired` (turunan) | Dihitung dari `booking_date` (`Asia/Jakarta`) |

Pembayaran sukses **tidak** menandai tiket `used`. E-ticket baru diterbitkan
jika booking `paid` + `confirmed` + `payments.paid_at` terisi dan dokumen
pengunjung lengkap (khusus booking baru; booking lama tanpa baris pengunjung
tetap kompatibel). Token HMAC tetap stabil; field versi hanya berubah saat
reissue resmi oleh admin. **Satu token/QR berlaku untuk satu booking**
(`TRV-######`); jumlah pengunjung ada di payload tiket, bukan QR terpisah.

### Scanner gerbang & riwayat tiket

| Method | Endpoint | Hak akses |
|---|---|---|
| `GET` | `/admin/scanner` | `admin` atau `owner` |
| `POST` | `/admin/tickets/redeem` | `owner` sesuai destinasi atau `admin` |
| `PATCH` | `/admin/visitors/{visitor}/document-review` | `owner`/`admin` sesuai destinasi |
| `GET` | `/admin/visitors/{visitor}/document` | `owner`/`admin` sesuai destinasi |

Redeem memakai satu conditional `UPDATE ... WHERE ticket_status = 'unused'`
dan memeriksa jumlah baris terpengaruh. Double scan (termasuk bersamaan)
hanya berhasil sekali; panggilan berikutnya mendapat 409 `used`. Petugas
tanpa akses destinasi mendapat 403, dan pembayaran yang belum sah tidak dapat
di-redeem.

Halaman `/admin/scanner` menyediakan kamera yang mulai otomatis (library
dimuat malas), pemilihan kamera belakang, input token manual, serta popup
sukses/used/expired/not-paid/forbidden. Staff hanya melihat menu scanner; akun
staff tanpa `staff_destinations` mendapat peringatan dan tidak dapat redeem.
Auto-start kamera sudah diuji dengan fake media device, termasuk simulasi izin
ditolak dan halaman non-HTTPS; kamera fisik tetap perlu diuji.

Di Android, `TicketHistoryRules` memakai `ticket_usage_status` dari backend:
tiket `used` (dan `expired` yang pembayarannya sukses) hilang dari daftar aktif
My Bookings, tombol e-ticket tidak lagi tampil, dan tiket muncul di **Ticket
history** read-only tanpa QR. `TicketUsageNoticeRules` mendeteksi perubahan
snapshot tiket aktif menjadi `used` dan menampilkan popup satu kali. Polling
senyap berjalan tiap 10 detik selama My Bookings aktif, sehingga tiket hilang
dan popup muncul tanpa refresh manual. `ticket_used_at` hanya dipakai untuk
keterangan riwayat; status aktif tetap milik backend.

**Yang belum ada:** halaman log semua scan admin, halaman antrean review
dokumen, dan halaman pengelolaan `staff_destinations`.

---

## 10. Ulasan Kunjungan Terverifikasi

Rating publik Travelo **hanya** berasal dari ulasan tamu yang benar-benar
berkunjung. Rating lama di kolom `destinations.rating` dan
`tour_packages.rating` tidak lagi dipercaya atau ditampilkan sebagai rating
ulasan. Jika belum ada ulasan, nilai rating adalah `null` (bukan angka palsu).

### Syarat menulis ulasan

Satu booking hanya boleh punya satu ulasan. Pemeriksaan dilakukan ulang di
server, bukan dari kiriman aplikasi:

1. Booking milik akun yang sedang login.
2. `payment_status = paid`, `booking_status = confirmed`, dan ada baris
   `payments` dengan `paid_at` terisi.
3. `ticket_status = used` dan `ticket_used_at` terisi (tiket sudah discan di
   gerbang). Pembayaran/konfirmasi saja tidak cukup.
4. Tidak ada refund, partial refund, chargeback, cancel, deny, failure, atau
   expire pada transaksi pembayaran.
5. Destinasi diambil dari booking -> tour package -> destination. Aplikasi
   tidak boleh mengirim `user_id` atau `destination_id` sendiri.
6. `UNIQUE(booking_id)` di database menjaga agar dua permintaan bersamaan
   tetap menghasilkan tepat satu ulasan. Permintaan kedua mendapat `409`
   dengan `reason = already_reviewed`.
7. Satu kunjungan hanya dinilai sekali. Jika kunjungan yang lebih baru di
   destinasi yang sama sudah diulas, tiket lama yang belum diulas tidak
   memunculkan tombol nilai lagi dan ditolak server dengan `409`
   (`reason = superseded`). Tombol baru muncul lagi hanya untuk kunjungan
   yang benar-benar lebih baru dari ulasan terakhir.

Rating wajib bilangan bulat 1-5. Komentar opsional, maksimal 1000 karakter.
Pemilik dapat mengubah atau menghapus ulasan yang statusnya `published`.
Ulasan `hidden` tidak dapat diubah/dihapus/dikirim ulang agar keputusan
moderasi tidak bisa dilewati.

### Endpoint

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| `GET` | `/api/destinations/{id}/reviews` | publik | Daftar + ringkasan ulasan `published` |
| `GET` | `/api/destinations/{id}/rating-summary` | publik | Rata-rata, jumlah, distribusi |
| `GET` | `/api/destinations/{id}/review-eligibility` | login | Status tamu di destinasi itu |
| `POST` | `/api/bookings/{bookingId}/review` | pemilik booking | Kirim ulasan pertama |
| `PATCH` | `/api/reviews/{reviewId}` | pemilik ulasan | Ubah ulasan `published` |
| `DELETE` | `/api/reviews/{reviewId}` | pemilik ulasan | Hapus ulasan `published` |
| `GET` | `/api/reviews/mine` | login | Ulasan milik sendiri, termasuk `hidden` |

Ringkasan publik hanya menghitung ulasan `published`, sehingga ulasan yang
disembunyikan admin langsung keluar dari rata-rata, jumlah, dan distribusi.
Respons publik hanya berisi nama tampilan aman (nama depan + inisial), rating,
komentar, tanggal, dan penanda `verified_visit`. Tidak ada email, nomor
identitas, token QR, atau dokumen pribadi.

Status yang ditampilkan Android: `not_booked`, `unpaid`, `not_checked_in`,
`eligible`, `reviewed`, `superseded`, dan `cancelled`. Kartu status muncul di
detail destinasi, sedangkan aksi menilai/mengubah/menghapus juga tersedia di
Ticket history untuk tiket yang sudah digunakan. Tiket lama yang kalah baru
dari ulasan kunjungan berikutnya berstatus `superseded` dan tidak menampilkan
tombol nilai. Ulasan yang sedang dimoderasi tampil sebagai "under moderation"
tanpa tombol edit/hapus.

### Moderasi admin

Menu **Reviews** hanya ada di grup `role:admin`; akun `staff` scanner tidak
mendapat akses. Admin dapat memfilter status/destinasi/rating/tanggal/cari,
lalu hide atau restore dengan alasan minimal 3 karakter. Setiap aksi dicatat
di `activity_logs` dengan `log_name = review_moderation`, status sebelum/sesudah,
alasan, serta id destinasi/booking. Isi komentar ulasan tidak disalin ke log.

Import destinasi/tour package tidak lagi menerima kolom `rating`, dan form
admin tidak menyediakan input rating manual. Rating tour package mengikuti
agregat destinasi dari ulasan `published`.

### Data demo lokal

Untuk presentasi, ulasan contoh di semua destinasi dapat diisi dengan:

```cmd
php artisan db:seed --class=DemoReviewSeeder
```

Seeder ini hanya berjalan saat `APP_ENV=local` (atau `testing`), tidak dipanggil
oleh `DatabaseSeeder`, dan tidak boleh dijalankan di produksi. Setiap ulasan
demo tetap dibuat melalui `ReviewService`: akun demo memiliki booking lunas,
pembayaran `paid_at`, dan tiket yang sudah dipakai, sehingga muncul sebagai
ulasan **verified visit**. Saat ini seeder mengisi 3 ulasan untuk masing-masing
dari 8 destinasi. Setelah dinilai, tombol nilai tidak muncul lagi untuk
kunjungan itu. Tombol hanya terbuka lagi jika ada kunjungan baru yang lebih
baru di destinasi yang sama; tiket lama yang belum diulas tidak memunculkan
tombol lagi. Edit/hapus ulasan tetap tersedia di Ticket History.

### Pengujian

`tests/Feature/ReviewTest.php` mencakup eligibility (termasuk booking lama),
validasi rating/komentar, duplikat unik, kepemilikan, edit/hapus, moderasi,
penolakan staff, agregat tanpa ulasan palsu, dan keamanan PII. Unit test
Android `ReviewModelsTest` menjaga kontrak JSON `tour_package`, state booking,
serta model ringkasan/daftar ulasan. Verifikasi visual di perangkat tetap
memerlukan emulator/HP fisik.

---

## 11. Foto Profil

Traveller yang sudah login dapat mengganti foto profilnya sendiri. Foto profil
bersifat **publik** karena ditampilkan di aplikasi, berbeda dari dokumen
identitas yang tetap disimpan di disk privat.

### Endpoint

| Method | Endpoint | Akses | Body |
|---|---|---|---|
| `POST` | `/api/auth/profile/photo` | login | multipart, field `photo` |

Header `Authorization: Bearer <token>` wajib dikirim. Endpoint dibatasi
`throttle:10,1` (maksimal 10 percobaan per menit). Respons sukses berisi
`success`, `message`, dan `data` user terbaru yang sudah memuat URL foto.

### Validasi dan penyimpanan

- Tipe yang diterima: JPG/JPEG, PNG, dan WebP. SVG, GIF, PDF, dan tipe lain
  ditolak. Server memvalidasi isi gambar dan MIME, bukan hanya nama file.
- Ukuran maksimal 10 MB.
- File disimpan di disk `public`:
  `storage/app/public/profile-photos/{userId}/{nama-acak}.{ext}`.
- Nama file selalu acak; nama asli dari perangkat tidak pernah dipakai.
- Kolom `users.photo` menyimpan path relatif (`profile-photos/...`). Accessor
  `User` mengubahnya menjadi URL publik lewat `Storage::disk('public')->url()`
  sehingga URL mengikuti `APP_URL` dan tidak terkunci pada host saat upload.
- Saat mengganti foto, file lokal lama dihapus **setelah** nilai database baru
  berhasil disimpan. Jika penyimpanan database gagal, file baru ikut dihapus.
- Foto dari Google/Firebase tetap berupa URL absolut dan tidak dihapus oleh
  proses ini.
- `public/storage` harus tersedia (`php artisan storage:link`). Di environment
  ini link tersebut sudah berupa junction ke `storage/app/public`.

### Perilaku saat login ulang

- Jika user belum pernah mengunggah foto sendiri, foto dari provider
  Google/Firebase boleh diperbarui saat sign-in.
- Setelah user mengunggah foto sendiri, foto tersebut **tidak** ditimpa oleh
  foto provider pada sign-in berikutnya.
- Belum ada aksi "hapus foto" untuk kembali ke foto provider; mengganti foto
  dilakukan dengan mengunggah gambar baru.

### Alur Android

- Avatar di header dan editor **Edit profile** menampilkan aksi
  **Change photo**. Avatar juga dapat diketuk untuk membuka editor.
- Pemilihan memakai system picker `image/*` (tanpa izin storage tambahan).
- Klien memeriksa MIME dan ukuran sebelum membaca byte, lalu mengirim multipart
  ke endpoint di atas.
- Selama upload, avatar menampilkan indikator progres dan aksi lain dinonaktifkan.
- Setelah sukses, state profil, URL avatar, dan sesi lokal diperbarui; pesan
  sukses muncul. Error validasi/upload ditampilkan di editor.
- Image loader Coil juga mengirim header `ngrok-skip-browser-warning` agar URL
  foto dari host ngrok tidak mendapat halaman peringatan browser.
- Implementasi utama: `ProfilePhotoPicker.kt`, `ProfileViewModel.kt`,
  `TraveloRepository.kt`, `ApiService.kt`, `TraveloApp.kt`, dan
  `ProfileScreen.kt`.

### Pengujian

`tests/Feature/ProfilePhotoTest.php` mencakup penolakan tamu, upload dan
penggantian foto (termasuk penghapusan file lama), penolakan PDF/SVG/file
terlalu besar, serta foto custom yang tetap bertahan setelah sign-in Firebase.
Hasil: **4 passed**, 19 assertions. Verifikasi visual di perangkat/emulator
belum dilakukan pada pass ini karena `adb`/emulator tidak tersedia; yang sudah
diverifikasi adalah unit test, lint, kompilasi, dan build APK.

---

## 12. Kuota per Tanggal, Pembatalan Booking, dan Push

Tiga fitur operasional yang saling melengkapi. Semuanya sudah diimplementasikan
di backend dan Android, tetapi pengiriman push sungguhan masih menunggu
kredensial Firebase yang valid (lihat batasan di bagian akhir).

### 12.1 Kuota per tanggal

Kapasitas default setiap tanggal berasal dari `tour_packages.max_people`.
Admin dapat menimpa kapasitas satu tanggal atau menutupnya lewat tabel
`tour_package_date_availabilities`:

| Kolom | Arti |
|---|---|
| `tour_package_id`, `date` | unik per paket + tanggal |
| `capacity` | `null` = pakai `max_people`; angka = override |
| `is_closed` | tanggal tidak bisa dibooking sama sekali |
| `note` | alasan internal/tampilan admin |

Perhitungan kursi terisi hanya menghitung booking dengan `booking_status`
`pending`, `confirmed`, atau `completed` dan `payment_status` bukan `failed`
atau `expired`. Booking `cancelled` otomatis melepas kursinya.

Endpoint publik:

| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/api/tour-packages/{id}/availability?month=YYYY-MM&people=2` | Kapasitas, sisa kursi, status tutup, dan override per hari |

Admin mengelola override di **Tour Packages → Availability**
(`admin.tour-packages.availability`), termasuk membuat, mengubah, dan menghapus
override. Halaman ini memakai middleware `role:admin`.

Pembuatan booking (`POST /api/bookings`) mengunci baris tour package di dalam
transaksi, lalu memeriksa ulang kapasitas tanggal tersebut. Jika penuh atau
ditutup, respons `409` berisi `success = false` dan `data.reason` `full` atau
`closed` beserta `remaining`. Dengan begitu dua permintaan bersamaan tidak bisa
menjual kursi yang sama.

Android memuat tampilan bulan lewat `AvailabilityRules` dan
`BookingViewModel.loadAvailability()`. Tanggal yang tutup atau kursinya kurang
menampilkan pesan sisa kursi dan memblokir tombol lanjut. Jika pemuatan
availability gagal, form tetap boleh dikirim karena server tetap memvalidasi.
Perubahan jumlah pelancong langsung dihitung ulang dari `remaining`.

### 12.2 Pembatalan dengan approval admin

Pembatalan bukan aksi langsung. Traveller mengirim **permintaan**; booking baru
berubah setelah admin menyetujuinya. Perubahan tanggal (reschedule) tidak
didukung — pembatalan adalah satu-satunya perubahan booking. Data disimpan di
`booking_change_requests`:

| Kolom | Arti |
|---|---|
| `booking_id`, `user_id` | pemilik dan booking terkait |
| `type` | selalu `cancellation` |
| `status` | `pending`, `approved`, `rejected`, `cancelled` |
| `original_booking_date` | tanggal kunjungan saat permintaan dibuat |
| `reason` | alasan dari traveller (opsional, maks. 1000) |
| `admin_note` | catatan keputusan admin |
| `reviewed_by`, `reviewed_at` | admin dan waktu keputusan |
| `refund_status` | `not_applicable`, `pending`, `processing`, `refunded`, `failed` |
| `refund_percentage`, `refund_amount` | snapshot persentase dan nominal refund saat disetujui |
| `refund_reference` | referensi refund (otomatis via Midtrans atau catatan manual) |
| `refund_note`, `refunded_at` | bukti refund manual |
| `refund_failure_reason` | alasan gagal jika refund otomatis gagal |

Kolom `requested_date` masih ada di database dari desain reschedule lama, tetapi
sudah tidak ditulis maupun dibaca oleh aplikasi. Kolom itu sengaja tidak
dihapus agar tidak ada migrasi destruktif.

Syarat pengajuan (diperiksa ulang di server):

1. Booking milik akun yang login.
2. `payment_status = paid` dan `booking_status = confirmed`.
3. Tanggal kunjungan belum lewat.
4. Tiket belum dipakai (`ticket_usage_status = unused`).
5. Tidak ada permintaan lain yang masih `pending` untuk booking itu.

Saat admin menyetujui, `booking_status` menjadi `cancelled` dan `refund_status`
diisi berdasarkan kebijakan refund bertingkat (`config/travelo.php`,
bagian `refund`):

- `>= 7 hari sebelum kunjungan`: 100% nominal yang dibayar (termasuk diskon promo)
- `3-6 hari sebelum kunjungan`: 50%
- `< 3 hari sebelum kunjungan`: 0% → `refund_status = not_applicable`

Snapshot persentase + nominal disimpan pada kolom `refund_percentage` dan
`refund_amount` waktu approval, sehingga perubahan kebijakan nantinya tidak
mengubah refund yang sudah disetujui.

Jika `TRAVELO_AUTO_REFUND=true` dan nominal lebih dari 0, refund diproses
otomatis via antrean (`ProcessAutoRefund`) yang memanggil Midtrans Refund API
dengan kunci idempotensi (`travelo_refund_<id_change_request>`). Status
berurutan `processing` → `refunded` (sukses) atau `failed` (gagal, dengan
alasan tersimpan). Gagal gateway tidak membatalkan keputusan admin dan tidak
menghasilkan refund ganda: admin dapat mencoba ulang (retry) dari panel admin,
yang mengantrekan ulang job yang sama.

Jika `TRAVELO_AUTO_REFUND=false` (default), refund tetap manual: admin menandai
`refunded` setelah saldo dikembalikan lewat luar aplikasi. Kedua jalur
mengirim push notification hasil keputusan ke traveller.

Penolakan tidak mengubah booking.

Setiap transisi dicatat di `activity_logs` (permintaan dibuat, disetujui,
ditolak, refund ditandai). Penolakan wajib menyertakan `admin_note` minimal 3
karakter.

Endpoint API:

| Method | Endpoint | Akses | Keterangan |
|---|---|---|---|
| `GET` | `/api/bookings/{id}/change-requests` | pemilik | Riwayat maksimal 20 permintaan |
| `POST` | `/api/bookings/{id}/change-requests` | pemilik | Kirim permintaan pembatalan (`type` opsional, hanya `cancellation`) |

Antrean admin ada di **Change Requests** (`admin.change-requests.index`) dengan
aksi approve, reject, dan tandai refund. Booking juga mengekspos
`latest_change_request` pada `/api/my-bookings` agar Android bisa menampilkan
status terakhir.

Di Android, kartu booking yang memenuhi syarat menampilkan tombol **Cancel
booking**. Tombol ini membuka dialog konfirmasi yang menjelaskan bahwa
pembatalan menunggu review admin. Status `pending`, `approved`, atau `rejected`
(beserta catatan admin) tampil di kartu, dan tombol aksi disembunyikan selama
masih ada permintaan `pending`. Booking yang sudah `cancelled` menampilkan
keterangan pembatalan dan tidak lagi menawarkan e-ticket maupun pembayaran
ulang.

### 12.3 Push notification (FCM)

Token perangkat disimpan di `device_tokens` (unik per token). Token yang sama
didaftarkan ulang akan dipindahkan ke akun yang sedang login, sehingga perangkat
bersama tidak menerima push akun sebelumnya. Logout menghapus token perangkat
milik sesi tersebut.

| Method | Endpoint | Akses |
|---|---|---|
| `POST` | `/api/device-tokens` | login |
| `DELETE` | `/api/device-tokens` | login (token milik sendiri) |

Notifikasi yang dikirim `PushNotificationService` memakai payload **data-only**
dengan format standar:

| `type` | `screen` | ID |
|---|---|---|
| `payment_paid` | `e_ticket` | `booking_id` |
| `payment_failed`, `payment_expired`, `payment_after_expiry`, `payment_update` | `booking_detail` | `booking_id` |
| `ticket_used`, `trip_reminder` | `e_ticket` | `booking_id` |
| `change_request_approved`, `change_request_rejected`, `change_request_pending`, `change_request_updated` | `booking_detail` | `booking_id`, `change_request_id` |
| `review_moderated` | `review` | `destination_id`, `review_id` |

`title` dan `body` juga dikirim di dalam `data`, sehingga Firebase SDK tidak
menampilkan notifikasi sendiri. `TraveloMessagingService` selalu membuat
notifikasi dan `PendingIntent` ke `MainActivity` dengan extras `type`, `screen`,
dan id terkait. Dengan begitu jalur tap-nya sama untuk foreground, background,
cold start, maupun warm start.

Payload tidak pernah memuat token tiket, dokumen identitas, nomor identitas,
email, telepon, nominal, atau daftar pengunjung. Data sensitif tetap hanya bisa
diambil lewat API yang memeriksa kepemilikan.

Jenis notifikasi yang dikirim:

- hasil pembayaran (`paid`, `failed`, `expired`),
- tiket discan di gerbang,
- keputusan permintaan pembatalan,
- hasil moderasi ulasan (dipanggil dari `ReviewService::moderate`),
- pengingat perjalanan H-1 lewat command terjadwal
  `travelo:send-trip-reminders` (idempoten dalam satu hari).

Seluruh pengiriman bersifat **best-effort**. Jika service account Firebase
belum valid, `PushNotificationService::isConfigured()` mengembalikan `false`,
pengiriman dilewati dan dicatat di log. Alur booking, pembayaran, scan tiket,
moderasi, dan perubahan booking tidak pernah gagal karena push.

Di Android:

- dependency `firebase-messaging-ktx` (BOM Firebase yang sudah ada),
- channel `travelo_updates` dibuat di `TraveloApp`,
- `TraveloMessagingService` menangani token baru dan **semua** pesan data-only
  (foreground/background/cold start), lalu memanggil `TraveloNotifications`,
- `TraveloNotifications` membuat `PendingIntent` ke `MainActivity` dengan
  extras `type`, `screen`, dan id; `MainActivity` memakai `launchMode="singleTop"`
  agar tap saat aplikasi terbuka masuk lewat `onNewIntent`,
- `DeepLinkParser` (Kotlin murni, tanpa Android) memetakan extras ke
  `DeepLinkTarget`: `booking_detail`, `e_ticket`, `review`, atau `destination`,
- `DeepLinkController` menyimpan tujuan yang belum bisa dijalankan (cold start
  sebelum NavHost siap, atau pengguna belum login),
- `NavGraph` mengarahkan target dengan `popUpTo(Home)`, sehingga back dari layar
  hasil deep link kembali ke Home tanpa menduplikasi stack,
- login sukses melanjutkan target yang tersimpan; booking 403/404 menampilkan
  pesan ramah lalu membuka **Booking Saya**,
- `PushTokenStore` menyimpan token di preferences terpisah agar tidak terhapus
  saat `SessionManager.clearSession()`,
- `PushRegistrar` mendaftarkan token saat login, saat aplikasi dibuka dengan
  sesi aktif, dan setelah token berotasi; unregister dilakukan saat logout,
- izin `POST_NOTIFICATIONS` diminta di Android 13+ setelah login.

Agar push benar-benar terkirim, isi service account Firebase yang valid di
`Travelo-Backend/storage/app/firebase-auth.json` (field `private_key` tidak
boleh kosong) atau arahkan `FIREBASE_CREDENTIALS` ke file JSON lain, lalu:

```cmd
php artisan config:clear
php artisan cache:clear
```

Untuk pengingat harian, daftarkan scheduler di cron produksi:

```cmd
php artisan schedule:run
```

### 12.4 Verifikasi

Backend (`php artisan test`):

- **267 passed**, 1.634 assertions.
- `AvailabilityTest`: 13 tes (kapasitas default/override, tanggal tutup,
  pelepasan kursi, endpoint publik, penolakan booking penuh/tutup, akses admin).
- `BookingChangeRequestTest`: 14 tes (pengajuan pembatalan, penolakan
  reschedule, kepemilikan, satu permintaan pending, validasi tanggal
  kunjungan, approval/rejection, refund manual, audit log, payload
  `latest_change_request`).
- `RefundPolicyTest`: **12 tes baru** (tipe refund H-7/H-3/hari H, batas hari,
  zona waktu Asia/Jakarta, booking belum dibayar, tiket sudah dipakai,
  diskon promo, preview endpoint hanya pemilik, otorisasi).
- `PushNotificationTest`: 16 tes. Enam tes baru menangkap pesan yang dikirim ke
  `Messaging` dan memastikan setiap tipe memakai `type`/`screen`/id yang benar,
  payload data-only tanpa blok `notification`, serta tidak memuat token tiket,
  dokumen identitas, email, telepon, nominal, atau data pengunjung.
- `WishlistTest`: 12 tes (toggle destinasi/paket, validasi item, unique
  constraint, daftar terbaru dulu + ringkasan item, filter tipe, ownership,
  `is_wishlisted` untuk pemilik/user lain/token invalid).

Migrasi `2026_10_25_000001` sampai `2026_10_25_000003` sudah dijalankan pada
database SQLite development. Tidak ada reset atau penghapusan data.

Android:

- `testDebugUnitTest`: **152 tes, 0 gagal** (termasuk 16 tes `DeepLinkParser`
  untuk semua tipe notifikasi, extras tidak lengkap, screen override, dan tipe
  tidak dikenal; 5 tes `RefundPreview` untuk pemetaan tier dan format nominal).
- `lintDebug`: **0 errors**, 31 warnings (mayoritas warning lama; tidak ada
  error pada kode baru).
- `assembleDebug`: APK berhasil dibuat.

Pembatalan adalah satu-satunya perubahan booking. Dialog dan tombol reschedule
sudah dihapus dari Android; backend tetap menolak `type` selain `cancellation`
dengan `422`.

Batasan yang masih ada:

- Pengiriman push nyata belum bisa diverifikasi karena
  `storage/app/firebase-auth.json` masih berisi `private_key` kosong.
- Verifikasi manual di perangkat/emulator belum dilakukan karena `adb` dan
  emulator tidak tersedia di lingkungan ini. Yang terverifikasi adalah unit
  test, lint, kompilasi, dan build APK.
- Semua perubahan pada pass ini masih belum di-commit.

---

## 13. Wishlist / Favorit Saya

Traveller dapat menyimpan destinasi dan paket wisata. Backend adalah sumber
kebenaran: setiap toggle divalidasi terhadap item yang benar-benar ada, dan
semua daftar/hapus dibatasi ke pemilik akun.

### 13.1 Skema dan endpoint

Migrasi aditif `2026_10_25_000004_create_wishlists_table.php` membuat tabel
`wishlists`:

| Kolom | Arti |
|---|---|
| `id` | primary key |
| `user_id` | FK ke `users`, cascade delete |
| `item_type` | enum `destination` atau `tour_package` |
| `item_id` | id item tanpa FK polimorfik (divalidasi service) |
| `created_at`, `updated_at` | waktu simpan, dipakai untuk urutan terbaru |
| unique `(user_id, item_type, item_id)` | mencegah duplikat |
| index `(user_id, created_at)` | mempercepat daftar favorit |

Endpoint (semua butuh `Authorization: Bearer <token>`):

| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/api/wishlist?type=destination\|tour_package&page=1&per_page=15` | Daftar favorit terbaru dulu, `meta` pagination, tiap baris berisi `item` ringkas (nama, kota/negara, gambar, harga paket, rating publik, durasi) |
| `POST` | `/api/wishlist/toggle` | Body `{item_type, item_id}`; menambah jika belum ada, menghapus jika sudah ada; respons `{success, data: {is_wishlisted}}` |
| `DELETE` | `/api/wishlist/{id}` | Hapus satu baris favorit; `404` jika bukan milik user |

Toggle dibatasi `throttle:30,1`; daftar dan hapus `throttle:60,1`/`30,1`.
`item_type` divalidasi enum, `item_id` harus ada di tabel yang sesuai. Ringkasan
item pada daftar diambil dengan dua query batch (destinasi dan paket), bukan
query per baris.

### 13.2 `is_wishlisted` pada katalog publik

Endpoint destinasi dan paket tetap publik. Middleware `optional.sanctum`
memasang user hanya jika bearer token valid; token tidak ada/invalid tidak
membuat request gagal. Controller memuat daftar id favorit sekali per request
ke request attribute, lalu `DestinationResource`/`TourPackageResource`
mengembalikan `is_wishlisted` hanya ketika user terautentikasi. Dengan begitu
tidak ada N+1 saat menampilkan grid katalog.

### 13.3 Android

- `WishlistRepository` memanggil ketiga endpoint; `WishlistStore` singleton
  menyimpan override optimistic yang dibagi antar layar.
- `WishlistRules` (pure Kotlin) menghitung flip optimistic, konfirmasi nilai
  server, dan rollback ke nilai sebelumnya.
- `WishlistHeartButton` ditambahkan ke kartu Home/Explore, hero detail
  destinasi/paket, dan baris favorit. Tap langsung mengubah hati; jika request
  gagal, hati kembali ke keadaan semula dan snackbar menampilkan pesan.
- Guest yang menekan hati diarahkan ke login (`onRequireLogin`).
- **Favorit Saya** dibuka dari Profil → *Favorit Saya*, dengan tab Destinasi
  dan Paket, pull state loading/error/empty, serta aksi hapus. Tap item membuka
  detail aslinya.
- Daftar favorit dimuat `per_page=50` per tab; belum ada UI pagination bertahap.

### 13.4 Verifikasi

- Backend: **219 passed**, 1.162 assertions. `WishlistTest` 12 tes / 72 assertions.
- Android: **108 tes, 0 gagal**; `WishlistRulesTest` 10 tes untuk optimistic
  toggle, konfirmasi, rollback, override ganda, dan mapper key.
- `lintDebug`: 0 error, 31 warning; `assembleDebug`: APK berhasil dibuat.
- Migrasi wishlist sudah dijalankan di database SQLite development secara
  aditif (bukan `migrate:fresh`/reset).
- Batasan: verifikasi visual di perangkat/emulator belum dilakukan karena
  `adb`/emulator tidak tersedia; daftar favorit belum memuat halaman berikutnya
  otomatis.

---

## 14. Filter, Sort, dan Pagination Katalog

Eksplorasi katalog sekarang sepenuhnya server-side: Android hanya mengirim
parameter, backend yang memfilter, mengurutkan, dan memotong halaman. Tidak ada
lagi filter atau pencarian yang dijalankan di sisi klien.

### 14.1 Parameter

Berlaku untuk `GET /api/destinations` dan `GET /api/tour-packages`. Parameter
tidak wajib; tanpa parameter, respons sama seperti sebelumnya (halaman 1, 15
baris) ditambah blok `meta`.

| Parameter | Nilai valid | Perilaku |
|---|---|---|
| `q` | teks ≤ 255 | Cari nama/kota/negara/deskripsi. Alias lama `search` tetap diterima. |
| `category` | `pantai`, `gunung`, `budaya`, `kuliner`, `alam`, `kota` | Destinasi memakai kolom sendiri; paket mengikuti kategori destinasi. |
| `location` | teks | Kota/negara/nama (paket: milik destinasi induknya). |
| `country` | teks | Filter lama, pencocokan persis. |
| `destination_id` | integer | Khusus paket. |
| `min_price`, `max_price` | angka ≥ 0 | Untuk destinasi: ada minimal satu paket di rentang itu. |
| `min_rating` | 0–5 | Rata-rata ulasan `published`; ulasan `hidden` tidak dihitung. |
| `duration` | `1`, `2-3`, `4+` | Untuk destinasi: ada paket dengan durasi tersebut. |
| `available_date` | `YYYY-MM-DD` | Ada sisa kursi pada tanggal itu. |
| `sort` | `newest`, `price_asc`, `price_desc`, `rating_desc`, `popular` | Default `newest`. |
| `page`, `per_page` | ≥ 1, 1–50 | Default 1 dan 15. |

Nilai di luar daftar, tanggal yang salah format, `per_page > 50`, atau
`max_price < min_price` menghasilkan `422` dengan pesan per field.

Bentuk respons:

```json
{
  "success": true,
  "data": [],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 0 }
}
```

### 14.2 Implementasi backend

- **Migrasi aditif** `2026_10_25_000005_add_category_to_destinations_table.php`
  hanya menambah kolom `category` (nullable, ber-index) bila belum ada.
  `destinations.city`, `destinations.country`, dan `tour_packages.duration_days`
  sudah ada sejak skema awal, jadi tidak diduplikasi. Kolom kategori juga
  ditambahkan ke form create/edit admin dan divalidasi terhadap
  `Destination::CATEGORIES`.
- **`DestinationFilter`** dan **`TourPackageFilter`** memusatkan seluruh
  filter, urutan, dan subquery agregat. Keduanya dipanggil controller sebelum
  `paginate()`.
- **`FilterDestinationRequest`** dan **`FilterTourPackageRequest`** memvalidasi
  query string dan memetakan alias lama (`search` → `q`, `country` tetap,
  `destination_id` untuk paket).
- **Rating** memakai subquery `avg` khusus review `published`, sehingga review
  yang disembunyikan admin tidak menaikkan/menurunkan skor maupun lolos filter
  `min_rating`.
- **`popular`** memakai `Booking::scopeSuccessful()`: `payment_status = paid`
  dan `booking_status` `confirmed`/`completed`. Booking `pending`, `cancelled`,
  atau pembayaran `failed`/`expired` tidak dihitung.
- **`available_date`** memakai `AvailabilityService::applyAvailableOnDate()`:
  menghormati override kapasitas, tanggal tutup, dan pelepasan kursi oleh
  pembayaran gagal/kedaluwarsa — semantik yang sama dengan endpoint ketersediaan
  per paket, tetapi diterapkan sebagai constraint SQL agar satu halaman cukup
  satu query.
- **Tanpa N+1**: `withAvg`/`withCount` untuk agregat dan correlated subquery
  untuk urutan. `DestinationResource`/`TourPackageResource` tidak lagi
  lazy-load relasi hanya untuk menghitung `tour_packages_count`/`bookings_count`.
  Ada tes yang membandingkan jumlah query untuk 1 baris dan 5 baris.
- **Seeder** destinasi dan paket ditulis idempoten (`firstOrCreate`), menambah
  variasi harga/durasi/kategori, dan hanya mengisi `category` yang masih kosong
  sehingga data admin tidak tertimpa.

### 14.3 Android Explore

- `CatalogQuery` membangun query map untuk Retrofit `@QueryMap`;
  `CatalogPage`/`CatalogMeta` membawa `data` + `meta`, dan `ApiResponse` kini
  memetakan blok `meta`.
- `ExploreFilters` adalah state murni (tanpa dependensi Android): kategori,
  rentang harga, rating minimum, durasi, tanggal, dan pilihan urutan, plus
  `activeCount` dan `toCatalogQuery()`.
- `ExploreViewModel` memuat destinasi dan paket secara paralel, melakukan
  debounce pencarian 350 ms, menjaga urutan respons dengan penghitung versi
  (respons lama tidak boleh menimpa filter baru), dan menyimpan pencarian +
  filter ke `SavedStateHandle` agar bertahan saat rotasi/navigasi.
- `ExploreScreen` menampilkan chip urutan, bottom sheet filter (chip kategori,
  `RangeSlider` harga, rating minimum, durasi, dan input tanggal
  `YYYY-MM-DD`), badge jumlah filter aktif, tombol reset, dan *infinite scroll*
  dengan indikator loading, error + "Try again", serta empty state yang
  membedakan "belum ada data" dan "tidak ada hasil".
- Home tetap memakai halaman pertama (10 item) dari endpoint yang sama.

### 14.4 Verifikasi

- Backend: **219 passed**, 1.162 assertions; `CatalogFilterTest` 19 tes /
  127 assertions (setiap filter, kombinasi, keempat urutan, pagination,
  `per_page` > 50, validasi, alias lama, `available_date`, review tersembunyi,
  dan jumlah query konstan).
- Android: **108 tes, 0 gagal**; 21 tes baru di `CatalogQueryTest` dan
  `ExploreFiltersTest`.
- `lintDebug`: 0 error, 31 warning; `assembleDebug`: APK berhasil dibuat.
- `pint --test`: lulus tanpa perubahan format.
- Migrasi kategori dijalankan aditif di SQLite development (bukan
  `migrate:fresh`), seeder tidak dijalankan ulang pada database tersebut.
- Batasan: pencarian memakai `LIKE` (bukan full-text), `popular` menghitung
  seluruh riwayat booking berhasil, dan verifikasi visual di perangkat/emulator
  belum dilakukan karena `adb`/emulator tidak tersedia.

---

## 15. Promo dan Voucher

Kode promo tidak pernah dihitung di klien. Android hanya menampilkan pratinjau
yang dikembalikan server dan mengirim kode itu kembali saat booking; backend
memvalidasi ulang di dalam transaksi yang sama dengan pembuatan booking.

### 15.1 Skema

Tiga migrasi aditif, tanpa mengubah atau menghapus data lama:

| Migrasi | Isi |
|---|---|
| `2026_10_25_000006_create_promo_codes_table.php` | `code` unik (huruf besar), `description`, `discount_type` (`percent`/`fixed`), `discount_value`, `max_discount` nullable, `min_transaction`, `usage_limit` nullable, `per_user_limit`, `used_count`, `starts_at`, `expires_at`, `is_active`, `tour_package_id`/`destination_id` nullable, timestamps, soft delete |
| `2026_10_25_000007_create_promo_redemptions_table.php` | `promo_code_id`, `user_id`, `booking_id` unik, `discount_amount`, `status` (`applied`/`released`), timestamps |
| `2026_10_25_000008_add_promo_fields_to_bookings_table.php` | `subtotal_amount` nullable, `discount_amount` default `0`, `promo_code_id` nullable + index |

`total_price` tetap menjadi jumlah akhir yang dibayar. Booking lama tidak
memiliki `subtotal_amount`; `Booking::effectiveSubtotal()` menurunkannya dari
`total_price + discount_amount` sehingga invoice lama tetap terbaca.

### 15.2 Validasi dan perhitungan

`PromoService::validate()` mengembalikan `ok`, nominal, atau `reason` yang stabil:

| Reason | Arti |
|---|---|
| `not_found` | Kode tidak ada |
| `inactive` | Dinonaktifkan admin |
| `not_started` | `starts_at` belum lewat |
| `expired` | `expires_at` sudah lewat |
| `quota_exhausted` | `used_count >= usage_limit` |
| `per_user_limit` | User sudah memakai sebanyak `per_user_limit` |
| `min_transaction` | Subtotal di bawah minimum |
| `not_applicable` | Paket/destinasi tidak cocok dengan batasan kode |

Diskon persen dibatasi `max_discount`; diskon fixed diambil apa adanya. Keduanya
di-clamp ke subtotal, jadi total tidak pernah negatif. `POST /api/promos/validate`
(auth, throttle 30/menit) menerima `{code, tour_package_id, people, date}` dan
mengembalikan `subtotal`, `discount`, `total`.

### 15.3 Booking, kuota, dan pembayaran

- `POST /api/bookings` menerima `promo_code` opsional. Booking dibuat dengan
  `subtotal_amount`, `discount_amount`, `promo_code_id`, dan `total_price` hasil
  hitungan server; nilai harga dari klien diabaikan.
- Di dalam `DB::transaction`, baris promo diambil dengan `lockForUpdate`.
  `PromoService::redeem()` menaikkan `used_count` lewat
  `UPDATE ... WHERE used_count < usage_limit`, sehingga dua request bersamaan
  tidak dapat memakai slot terakhir dua kali.
- `MidtransService::buildSnapParams()` memakai `gross_amount = total_price`
  final; baris `PROMO` negatif ditambahkan hanya sebagai rincian item.
- `BookingObserver` memanggil `releaseForBooking()` ketika booking menjadi
  `cancelled` atau pembayaran `failed`/`expired`. Release bersifat idempoten:
  redemption ditandai `released` sekali, dan `used_count` tidak pernah negatif.
- Jika pembuatan Snap token gagal dan booking dihapus, kuota dilepas lebih dulu
  sebelum baris booking (dan redemption-nya) dihapus.

### 15.4 Admin

Menu **Promo Codes** (`admin.promos.*`, hanya role `admin`) menyediakan daftar
dengan pencarian/status, statistik, form create/edit, detail, riwayat pemakaian
per user/booking, aktif/nonaktif, dan hapus (soft delete). Validasi form mencakup
kode unik, persen ≤ 100, `expires_at` setelah `starts_at`, dan `usage_limit`
tidak lebih kecil dari pemakaian saat ini. Setiap create/update/toggle/delete
dicatat ke activity log. Detail booking admin menampilkan subtotal, kode promo,
diskon, status redemption, dan total dibayar.

### 15.5 Android

- `BookingViewModel` menyimpan pratinjau promo dan memanggil
  `POST /api/promos/validate` lewat `TraveloRepository.validatePromo()`.
- Ringkasan booking menampilkan subtotal, baris promo + diskon, dan total dari
  respons server, dengan input kode + tombol **Apply** dan **Remove**.
  Pratinjau dibersihkan otomatis bila tanggal atau jumlah traveler berubah.
- `PromoErrorMessages` memetakan setiap `reason` ke pesan yang bisa ditindaklanjuti
  (`PromoErrorMessagesTest`); kode promo tidak pernah dihitung lokal.
- E-ticket/booking detail menampilkan subtotal, kode promo, diskon, dan total
  yang dibayar dari payload server.

### 15.6 Verifikasi

- Backend: **219 passed**, 1.162 assertions. `PromoCodeTest` 24 tes / 92
  assertions mencakup semua alasan penolakan, batas `max_discount`, fixed,
  total tidak negatif, kuota dan limit per user pada dua request berurutan,
  release idempoten saat batal/expired, `gross_amount` Midtrans, dan penolakan
  user biasa dari CRUD admin.
- Android: **108 tes, 0 gagal**; 13 tes baru untuk pemetaan alasan promo dan
  format rupiah.
- `lintDebug`: 0 error, 31 warning; `assembleDebug`: APK berhasil dibuat.
- `pint --test`: lulus tanpa perubahan format.
- Migrasi promo dijalankan aditif di SQLite development; seeder tidak dijalankan
  ulang dan data admin tidak ditimpa.
- Batasan: verifikasi visual di perangkat/emulator belum dilakukan karena
  `adb`/emulator tidak tersedia di lingkungan build.

---

## 16. Batas Waktu Pembayaran dan Auto-Expire

Fitur ini memastikan kursi yang ditahan booking `pending` tidak menggantung
selamanya bila traveller tidak menyelesaikan pembayaran.

### 16.1 Backend

- Migrasi aditif `2026_10_25_000009_add_payment_expires_at_to_bookings_table`
  menambahkan `bookings.payment_expires_at` (nullable, index). Booking lama
  tetap `NULL` dan tidak pernah di-expire otomatis.
- `config/travelo.php` → `payment.ttl_minutes` (env
  `TRAVELO_PAYMENT_TTL_MINUTES`, default 60) dan
  `payment.expire_midtrans_transaction` (env `TRAVELO_EXPIRE_MIDTRANS`).
- `BookingController@store` mengisi deadline; payload booking menyertakan
  `payment_expires_at`, `payment_expires_in_seconds`, `payment_expired`, dan
  `server_time`. Endpoint payment-status memakai field yang sama.
- `POST /api/payments/create-snap-token` menolak booking yang deadlinenya sudah
  lewat dengan `422` + `reason=payment_expired`.
- Snap `expiry` mengikuti sisa waktu booking, bukan hardcoded 24 jam.
- `BookingExpiryService` + command `travelo:expire-unpaid-bookings`:
  transaksi per booking, row lock, cek ulang status pembayaran, ubah booking
  ke `cancelled` dan payment ke `expired`, catat activity log, kirim push,
  lalu memanggil API expire Midtrans secara best-effort.
- `BookingObserver` tetap menjadi satu-satunya tempat pelepasan redemption
  promo; `PromoService::restoreForBooking()` memulihkannya bila pembayaran
  terlambat dan booking dihidupkan kembali.
- `MidtransService::syncBookingPaymentStatus()` mengunci baris booking bila
  dipanggil di dalam transaksi, sehingga webhook dan scheduler tidak saling
  menimpa. Webhook `paid` setelah expiry dicek terhadap kapasitas tanggal.

### 16.2 Android

- `PaymentCountdown` (pure Kotlin) menghitung sisa detik dari nilai server +
  elapsed monotonic, memformat `mm:ss`, dan menentukan batas `Kedaluwarsa`.
- `PaymentScreen` menampilkan banner hitung mundur, menonaktifkan pembayaran
  saat habis, dan menampilkan tombol **Pesan ulang**.
- Kartu booking `pending` menampilkan hitung mundur; setelah habis atau saat
  backend sudah `expired`, kartu menampilkan **Kedaluwarsa** + **Pesan ulang**.
- Respons payment-status yang sudah `payment_expired=true` dipetakan ke
  `PaymentOutcome.EXPIRED`, termasuk saat scheduler belum sempat berjalan.
- **Pesan ulang** membuka detail paket agar traveller memesan kursi baru.

### 16.3 Verifikasi

- Backend: **232 passed**, 1.237 assertions. `BookingPaymentExpiryTest` 13 tes
  mencakup TTL saat pembuatan booking, field countdown, penolakan Snap token,
  expiry + pelepasan kuota, booking `paid`/belum jatuh tempo yang tidak
  tersentuh, idempotensi + promo, kegagalan API Midtrans, push + activity log,
  webhook terlambat dengan kursi tersedia, dan webhook terlambat tanpa kursi
  (refund).
- Android: **131 tes, 0 gagal**; `PaymentCountdownTest` 21 tes untuk
  perhitungan sisa waktu, fallback `payment_expires_at - server_time`,
  boundary expired, format `mm:ss`, dan parsing ISO-8601. Tes outcome juga
  mencakup `payment_expired` dari payment-status.
- `lintDebug`: 0 error, 31 warning; `assembleDebug`: APK berhasil dibuat.
- `pint --test`: lulus.
- Migrasi dijalankan aditif pada SQLite development; seeder dan data admin
  tidak dijalankan ulang/ditimpa.
- Batasan: verifikasi visual di perangkat/emulator belum dilakukan karena
  `adb`/emulator tidak tersedia; pengiriman FCM nyata masih belum dapat
  diverifikasi karena `private_key` service account Firebase masih kosong.

---

## 17. Laporan dan Analitik Admin

Halaman laporan khusus `admin` menggabungkan angka pendapatan, booking,
okupansi, pembatalan, pengguna baru, dan rating. Dashboard admin memakai
`ReportService` yang sama (jendela 30 hari terakhir) sehingga angka di kedua
layar tidak bisa berbeda.

### 17.1 Halaman dan filter

| Item | Detail |
|---|---|
| Halaman | `GET /admin/reports` (`admin.reports.index`) |
| Data chart (JSON) | `GET /admin/reports/data` (`admin.reports.data`) |
| Export | `GET /admin/reports/export?format=csv\|pdf` (`admin.reports.export`) |
| Filter | `start_date`, `end_date` (`YYYY-MM-DD`), `period` = `day`/`week`/`month` |
| Default | 30 hari terakhir, `period=day` |
| Batas | `TRAVELO_REPORTS_MAX_RANGE_DAYS` (default 366 hari); tanggal terbalik ditukar otomatis |
| Akses | Middleware `auth` + `role:admin`; staff dan traveller mendapat `403` |

Halaman menampilkan empat kartu (net revenue, booking + cancellation rate,
okupansi, pengguna baru + rating), chart pendapatan, status booking, tren
booking, top paket, pengguna baru, dan okupansi, serta tabel pendapatan per
period, top 5 paket/destinasi, okupansi per paket, pembatalan/refund, dan
distribusi rating. Filter yang tidak valid (`period` asing atau format tanggal
salah) menghasilkan `422` pada endpoint JSON.

### 17.2 Definisi metrik

- **Pendapatan**: diakui saat uang diterima, memakai `payments.paid_at` dengan
  fallback `bookings.created_at` untuk booking lama, dan hanya untuk
  `bookings.payment_status = paid`. Pembayaran `pending`, `failed`, dan
  `expired` tidak pernah masuk pendapatan.
- **Refund**: dihitung pada `booking_change_requests.refunded_at` dengan
  `refund_status = refunded`, ditampilkan sebagai seri/kartu terpisah dan tidak
  pernah dimundurkan ke tanggal penjualan. **Net revenue = gross − refund**.
  Karena refund adalah kejadian tersendiri, net pada satu hari bisa negatif.
- **Top 5 paket/destinasi**: hanya booking `paid` dengan status `confirmed`
  atau `completed`; booking `cancelled`, pending, gagal, dan refund tidak
  dihitung sebagai penjualan.
- **Okupansi**: kursi terisi vs kapasitas untuk rentang tanggal kunjungan.
  Aturannya sama dengan `AvailabilityService`: status `pending`/`confirmed`/
  `completed` menahan kursi, pembayaran `failed`/`expired` melepas kursi,
  override `tour_package_date_availabilities` mengganti kapasitas satu tanggal,
  dan tanggal tutup berkapasitas 0. Kapasitas paket = `max_people × jumlah
  hari` setelah penyesuaian override.
- **Tingkat pembatalan**: booking `cancelled` dibagi seluruh booking yang
  dibuat pada rentang tersebut, dilengkapi status permintaan pembatalan,
  status refund, dan lima alasan pembatalan terbanyak.
- **Pengguna baru**: hanya `users.role = traveller`; akun admin/staff tidak
  dihitung.
- **Rating**: hanya ulasan `published`; ulasan `hidden` tidak masuk rata-rata
  maupun distribusi bintang.

### 17.3 Implementasi

- `App\Services\ReportService` menyediakan method terpisah: `revenue()`,
  `bookings()`, `topPackages()`, `topDestinations()`, `occupancy()`,
  `cancellations()`, `newUsers()`, `ratings()`, serta `summary()`,
  `chartPayload()`, dan `exportSections()`.
- Semua agregat dijalankan dengan `GROUP BY` di database dan hanya hasil
  agregat kecil yang diproses di PHP; tidak ada query per booking (N+1).
  Bucket harian memakai `DATE()` yang tersedia di SQLite dan MySQL.
- Payload `summary()` di-cache `TRAVELO_REPORTS_CACHE_TTL` detik (default 300).
  Cache key memuat rentang tanggal dan period, jadi filter berbeda tidak pernah
  berbagi entri. Export memakai `buildSummary()` tanpa cache agar angka selalu
  terbaru.
- Migrasi aditif `2026_10_26_000001_add_reporting_indexes.php` menambahkan
  indeks `bookings(payment_status, created_at)`,
  `bookings(booking_status, created_at)`,
  `bookings(tour_package_id, booking_date)`, `payments(paid_at)`,
  `booking_change_requests(refund_status, refunded_at)`, dan
  `users(role, created_at)`.
- Chart.js (`chart.js@^4.6.1`) dipasang lewat npm dan dibundel Vite sebagai
  `resources/js/reports.js`; tidak ada CDN. Server mengirim data agregat
  sebagai JSON di halaman (`#report-chart-data`), sehingga browser tidak pernah
  menerima baris booking mentah.
- Konfigurasi: `TRAVELO_REPORTS_CACHE_TTL` dan
  `TRAVELO_REPORTS_MAX_RANGE_DAYS` di `config/travelo.php` bagian `reports`.

### 17.4 Export

- **CSV** (wajib) berisi beberapa section: ringkasan, pendapatan per period,
  booking per status, tren booking, top paket, top destinasi, okupansi,
  pembatalan/refund, pengguna baru, dan rating. File diunduh sebagai
  `travelo-report_<start>_<end>.csv`.
- **Print/PDF** mengembalikan HTML siap cetak (`text/html`, dibuka di tab
  baru). PDF dibuat lewat **Print → Save as PDF** di browser. Ini mengikuti
  mekanisme export yang sudah dipakai `ExportController`; tidak ada dependency
  dompdf yang ditambahkan.

### 17.5 Verifikasi

- Backend: **249 test lulus**, 1.477 assertions. `ReportServiceTest` berisi
  17 test / 240 assertions dengan data terkontrol:
  - pendapatan hanya dari pembayaran `paid` (pending/failed dikecualikan),
    gross 8.500.000, refund 1.000.000, net 7.500.000, refund pada tanggalnya
    sendiri;
  - top paket/destinasi mengabaikan booking belum lunas dan batal;
  - okupansi menghormati override kapasitas dan tanggal tutup;
  - tingkat pembatalan, pengguna baru (traveller saja), dan rating published;
  - cache per kombinasi filter dan bypass cache saat export;
  - filter tanggal/period, validasi `422`, CSV valid, HTML print, dashboard
    yang memakai service yang sama, dan akses `403` untuk staff/traveller.
- `npm run build` berhasil; manifest memuat bundle `reports-*.js` (Chart.js
  ikut ter-bundle).
- `pint --test`: lulus.
- Migrasi indeks dijalankan aditif pada SQLite development; seeder dan akun
  admin tidak dijalankan ulang/ditimpa.
- Batasan: export PDF berupa HTML siap cetak, bukan file PDF biner; verifikasi
  visual di perangkat/emulator tetap belum dilakukan karena `adb`/emulator
  tidak tersedia.

---

## File yang dihapus

Dihapus karena isinya sudah diringkas di atas / hanya histori sementara:
`GOOGLE_SIGNIN_FIX.md`, `GOOGLE_SIGNIN_QUICK_REFERENCE.md`, `IMPLEMENTATION_STATUS.md`, `NGROK_AUTHTOKEN_CEK.md`, `NGROK_CMD_QUICK.md`, `NGROK_SETUP_GUIDE.md`, `PAYMENT_PENDING_FIX.md`, `.aider.chat.history.md`, `Travelo-Backend/MIDTRANS_SETUP.md`, `FIXES_APPLIED.md`, `FINAL_FIX.md`, `BOOKING_PAYMENT_ANALYSIS.md`, `ANDROID_PAYMENT_INTEGRATION.md`, `README_PAYMENT_FIX.md`, `PAYMENT_STATUS_FIX.md`, `PAYMENT_FIX_COMPLETE.md`, `BRAND_GUIDELINES.md`, `LOGO_QUICK_REFERENCE.md`, `Travelo-Backend/README.md` (boilerplate Laravel).
