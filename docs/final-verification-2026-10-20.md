# Travelo — Final Verification Pass

Tanggal: 2026-10-20  
Lingkungan: local development Windows / Laravel + Android debug + Vite  
Ruang lingkup: fungsi, keamanan, kesiapan pengujian. Logo final dan foto destinasi tetap ditunda.

> Tidak ada commit atau push. Tidak ada API key, password, token, private key, atau isi service account yang dicetak dalam laporan ini.

## Ringkasan status

| Area | Status | Catatan |
|---|---|---|
| Backend test suite | TERVERIFIKASI | 38 passed, 187 assertions |
| Android debug build | TERVERIFIKASI | `BUILD SUCCESSFUL` |
| Android lint | TERVERIFIKASI | 0 errors, 25 warnings |
| Vite production build | TERVERIFIKASI | 53 modules transformed |
| `set_password.php` | TERVERIFIKASI + DIPERKUAT | CLI-only, environment-only, validasi email/password |
| Git secret tracking | TERVERIFIKASI | `.env`, service account, `local.properties`, `google-services.json` tidak tracked |
| Firebase token verification | TERVERIFIKASI | Memakai project ID publik; tidak bergantung pada private key |
| Firebase Admin SDK | TERBLOKIR | Service account masih placeholder |
| Rotasi Brevo | BELUM TERVERIFIKASI / ASUMSIKAN BELUM | Tidak ada bukti key baru dipasang |
| OTP inbox nyata | BELUM DIUJI | Tidak ada pembacaan inbox otomatis |
| Pembayaran Midtrans aktual | BELUM DIUJI | Hanya konfigurasi/test otomatis; belum ada transaksi |
| Webhook publik Midtrans | TERBLOKIR | URL ngrok di `.env` tidak sedang meneruskan ke aplikasi |

## 1. Brevo API key

### Temuan

- `MAIL_MAILER=brevo`.
- `BREVO_API_KEY` ada dan bukan placeholder.
- `.env` terakhir diubah: **2026-10-19 11:44:41**.
- Pengiriman OTP live terakhir yang tercatat: **2026-10-20 18:49:29**, log `OTP sent`.
- Log pengiriman tersebut membuktikan key yang dipakai saat itu masih diterima Brevo.
- Tidak ada bukti bahwa key sudah dirotasi. Karena `.env` tidak berubah setelah 19 Oktober, aplikasi masih memakai key lama. Key lama harus dianggap berisiko karena pernah terlihat di output tool lokal.

**Kesimpulan:** status rotasi tidak dapat dipastikan dari repo saja; berdasarkan bukti lokal, **belum ada key baru yang dipasang**. Jangan menganggap rotasi selesai sebelum key baru diuji.

### Langkah manual rotasi

1. Buka dashboard Brevo → **SMTP & API** → **API Keys**.
2. Cabut/revoke key lama yang pernah terekspos.
3. Buat key baru dengan izin pengiriman email transaksional.
4. Perbarui `BREVO_API_KEY` hanya di `Travelo-Backend/.env` lokal atau secret manager deployment. Jangan tempel key ke chat, issue, commit, atau dokumen.
5. Bersihkan cache konfigurasi:
   ```powershell
   php artisan config:clear
   php artisan optimize:clear
   ```
6. Restart proses Laravel/worker jika ada.
7. Jalankan checklist OTP di bagian 5 memakai inbox sungguhan.
8. Setelah key baru terbukti bekerja, pastikan key lama tetap revoked.
9. Audit log lama dan hapus/redaksi jika ada jejak key lama.

## 2. Keamanan `set_password.php`

### Hasil audit

- Lokasi: `Travelo-Backend/set_password.php`, **di luar** `public/`.
- Tidak ada route yang mengarah ke script ini; tidak muncul di `php artisan route:list`.
- Target dan password hanya dibaca dari environment:
  - `TRAVELO_TARGET_EMAIL`
  - `TRAVELO_NEW_PASSWORD`
- Password tidak dicetak.
- Ditambahkan guard:
  - Hanya boleh berjalan dengan `PHP_SAPI === 'cli'`.
  - Request web ke script mengembalikan **404**.
- Ditambahkan validasi:
  - Email target harus valid (`FILTER_VALIDATE_EMAIL`).
  - Password minimal 12 karakter.
  - Email di-trim; password tidak di-trim.

### Bukti

- `php -l set_password.php` → tidak ada syntax error.
- Tanpa environment variable → exit 1 dengan pesan yang aman.
- Email tidak valid → exit 1.
- Password pendek → exit 1.
- Request web langsung ke script → **HTTP 404**.
- Perubahan password admin **belum dijalankan**. Ini pekerjaan manual dan membutuhkan password baru yang tidak boleh dicetak.

### Perintah manual (jangan simpan password di shell history bila memungkinkan)

```powershell
$env:TRAVELO_TARGET_EMAIL = "admin@example.com"
$env:TRAVELO_NEW_PASSWORD = "<password-baru-minimal-12-karakter>"
php set_password.php
Remove-Item Env:TRAVELO_TARGET_EMAIL, Env:TRAVELO_NEW_PASSWORD
```

`create_test_user.php` juga diberi guard CLI-only karena menggunakan Firebase Admin SDK.

## 3. Audit log Laravel lama

### Temuan read-only

- File: `Travelo-Backend/storage/logs/laravel.log`
- Ukuran: **1.944.738 byte**
- Jumlah baris: **9.238**
- Terakhir ditulis: **2026-10-20 19:58:46**
- Baris yang mengandung pola email: **249**
- Baris yang mengandung pola OTP: **70**
- Baris yang mengandung pola nomor telepon Indonesia: **1**
- Tidak ditemukan pola key Brevo/Midtrans/private key yang valid di log.
- Log tetap harus diperlakukan sebagai data sensitif karena berisi email, data pengujian, order/payment, dan jejak internal.
- Beberapa baris `testing.INFO` tanggal 20 Oktober memuat email lengkap dari test environment. Baris OTP lokal terbaru memakai email tersamar; log provider hanya memuat message ID, bukan kode OTP.

### Prosedur aman (belum dijalankan; butuh persetujuan)

1. Hentikan proses yang menulis log, atau pastikan tidak ada request selama proses.
2. Jika retensi diperlukan, buat salinan terenkripsi di luar repo dan akses terbatas. Jangan commit salinan.
3. Lakukan redaksi pada salinan, bukan file asli:
   - email → `[email]`
   - nomor telepon → `[phone]`
   - kode OTP 6 digit dalam konteks OTP → `[otp]`
   - header `Authorization`, `Cookie`, token, dan key → `[redacted]`
4. Verifikasi hasil redaksi dengan hitungan pola, tanpa mencetak isi:
   ```powershell
   Select-String -Path .\storage\logs\laravel-redacted.log `
     -Pattern '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}' |
     Measure-Object
   ```
5. Setelah verifikasi dan persetujuan, pilih salah satu:
   - ganti file asli dengan versi redaksi, atau
   - kosongkan file lama dengan `Clear-Content` lalu biarkan Laravel membuat log baru.
6. Jika log lama memuat secret, rotasi secret tersebut—bukan hanya menghapus log.
7. Untuk deployment: set `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`; aktifkan rotasi/retensi log. Konfigurasi produksi tidak diubah pada pass ini.

## 4. Firebase service account

### Temuan

File: `Travelo-Backend/storage/app/firebase-auth.json`

- JSON valid.
- `type=service_account`.
- `project_id` cocok dengan project `travelo-818bc`.
- `client_email` menggunakan domain service account project tersebut.
- `private_key` **kosong**; `private_key_id` tidak ada.
- Ukuran file: **428 byte** (placeholder).
- File **tidak tracked** dan di-ignore oleh `storage/app/.gitignore`.
- `FIREBASE_CREDENTIALS` di `.env` kosong, sehingga config memakai fallback file placeholder tersebut.

### Fitur yang bergantung pada service account

- `create_test_user.php` untuk membuat user Firebase lewat Admin SDK.
- Pengelolaan user Firebase di masa depan (create/delete/custom claims), jika fitur itu ditambahkan.
- Login, register, dan OTP **tidak** bergantung pada file ini. Verifikasi ID token memakai `IdTokenVerifier::createWithProjectIdAndCache()` dengan `FIREBASE_PROJECT_ID` publik dan cache Google public keys.

### Konfigurasi aman yang dibutuhkan

1. Buat service account key baru hanya jika fitur Admin SDK benar-benar diperlukan.
2. Simpan di luar webroot, misalnya `storage/app/firebase-auth.json` (sudah gitignored).
3. Batasi permission file ke user proses Laravel.
4. Jangan menaruh file JSON service account di `public/`.
5. Jangan commit, mengirim lewat chat, atau menempel private key ke issue.
6. Untuk deployment, gunakan secret manager atau Workload Identity/ADC bila tersedia. Jika memakai `FIREBASE_CREDENTIALS`, arahkan ke path absolut yang aman.
7. Jangan membuat kredensial palsu. Placeholder saat ini harus dibiarkan sampai key asli tersedia.

## 5. Checklist OTP dengan inbox sungguhan

**Status saat ini:** belum dijalankan pada pass ini. Pengiriman live terakhir hanya membuktikan Brevo menerima request; isi inbox tidak dibaca otomatis dan verifikasi kode dengan inbox nyata belum dibuktikan.

Prasyarat:

- Key Brevo baru sudah dipasang dan `config:clear` sudah dijalankan.
- Inbox uji nyata dapat diakses manual oleh penguji.
- Gunakan akun uji, bukan akun customer produksi.
- `MAIL_MAILER=brevo`, `APP_URL` benar, dan backend aktif.
- Jangan mencetak ID token, kode OTP, atau isi email ke log/chat.

Langkah:

- [ ] Login Firebase memakai akun uji dan dapatkan ID token baru. Jangan memakai token kedaluwarsa.
- [ ] Kirim `POST /api/auth/otp/send` dengan body `{"id_token":"<token>"}`.
- [ ] Pastikan respons **200**, `success=true`, email penerima tersamar, `expires_in=300`, `resend_after=60`; respons tidak memuat kode.
- [ ] Buka inbox manual. Pastikan email masuk, subjek/isi sesuai locale, dan kode 6 digit terlihat.
- [ ] Cek log: hanya email tersamar dan status pengiriman; tidak ada kode/token.
- [ ] Segera kirim ulang (`POST /api/auth/otp/resend`). Harapkan **429** dengan `retry_after > 0`; hanya satu email.
- [ ] Tunggu 60 detik, kirim ulang. Harapkan **200** dan kode lama tidak berlaku.
- [ ] Verifikasi kode baru via `POST /api/auth/otp/verify` dengan `id_token` dan `code`.
- [ ] Pastikan **200**, Sanctum token diterbitkan, `email_verified_at` terisi, dan `login_otps.verified_at` terisi.
- [ ] Replay kode yang sama. Harapkan **400** karena single-use.
- [ ] Uji 5 kode salah. Harapkan percobaan dibatasi dan kode dihapus/ditolak (**429** pada batas).
- [ ] Uji kode kedaluwarsa (>5 menit) di environment uji. Harapkan **400**.
- [ ] Uji rate limit pengiriman (10/menit per route throttle). Harapkan **429**.
- [ ] Pastikan tidak ada kode OTP di respons API atau log.
- [ ] Bersihkan baris OTP uji dari database bila perlu; jangan hapus akun nyata.

Bukti yang harus disimpan: status HTTP, timestamp, email tersamar, dan screenshot email yang sudah diredaksi. Jangan menyimpan kode OTP aktif.

## 6. Checklist pembayaran Midtrans aktual (sandbox)

**Status saat ini:** belum ada transaksi aktual. Test otomatis sudah mencakup mapping status, idempotensi, amount mismatch, dan signature, tetapi itu bukan bukti pembayaran sandbox nyata.

### Konfigurasi yang terverifikasi

- `MIDTRANS_IS_PRODUCTION=false`.
- Server key terdeteksi berformat sandbox (`SB-Mid-server-...`); nilainya tidak dicetak.
- `/api/midtrans/health` lokal → **200**, `config_consistent=true`.
- `APP_URL` memakai HTTPS, tetapi URL ngrok saat ini mengembalikan halaman error **ERR_NGROK_6024**, bukan respons aplikasi. Webhook publik **belum siap** sampai tunnel aktif dan URL di dashboard Midtrans diperbarui.

### Prasyarat

- Gunakan Midtrans **Sandbox**, bukan produksi.
- Tunnel publik aktif dan `APP_URL` cocok dengan URL tunnel.
- Notification URL di dashboard Midtrans: `https://<host-aktif>/api/midtrans/callback`.
- Jangan memakai kartu/rekening nyata. Jangan lakukan transaksi finansial tanpa persetujuan eksplisit.
- Siapkan booking uji dengan nominal yang diketahui.

### Checklist

- [ ] Buat booking uji; catat `booking_id`, `total_price`, dan status awal tanpa mencetak token.
- [ ] Panggil pembuatan Snap token; pastikan `order_id` baru dan nominal Snap = `total_price`.
- [ ] **Pending:** buka halaman Snap, pilih metode pending, jangan selesaikan. Pastikan `payments.payment_status=pending`, `bookings.payment_status=pending`, `booking_status=pending`, dan e-ticket belum tersedia.
- [ ] **Sukses sandbox:** selesaikan pembayaran dengan metode/kartu sandbox yang disetujui. Pastikan webhook `settlement`/`capture+accept` diterima dengan signature valid.
- [ ] Setelah sukses: `payments.payment_status=paid`, `bookings.payment_status=paid`, `booking_status=confirmed`, `paid_at` terisi, amount sesuai, e-ticket dapat diterbitkan.
- [ ] Verifikasi e-ticket lewat `POST /api/tickets/verify`; pastikan referensi dan status valid.
- [ ] **Gagal:** uji `deny`/`failure`/`cancel` (sandbox). Pastikan payment `failed`, booking `cancelled`, e-ticket tidak terbit.
- [ ] **Expired:** uji `expire`. Pastikan payment `expired`, booking `cancelled`, e-ticket tidak terbit.
- [ ] **Nominal salah:** kirim payload uji dengan `gross_amount` berbeda dari booking. Pastikan sistem menolak menandai paid dan mempertahankan status lokal.
- [ ] **Signature salah/kosong:** POST callback tanpa signature valid. Harapkan **401** dan tidak ada perubahan status.
- [ ] **Webhook duplikat:** kirim settlement yang sama dua kali. Harapkan idempoten, satu payment row, `paid_at` tidak berubah.
- [ ] **Out-of-order:** kirim `pending` setelah `paid`. Pastikan status tidak turun dari paid.
- [ ] **Sinkronisasi booking:** uji `POST /api/bookings/{id}/payment/verify-and-sync` dan `retry-sync`; pastikan hanya pemilik booking yang bisa mengakses.
- [ ] Cek log: order ID boleh ada, tetapi server key, Snap token, signature, dan payload sensitif tidak boleh tercetak.
- [ ] Setelah pengujian, hapus booking/payment uji bila disetujui; jangan hapus data customer nyata.

Bukti yang disimpan: status HTTP, order ID tersamar, status DB sebelum/sesudah, screenshot halaman Snap yang diredaksi, dan hasil verifikasi e-ticket. Jangan mencetak server key atau token.

## 7. Test dan build yang dijalankan ulang

### Backend

```powershell
php artisan test --compact
```

Hasil:

```text
Tests:    38 passed (187 assertions)
Duration: 5.06s
```

Termasuk `OtpFlowTest`, `PaymentFlowTest`, `SecurityFlowTest`, `LocalizationTest`, dan `FirebaseTokenVerifierTest`.

### Android

```powershell
.\gradlew.bat :app:assembleDebug :app:lintDebug --console=plain
```

Hasil:

- `BUILD SUCCESSFUL`
- `0 errors, 25 warnings`
- 24 `GradleDependency`
- 1 `OldTargetApi`
- APK: `Travelo-Android/app/build/outputs/apk/debug/app-debug.apk`

### Vite

```powershell
npm run build
```

Hasil:

- `vite v7.3.1`
- 53 modules transformed
- `built in 5.07s`
- output `public/build/assets/app-*.css` dan `app-*.js`

## 8. Git dan secret tracking

- `.env` → ignored oleh `Travelo-Backend/.gitignore`.
- `storage/app/firebase-auth.json` → ignored oleh `storage/app/.gitignore`.
- `Travelo-Android/local.properties` → ignored oleh root `.gitignore`.
- `Travelo-Android/app/google-services.json` → ignored oleh root `.gitignore`.
- Hanya `Travelo-Backend/.env.example` yang tracked.
- `.env.example` memakai nilai kosong/placeholder; `MAIL_PASSWORD=null` bukan secret.
- Scan tracked file untuk pola key hanya menemukan referensi prefix di komentar/validasi kode, bukan key aktif.
- Tidak ada commit atau push.
- Working tree tetap memiliki perubahan yang sudah ada; tidak ada file pengguna yang dihapus.

Catatan kebersihan: `Travelo-Backend/_diag_midtrans.php` dan `_diag_payments.php` adalah script diagnostik untracked yang mencetak data payment/booking. Script ini tidak berada di `public/` dan tidak punya route, tetapi sebaiknya dihapus setelah tidak diperlukan (dengan persetujuan). Belum dihapus pada pass ini.

## 9. Risiko dan tindakan manual yang tersisa

1. **Brevo key belum terbukti dirotasi.** Rotasi di dashboard, update `.env`, lalu jalankan checklist OTP inbox.
2. **Password admin dev belum dirotasi.** Gunakan `set_password.php` dengan environment; jangan mencetak password.
3. **Log lama masih mengandung PII.** Ikuti prosedur bagian 3 dan minta persetujuan sebelum menghapus/meredaksi.
4. **Service account Firebase masih placeholder.** Admin SDK belum bisa dipakai; jangan membuat kredensial palsu.
5. **Webhook publik Midtrans belum siap.** URL ngrok di `.env` mengembalikan `ERR_NGROK_6024`; aktifkan tunnel, update `APP_URL` dan notification URL Midtrans, lalu ulangi checklist sandbox.
6. **OTP inbox nyata belum diuji.** Jangan mengklaim OTP berhasil sampai email benar-benar diterima dan kode diverifikasi.
7. **Pembayaran aktual belum diuji.** Jangan mengklaim pembayaran berhasil sampai transaksi sandbox diselesaikan dan status/e-ticket terverifikasi.
8. **Konfigurasi lokal masih debug.** `APP_DEBUG=true` dan `LOG_LEVEL=debug` sesuai untuk lokal; jangan dipakai di produksi.
9. **Logo final dan foto destinasi tetap ditunda** sesuai keputusan sebelumnya.

## Pernyataan batasan

Pass ini memverifikasi kode, test otomatis, build, lint, konfigurasi non-rahasia, dan bukti log yang tersedia. Pass ini **tidak** membuktikan keberhasilan OTP ke inbox nyata atau pembayaran Midtrans aktual. Keduanya memerlukan tindakan manual/external dengan kredensial dan persetujuan yang sesuai.
