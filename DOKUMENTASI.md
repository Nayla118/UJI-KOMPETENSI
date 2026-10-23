# Travelo — Dokumentasi Gabungan

Satu file pengganti semua `.md` yang sebelumnya tersebar di project.
File-file lama yang redundan / histori fix sudah dihapus.

Terakhir diperbarui: 2026-10-23

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

Status: `pending` → nunggu bayar, `paid` → booking `confirmed`, `failed`/`expired` → booking `cancelled`.

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
- Halaman `/admin/scanner` tersedia untuk `admin` dan `staff`: kamera QR
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
| `GET` | `/admin/scanner` | `admin` atau `staff` |
| `POST` | `/admin/tickets/redeem` | `staff` sesuai destinasi atau `admin` |
| `POST` | `/admin/bookings/{booking}/ticket/reissue` | `admin` |
| `PATCH` | `/admin/visitors/{visitor}/document-review` | `staff`/`admin` sesuai destinasi |
| `GET` | `/admin/visitors/{visitor}/document` | `staff`/`admin` sesuai destinasi |

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

## File yang dihapus

Dihapus karena isinya sudah diringkas di atas / hanya histori sementara:
`GOOGLE_SIGNIN_FIX.md`, `GOOGLE_SIGNIN_QUICK_REFERENCE.md`, `IMPLEMENTATION_STATUS.md`, `NGROK_AUTHTOKEN_CEK.md`, `NGROK_CMD_QUICK.md`, `NGROK_SETUP_GUIDE.md`, `PAYMENT_PENDING_FIX.md`, `.aider.chat.history.md`, `Travelo-Backend/MIDTRANS_SETUP.md`, `FIXES_APPLIED.md`, `FINAL_FIX.md`, `BOOKING_PAYMENT_ANALYSIS.md`, `ANDROID_PAYMENT_INTEGRATION.md`, `README_PAYMENT_FIX.md`, `PAYMENT_STATUS_FIX.md`, `PAYMENT_FIX_COMPLETE.md`, `BRAND_GUIDELINES.md`, `LOGO_QUICK_REFERENCE.md`, `Travelo-Backend/README.md` (boilerplate Laravel).
