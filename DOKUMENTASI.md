# Travelo — Dokumentasi Gabungan

Satu file pengganti semua `.md` yang sebelumnya tersebar di project.
File-file lama yang redundan / histori fix sudah dihapus.

Terakhir diperbarui: 2026-10-20

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
Midtrans kirim webhook → POST /api/midtrans/callback → DB jadi paid/confirmed
Android WAJIB panggil verify-and-sync setelah Snap ditutup (jangan hanya andalkan webhook)
```

Webhook di dashboard Midtrans → Settings → Configuration:
```
Payment Notification URL: https://xxxx.ngrok-free.dev/api/midtrans/callback
```

Test kartu sandbox: `4811 1111 1111 1114`, exp `12/2030`, CVV `123`. Atau pakai menu Simulation di dashboard Midtrans.

Aturan penting di Android: **setelah Snap ditutup, langsung panggil `verify-and-sync`**. Kalau masih pending, polling `payment-status` tiap 5 detik (±2 menit), fallback ke `retry-sync`.

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

Logo ada di `Travelo-Backend/public/images/logos/`:
- `travelo-logo-primary.svg` — logo utama untuk background terang
- `travelo-logo-journey.svg`, `travelo-logo-discover.svg` — alternatif
- `travelo-logo-mono.svg` — untuk background gelap
- `travelo-favicon.svg` — favicon

Logo final dari pemilik project belum diberikan. SVG yang ada masih memakai
palet pink lama; jangan menganggapnya final. Setelah logo baru tersedia,
ganti file dengan nama yang sama agar layout admin/login tidak perlu diubah.

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
<img src="/images/logos/travelo-logo-primary.svg" alt="Travelo" width="150" height="40">
<link rel="icon" href="/images/logos/travelo-favicon.svg">
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
- Profile hanya menampilkan aksi yang berfungsi: My Bookings, info akun,
  dan logout. Item Personal Information, Security, Notifications, Saved
  Destinations, Payment Methods, Help Center, Terms, dan Edit Profile yang
  sebelumnya hanya memanggil lambda kosong dihapus, bukan dibiarkan sebagai
  kontrol mati.
- Explore juga kehilangan grid/list toggle, share per kartu, favorit
  sementara, dan dialog pencarian duplikat. Fitur-fitur itu belum punya
  persistence/API; bisa dikembalikan nanti dengan data nyata.
- Offline: `NetworkMonitor` + `ConnectivityViewModel` menampilkan banner
  global di `MainActivity` dengan tombol Retry. Pull-to-refresh ada di Home
  dan My Bookings (Material pull-refresh karena Material3 1.1.2 belum punya
  `PullToRefreshBox`).

### E-ticket dari backend

Booking yang sudah `paid` + `confirmed` menampilkan tombol **View e-ticket**.
App memanggil `GET /api/bookings/{id}/e-ticket`; backend memeriksa ownership,
status pembayaran, status booking, dan `payments.paid_at`, lalu menerbitkan
reference `TRV-######` beserta QR token yang ditandatangani HMAC. App hanya
merender token dari server, tidak lagi membuat tiket lokal. Token dapat
diverifikasi lewat `POST /api/tickets/verify`.

### Admin web

- Layout admin memakai token brand, Nunito/Nunito Sans, sidebar responsif
  dengan menu mobile, backdrop, tombol Escape, focus-visible, dan form logout.
- Warna Tailwind lama di-remap ke palet brand; gradient dekoratif dan
  glassmorphism di header dihapus. Chart dashboard memakai garis Rosewood
  dan area fill datar, bukan gradient biru.
- Login admin punya label, autocomplete, target 48px, pesan error
  danger-tinted, dan logo existing.
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
  `booking_status=confirmed`, dan `payments.paid_at`. QR memakai token HMAC;
  endpoint `POST /api/tickets/verify` tersedia untuk verifikasi.
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
| Test backend | `php artisan test` | **38 passed**, 187 assertions |
| Test OTP khusus | `php artisan test --filter=OtpFlowTest` | **7 passed**, 41 assertions |
| Test Firebase claims | `php artisan test --filter=FirebaseTokenVerifierTest` | **3 passed** |
| Build Android | `gradlew :app:assembleDebug` | **BUILD SUCCESSFUL** |
| Lint Android | `gradlew :app:lintDebug` | **0 error**, 25 warning (24 `GradleDependency`, 1 `OldTargetApi`) |
| Build frontend | `npm run build` | **berhasil** (Vite) |
| Admin pages | login, dashboard, bookings, payments, users, destinations, tour packages, activity logs, import | **200**, CSS build termuat, **tanpa CDN** |
| Lokalisasi | `LocalizationTest` | email OTP + callback **en/id** lolos |
| Migrasi | `php artisan migrate` | kolom password/remember token ditambahkan (no-op di DB dev) |

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

Verifikasi OTP live: `POST /api/auth/otp/send` mengembalikan **200** melalui
Brevo (email disamarkan di respons), dan `POST /api/auth/otp/resend` langsung
mengembalikan **429** karena cooldown. Isi inbox tidak diperiksa otomatis.

### Migrasi & prasyarat

- Migrasi `2026_10_20_000001_add_password_columns_to_users_table` menambahkan
  kolom nullable `password` dan `remember_token`; aman dijalankan di DB lama
  (no-op jika kolom sudah ada) dan diperlukan untuk fresh install/test.
- Jalankan `php artisan migrate` dan `npm run build` sebelum menyalakan admin.
- Variabel yang perlu diisi di `.env` (contoh placeholder ada di
  `.env.example`): `FIREBASE_PROJECT_ID`, `MIDTRANS_SERVER_KEY`,
  `MIDTRANS_IS_PRODUCTION`, `MIDTRANS_MERCHANT_ID`, `BREVO_API_KEY`,
  `MAIL_MAILER`, dan `TRAVELO_ADMIN_PASSWORD` (untuk seeder).
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

---

## File yang dihapus

Dihapus karena isinya sudah diringkas di atas / hanya histori sementara:
`GOOGLE_SIGNIN_FIX.md`, `GOOGLE_SIGNIN_QUICK_REFERENCE.md`, `IMPLEMENTATION_STATUS.md`, `NGROK_AUTHTOKEN_CEK.md`, `NGROK_CMD_QUICK.md`, `NGROK_SETUP_GUIDE.md`, `PAYMENT_PENDING_FIX.md`, `.aider.chat.history.md`, `Travelo-Backend/MIDTRANS_SETUP.md`, `FIXES_APPLIED.md`, `FINAL_FIX.md`, `BOOKING_PAYMENT_ANALYSIS.md`, `ANDROID_PAYMENT_INTEGRATION.md`, `README_PAYMENT_FIX.md`, `PAYMENT_STATUS_FIX.md`, `PAYMENT_FIX_COMPLETE.md`, `BRAND_GUIDELINES.md`, `LOGO_QUICK_REFERENCE.md`, `Travelo-Backend/README.md` (boilerplate Laravel).
