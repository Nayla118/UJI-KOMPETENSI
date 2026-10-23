# Laporan Audit dan Uji Travelo — 21 Oktober 2026

Dokumen ini adalah hasil audit dan pengujian **implementasi yang sudah ada**. Tidak ada pembangunan ulang fitur, tidak ada commit/push, dan tidak ada reset database.

## 1. Ringkasan Eksekutif

| Area | Status | Ringkasan |
|---|---|---|
| Backend identity, upload, payment, e-ticket | **PASS (otomatis)** | `php artisan test`: **77 passed, 381 assertions**. Fokus keamanan/identitas/tiket: **50 passed, 236 assertions**. |
| Build Android | **PASS** | `:app:assembleDebug` sukses; APK debug 26.167.483 byte. |
| Lint Android | **PASS** | 0 error, 25 warning. |
| Emulator: instalasi + layar login | **PASS** | APK terpasang, Splash/Login tampil. |
| Emulator: login/booking/upload/payment/e-ticket terautentikasi | **NOT RUN / BLOCKED** | Tidak ada kredensial Firebase sintetis; emulator dipakai bersama dan mengalami ANR System UI. |
| Perangkat Android fisik | **BLOCKED** | `adb devices -l` hanya menampilkan `emulator-5554`; tidak ada perangkat USB/fisik. |
| Midtrans sandbox nyata | **BLOCKED** | Server key sandbox ada, tetapi client key Android tidak tersedia dan tidak ada penyelesaian pembayaran nyata. |
| Admin scanner/review UI | **NOT RUN (belum ada)** | Yang tersedia hanya fondasi endpoint backend, bukan UI scanner kamera/antrean review. |
| Pemulihan upload setelah app ditutup | **FAIL (belum diimplementasikan)** | Retry hanya hidup selama layar booking masih hidup. |

**Kesimpulan singkat:** fondasi backend sudah lulus pengujian otomatis dan beberapa celah keamanan sudah ditutup. Namun aplikasi **belum bisa dinyatakan siap produksi atau teruji end-to-end di perangkat fisik**. Klaim pembayaran sandbox, login Firebase nyata, upload dari galeri/kamera, dan scan QR di gerbang tidak boleh diambil dari build/lint saja.

Data dev tidak direset. Kondisi yang teramati: **8 user, 25 booking, 0 baris `booking_visitors`, 25 payment**. Tidak ada data lama yang dihapus pada pass ini.

## 2. Lingkup, Metode, dan Batasan

### Lingkup

- Audit read-only terhadap repository, Git state, route, konfigurasi, migration, scheduler, test, APK debug, dan implementasi Android.
- Verifikasi aturan identitas pengunjung, upload dokumen privat, pembayaran Midtrans, dan e-ticket.
- Perbaikan hanya untuk bug lokal yang jelas dan aman.
- Pengujian memakai data sintetis. Tidak ada KTP/asli, dokumen pribadi, email nyata, atau pembayaran nyata.

### Metode dan bukti

- `git status`, `git diff --stat`, pembacaan kode, `php artisan route:list`, `php artisan migrate:status`, `php artisan schedule:list`.
- `php artisan test` untuk seluruh suite dan subset keamanan/identitas/tiket.
- Probe HTTP lokal untuk endpoint publik, endpoint terproteksi, callback Midtrans, dan fallback storage.
- `gradlew :app:assembleDebug :app:lintDebug`, inspeksi APK dan `BuildConfig`.
- `adb install`, `adb shell am start`, UI dump, dan logcat yang disanitasi.
- Tidak mencetak `.env`, service account, token, password, OTP, URL rahasia, atau PII.

### Batasan dan tindakan yang tidak dilakukan

- Tidak menjalankan `git reset`, `git checkout --`, `git clean`, atau operasi destruktif lain.
- Tidak commit/push.
- Tidak reset database, tidak menghapus booking/user lama, tidak rollback migration.
- Tidak melakukan pembayaran nyata; hanya simulasi payload bertanda tangan di test.
- Tidak membuat webhook palsu tanpa signature.
- Tidak mengubah `travelo.releaseBaseUrl`, kebijakan HTTPS release, atau skema QR.

### Status yang dipakai

- **PASS**: terpenuhi dan ada bukti otomatis atau probe langsung.
- **FAIL**: perilaku yang diharapkan tidak ada/keliru.
- **BLOCKED**: tidak dapat diuji karena perangkat, kredensial, atau environment tidak tersedia.
- **NOT RUN**: belum dijalankan; bukan bukti lulus.

## 3. Temuan Audit dan Perbaikan

| ID | Tingkat | Temuan | Perbaikan | Bukti |
|---|---|---|---|---|
| F-01 | Tinggi | Route panel admin sebelumnya hanya bergantung pada `auth`; akun traveller yang punya sesi web berpotensi masuk area manajemen. | Middleware `EnsureRole` didaftarkan sebagai alias `role`; route manajemen memakai `role:admin`, route tiket/review memakai `role:admin,staff`; login password lokal menolak non-staff/admin. | `bootstrap/app.php`, `routes/web.php`, `Admin/LoginController.php`, test role/traveller. |
| F-02 | Tinggi | Disk `local` memakai `serve => true`. Laravel otomatis mendaftarkan route `/storage/{path}` untuk file privat dengan signed URL. Dokumen identitas berada di disk ini. | `serve => false`. Route privat otomatis hilang; dokumen hanya bisa diunduh lewat controller berautentikasi. | `config/filesystems.php`; `route:list --path=storage` hanya menyisakan `storage.serve`. |
| F-03 | Sedang | Fallback `/storage/{path}` perlu dijamin tidak bisa keluar dari `storage/app/public`. | Validasi `realpath` di dalam direktori publik, `is_file`, dan header `X-Content-Type-Options: nosniff`. | `routes/web.php`; test traversal; probe live 404. |
| F-04 | Sedang | Penggantian dokumen berpotensi menghapus file lama sebelum file baru tersimpan; kegagalan simpan bisa meninggalkan referensi ke file yang hilang. | File baru ditulis lebih dulu, baris DB disimpan, baru file lama dihapus. File baru dibersihkan bila simpan DB gagal. | `VisitorIdentityService::store()`; test replacement. |
| F-05 | Rendah | `TicketService::evaluate()` tidak eager-load `user`, sehingga respons tiket dapat memicu query tambahan. | Tambah eager-load `user`. | `TicketService.php`; test tiket. |
| F-06 | Sedang | Belum ada test eksplisit untuk semua tipe identitas, penolakan password traveller di login admin, dan IDOR `mark-as-paid`. | Tambah test KTP/KIA/kartu pelajar/other, login role, dan IDOR `mark-as-paid`; scope endpoint ke relasi booking milik user sehingga ID asing = 404. | `VisitorIdentityTest`, `SecurityFlowTest`, `PaymentController`. |

Catatan: satu QR mewakili **satu booking** (`TRV-######`), bukan satu QR per pengunjung. Skema token tidak diubah.

## 4. Hasil Uji Backend dan Otomasi

### 4.1 Perintah dan hasil

| Pemeriksaan | Perintah | Hasil |
|---|---|---|
| Seluruh suite backend | `php artisan test` | **77 passed, 381 assertions** |
| Keamanan + identitas + tiket | `php artisan test tests/Feature/SecurityFlowTest.php tests/Feature/TicketLifecycleTest.php tests/Feature/VisitorIdentityTest.php` | **50 passed, 236 assertions** |
| Status migration | `php artisan migrate:status` | Semua migration `Ran`, termasuk `2026_10_21_000001_add_visitor_identities_and_ticket_lifecycle` |
| Scheduler | `php artisan schedule:list` | `travelo:purge-identity-documents` pukul 02:00; tidak dijalankan lebih awal |
| Route storage | `php artisan route:list --path=storage` | Hanya `storage.serve` (fallback publik); route privat otomatis sudah tidak ada |

Test backend memakai SQLite `:memory:` (`phpunit.xml`), sehingga tidak menyentuh database dev.

### 4.2 Probe HTTP lokal

| Probe | Hasil | Status |
|---|---|---|
| `GET /up` | 200 | PASS |
| `GET /api/destinations` | 200 | PASS |
| `GET /api/auth/me` tanpa token | 401 | PASS |
| `GET /api/my-bookings` tanpa token | 401 | PASS |
| `POST /api/bookings` tanpa token | 401 | PASS |
| `POST /api/tickets/verify` dengan token sampah | 200, `valid=false`, `reason=invalid_token` | PASS |
| `POST /api/midtrans/callback` tanpa signature | 401 | PASS |
| `GET /admin` tanpa sesi | 302 ke login | PASS |
| `GET /storage/does-not-exist.txt` | 404 | PASS |
| `GET /storage/..%2F..%2Fcomposer.json` | 404 | PASS |
| `GET /storage/destinations/<file-publik>` | 200 | PASS |

### 4.3 Matriks test plan backend

Kolom “Aktual” merujuk test otomatis, probe, atau review kode. Tidak ada data nyata yang dipakai.

| ID | Prasyarat | Langkah | Harapan | Aktual | Status |
|---|---|---|---|---|---|
| B-01 | User login, paket 2 orang | POST booking dengan 1 visitor | 422, jumlah visitor harus sama | 422 `visitors` | PASS |
| B-02 | User login, paket 2 orang | POST 2 visitor (booker + KIA) | 201, 2 baris, booker benar | 2 baris, booker true | PASS |
| B-03 | User login, paket 4 orang | POST visitor KTP, KIA, kartu pelajar, other | 201, semua tipe diterima | 4 tipe tersimpan | PASS |
| B-04 | User login | KIA tanpa `date_of_birth`; other tanpa label | 422 pada field terkait | 422 | PASS |
| B-05 | User login | Kirim visitor tanpa `privacy_consent` | 422 consent | 422 | PASS |
| B-06 | User login | Buat booking dengan identitas | Nomor asli/path tidak ada di JSON; hanya masked | masked, path hidden | PASS |
| B-07 | Booking milik user | Upload JPG, PNG, PDF sintetis | 201, status `pending` | 201, `pending` | PASS |
| B-08 | Booking milik user | Upload file > 5 MB | 422 | 422 | PASS |
| B-09 | Booking milik user | Upload tipe tidak didukung | 422 | 422 | PASS |
| B-10 | Dokumen sudah ada | Upload pengganti | File baru tersimpan, file lama hilang, status `pending` | Sesuai | PASS |
| B-11 | Dokumen sudah ada | Hapus sebagai pemilik | File privat hilang, status `missing` | Sesuai | PASS |
| B-12 | Dua user berbeda | User B list/upload/download dokumen user A | 404/403, tidak ada kebocoran | Ditolak | PASS |
| B-13 | Dokumen milik user | Download oleh pemilik | `no-store`, `nosniff`, isi benar | Sesuai | PASS |
| B-14 | Dokumen `verified` | Ubah tipe/nomor identitas | Status kembali `pending` | Sesuai | PASS |
| B-15 | Dokumen melewati retensi | Jalankan purge command | File dan path dibersihkan, status `purged` | Sesuai | PASS |
| B-16 | Tidak ada upload nyata | Audit disk/route | Dokumen di private disk, path tidak publik | `local`, `serve=false`, path hidden | PASS |
| B-17 | Booking + payment pending | Total dihitung server dari harga paket × orang | Klien tidak bisa menentukan total | Dihitung di controller | PASS (review) |
| B-18 | Upload terputus di tengah request | Abort koneksi/fault injection | Tidak ada baris/file setengah jadi | Belum diuji | NOT RUN |
| B-19 | Callback `settlement` bertanda tangan | Kirim notifikasi | `paid` + `confirmed` | Sesuai | PASS |
| B-20 | Berbagai metode Midtrans | Kirim payload metode yang didukung | Semua bisa mencapai `paid` | Sesuai | PASS |
| B-21 | Status `pending`/`capture challenge` | Kirim notifikasi | Tetap `pending` | Sesuai | PASS |
| B-22 | Status `expire` | Kirim notifikasi | `expired` | Sesuai | PASS |
| B-23 | Status `cancel`/`deny`/`failure` | Kirim notifikasi | `failed`, booking dibatalkan | Sesuai | PASS |
| B-24 | Settlement duplikat / pending setelah paid | Kirim notifikasi berulang | Idempotent, tidak turun status | Sesuai | PASS |
| B-25 | Nominal salah | Kirim settlement dengan amount berbeda | Tidak menjadi `paid` | Ditolak | PASS |
| B-26 | Tanpa signature / signature salah | POST callback | 401 | 401 | PASS |
| B-27 | Signature valid | POST callback | Diproses | Diproses | PASS |
| B-28 | Booking milik user lain | POST `mark-as-paid` | 404, bukan 403 yang membocorkan keberadaan | 404 | PASS |
| B-29 | Booking belum paid | Buka e-ticket | Ditolak | Ditolak | PASS |
| B-30 | Paid, tapi dokumen pengunjung belum lengkap | Buka e-ticket | 409 + status kelengkapan | 409 | PASS |
| B-31 | Booking lama tanpa baris visitor | Buka e-ticket | Tetap kompatibel | E-ticket terbit | PASS |
| B-32 | Booking paid + confirmed | Buka e-ticket dua kali | Token sama, signature valid | Stabil | PASS |
| B-33 | Token palsu/dimodifikasi | Verifikasi | `valid=false`/authentic false | Ditolak | PASS |
| B-34 | Booking milik user lain | Buka e-ticket | Ditolak | Ditolak | PASS |
| B-35 | QR token | Inspeksi payload | Hanya ID booking/user/reference/issued_at; tanpa identitas/dokumen | Sesuai | PASS (review) |
| B-36 | Dua scan token sama | Redeem dua kali | Hanya satu yang menjadi `used` | Atomik, satu | PASS |
| B-37 | Tiket kedaluwarsa | Verifikasi/redeem | Authentic tapi tidak bisa dipakai; batas hari `Asia/Jakarta` | Sesuai | PASS |
| B-38 | Staff beda destinasi / traveller | Redeem | Staff hanya destinasi sendiri; traveller 403 | Sesuai | PASS |
| B-39 | Pembayaran sukses | Cek status tiket | Tetap `unused` | Sesuai | PASS |
| B-40 | Admin reissue | Reissue tiket | Token lama invalid, token baru valid | Sesuai | PASS |
| B-41 | Staff/admin/traveller | Akses route admin | Manajemen hanya admin; gate staff/admin; traveller ditolak | Sesuai | PASS |
| B-42 | File privat + traversal | Request fallback storage | 403/404, isi privat tidak keluar | 404 | PASS |
| B-43 | Firebase token rusak | Kirim ke verifier/register | Ditolak; register wajib token | Sesuai | PASS |
| B-44 | Email sudah terhubung UID lain | Login | Tidak relink; akun unverified tidak diprovision | Sesuai | PASS |
| B-45 | OTP | Kirim/verifikasi/expiry/attempt/rate limit | Hash, sekali pakai, cooldown, 429 | Sesuai | PASS |
| B-46 | Inbox Firebase/Brevo nyata | Login/OTP end-to-end | Email nyata terverifikasi | Tidak ada kredensial sintetis | BLOCKED |
| B-47 | Midtrans sandbox nyata | Selesaikan pembayaran | Status berubah dari transaksi nyata | Client key/payment UI tidak tersedia | BLOCKED |

## 5. Hasil Uji Android, Emulator, dan Perangkat Fisik

### 5.1 Build dan konfigurasi

| Pemeriksaan | Hasil |
|---|---|
| `:app:assembleDebug` | **BUILD SUCCESSFUL** |
| `:app:lintDebug` | **0 error, 25 warning** (24 `GradleDependency`, 1 `OldTargetApi`) |
| APK debug | `app/build/outputs/apk/debug/app-debug.apk`, 26.167.483 byte |
| `BASE_URL` debug | HTTPS dan sama dengan `travelo.baseUrl` di `local.properties`; bukan `10.0.2.2` |
| Release policy | URL release/HTTPS tidak diubah |
| Midtrans Android | `MIDTRANS_IS_SANDBOX = true`; client key tidak ada di BuildConfig |

### 5.2 Matriks Android

| ID | Prasyarat | Langkah | Harapan | Aktual | Status |
|---|---|---|---|---|---|
| A-01 | Emulator aktif | Install APK debug | Terpasang | `Success` | PASS |
| A-02 | APK terpasang | Launch `SplashActivity` | Masuk layar login | Splash/Login tampil | PASS |
| A-03 | Kredensial Firebase sintetis | Login email/password, register, OTP | Sesi valid, masuk Home | Tidak ada kredensial; emulator shared/ANR | BLOCKED |
| A-04 | Sesi login | Buka Home/Explore/detail | Data destinasi tampil | Belum dijalankan | NOT RUN |
| A-05 | Sesi login | Isi form 2+ pengunjung, semua tipe, KIA DOB | Validasi UI dan server konsisten | Backend PASS; UI belum dijalankan | NOT RUN |
| A-06 | Sesi login | Pilih JPG/PNG/PDF dari galeri/kamera | Upload sukses, status pending | Backend PASS; perangkat belum | NOT RUN |
| A-07 | Sesi login | Upload >5 MB dan tipe salah | Pesan error jelas | Backend PASS; UI belum | NOT RUN |
| A-08 | Dokumen ada | Ganti dan hapus dokumen | File lama hilang, UI sinkron | Backend PASS; UI belum | NOT RUN |
| A-09 | Upload terputus | Putus jaringan di tengah upload | Tidak ada booking ganda/status rusak | Belum dijalankan | NOT RUN |
| A-10 | Booking sudah dibuat, upload gagal | Tekan retry di layar yang sama | Upload ulang memakai booking yang sama | Kode memakai `createdBooking`, tidak membuat booking baru; perangkat belum diuji | NOT RUN |
| A-11 | Booking dibuat, app ditutup sebelum upload | Buka lagi app | Ada cara melengkapi dokumen | Tidak ada antrean persisten/UI pemulihan | **FAIL** |
| A-12 | Booking paid | Buka My Bookings | E-ticket hanya setelah paid | Backend PASS; UI belum dijalankan | NOT RUN |
| A-13 | E-ticket pernah dibuka | Restart app, buka lagi | Token/QR sama | Backend PASS; UI belum | NOT RUN |
| A-14 | Token Sanctum dicabut | Pull-to-refresh | 401 memicu kembali ke login | Kode `SessionManager` ada; perangkat belum diuji | NOT RUN |
| A-15 | Admin/staff | Buka scanner/review | UI kamera/antrean review | UI belum ada; hanya endpoint | NOT RUN |
| P-01 | Perangkat fisik USB | `adb devices -l` | Muncul device fisik | Hanya `emulator-5554` | BLOCKED |
| P-02 | Perangkat fisik | Install + buka app | Berjalan di perangkat | Tidak ada perangkat | BLOCKED |
| P-03 | Perangkat fisik | Uji endpoint ngrok/HTTPS | Bisa mencapai backend | Tidak ada perangkat | BLOCKED |
| P-04 | Perangkat fisik | Upload dari kamera/galeri | Dokumen terkirim | Tidak ada perangkat | BLOCKED |
| P-05 | Perangkat fisik + Midtrans | Selesaikan sandbox | Paid/e-ticket nyata | Tidak ada perangkat/client key | BLOCKED |
| M-01 | Midtrans sandbox | Sukses | Paid/confirmed | Backend signed simulation PASS; nyata belum | BLOCKED |
| M-02 | Midtrans sandbox | Gagal/cancel/expire | Status sesuai | Backend signed simulation PASS; nyata belum | BLOCKED |
| M-03 | Midtrans sandbox | Webhook signature/amount/idempotency | Sesuai kontrak | Backend PASS | PASS (backend) |
| M-04 | Midtrans sandbox | Log tidak memuat secret/PII | Bersih | Review kode PASS; log lama belum dibersihkan | PASS (review) |

### 5.3 Catatan emulator

- Emulator `emulator-5554` adalah **emulator**, bukan perangkat fisik; hasilnya tidak memenuhi syarat uji perangkat fisik.
- Saat pengujian, emulator dipakai bersama/otomatis: sempat muncul **“System UI isn't responding”** dan data aplikasi dibersihkan oleh proses lain.
- Sempat terlihat `MainActivity` tanpa log `AuthViewModel`/Firebase sukses yang cocok. Pemeriksaan prefs menunjukkan sesi milik akun lain sudah ada, **bukan** hasil kredensial sintetis yang dimasukkan. Karena itu status login tidak boleh dianggap PASS.
- Sesi tersebut tidak dipakai untuk menguji booking/upload/payment karena bukan akun uji sintetis yang diketahui.

## 6. Keamanan, Privasi, dan Regresi

### 6.1 Kontrol yang diverifikasi

| Kontrol | Bukti | Status |
|---|---|---|
| Firebase token diverifikasi; token rusak ditolak | Test verifier/register | PASS |
| Register wajib token; email unverified tidak diprovision | Test auth | PASS |
| Login tidak me-relink email ke UID lain | Test auth | PASS |
| OTP hash, expiry, attempt limit, cooldown/rate limit | Test OTP | PASS |
| Endpoint terproteksi mengembalikan 401 | Probe live | PASS |
| Ownership booking/tiket/upload/retry sync | Test IDOR | PASS |
| `mark-as-paid` tidak bisa menyentuh booking orang lain | Test IDOR baru | PASS |
| Upload hanya pemilik; path privat tidak diekspos | Test upload/storage | PASS |
| Webhook Midtrans wajib signature; amount dicek | Test payment/security | PASS |
| QR memakai HMAC; token palsu/dimodifikasi ditolak | Test tiket | PASS |
| Penerbitan tiket mensyaratkan paid/confirmed/dokumen | Test tiket | PASS |
| Pembayaran sukses tidak menandai tiket used | Test tiket | PASS |
| Staff hanya redeem destinasi sendiri | Test tiket | PASS |
| Admin management hanya admin; gate staff/admin | Test role | PASS |
| Storage traversal/private file tidak bocor | Test + probe | PASS |
| Legacy booking tanpa visitor tetap dapat e-ticket | Test tiket | PASS |
| Log baru tidak memuat payload Snap/token/server key/email | Review kode | PASS (review) |
| Session 401 kembali ke login di Android | Review kode; perangkat belum diuji | NOT RUN |

### 6.2 Risiko residual

1. **Migration mempromosikan user lama yang punya password lokal menjadi admin.** Audit dulu apakah ada akun traveller lama dengan password; jika ada, jangan jalankan promosi otomatis tanpa pemetaan manual.
2. **`MIDTRANS_IS_SANDBOX = true` hardcoded di Android.** Release harus diselaraskan dengan mode backend sebelum go-live.
3. **Client key Midtrans Android tidak tersedia.** Pembayaran sandbox nyata belum bisa diuji.
4. **Tidak ada kredensial Firebase sintetis/verifikasi inbox.** Login/OTP nyata belum diuji.
5. **Admin scanner/review UI belum ada.** Fondasi backend tidak sama dengan UI gerbang.
6. **Pemulihan upload setelah app ditutup belum ada.** Booking dapat tertinggal `pending` tanpa tombol unggah ulang di My Bookings.
7. **Log historis** di `storage/logs` mungkin masih memuat data lama. Rotasi/bersihkan sebelum log dibagikan.
8. **Emulator shared** tidak layak menjadi bukti E2E; gunakan perangkat fisik/emulator terisolasi dengan akun sintetis.
9. **Tidak ada commit/push.** Semua perubahan tetap di working tree.

## 7. Keterbatasan, Risiko, dan Pekerjaan yang Belum Selesai

- **Perangkat fisik belum terhubung.** Tidak ada bukti instalasi, endpoint HTTPS/ngrok, kamera/galeri, atau performa di perangkat.
- **Pembayaran nyata belum diuji.** Test hanya memvalidasi kontrak webhook/signature/amount/idempotency dengan payload bertanda tangan.
- **Login Firebase/OTP nyata belum diuji** sampai inbox terverifikasi. Jangan mengklaim OTP end-to-end dari test backend.
- **Upload terputus** belum diuji dengan fault injection; belum ada bukti tidak ada file/baris orphan.
- **Restart sync gagal** secara fungsional: `createdBooking` dan pilihan file hilang saat proses Android mati; My Bookings hanya menampilkan 409 saat e-ticket dibuka, tanpa aksi pemulihan.
- **Admin scanner/review UI** belum ada; `POST /admin/tickets/redeem` dan endpoint review sudah ada, tetapi belum ada layar kamera/input manual/antrean review.
- **Satu QR per booking** sudah didokumentasikan; jangan mengubah skema tanpa migrasi kompatibilitas.
- **Migration promotion** berisiko terhadap akun lama; audit database sebelum deploy.
- **Frontend build** tidak dijalankan ulang pada pass ini karena tidak ada perubahan frontend; fokus pass adalah backend/Android. Build Vite historis bukan bukti pass ini.

## 8. Rekomendasi, Status Akhir, dan Artefak

### Status akhir

| Gate | Status | Syarat lanjut |
|---|---|---|
| Backend automated regression | **PASS** | Pertahankan 77/381 dan 50/236 di CI. |
| Android build/lint | **PASS** | Jalankan ulang di CI setiap perubahan. |
| Emulator install/login screen | **PASS** | Bukan pengganti perangkat fisik. |
| Android authenticated E2E | **BLOCKED** | Butuh akun Firebase sintetis terverifikasi + emulator terisolasi/perangkat. |
| Physical device E2E | **BLOCKED** | Butuh perangkat ADB fisik dan endpoint yang dapat dijangkau. |
| Midtrans sandbox completion | **BLOCKED** | Butuh client key, payment UI, dan transaksi sandbox yang benar-benar diselesaikan. |
| Admin scanner/review UI | **NOT RUN** | UI belum dibuat; backend saja belum cukup. |
| Closed-app upload recovery | **FAIL** | Butuh antrean upload persisten atau layar “lengkapi dokumen”. |

**Go/no-go:** backend layak dilanjutkan ke staging, tetapi **belum layak produksi** sampai perangkat fisik, pembayaran sandbox nyata, UI admin, dan pemulihan upload selesai.

### Prioritas tindak lanjut

1. Sediakan akun Firebase sintetis yang sudah terverifikasi dan inbox uji; ulangi login/register/OTP di emulator terisolasi.
2. Hubungkan perangkat fisik; uji endpoint HTTPS/ngrok, upload kamera/galeri, retry, dan restart.
3. Isi client key Midtrans sandbox; selesaikan skenario sukses/gagal/cancel/expire dan verifikasi webhook nyata.
4. Implementasikan antrean upload/pemulihan dokumen dan aksi “lengkapi dokumen” di My Bookings.
5. Bangun UI scanner QR dan antrean review dokumen; hubungkan ke endpoint yang sudah ada.
6. Audit user lama sebelum menjalankan migration promosi admin; rotasi secret/log lama.
7. Tambahkan Android instrumentation/UI test untuk alur yang saat ini hanya diuji backend.

### Artefak

- `Travelo-Backend/tests/Feature/SecurityFlowTest.php`
- `Travelo-Backend/tests/Feature/VisitorIdentityTest.php`
- `Travelo-Backend/tests/Feature/TicketLifecycleTest.php`
- `Travelo-Backend/app/Http/Middleware/EnsureRole.php`
- `Travelo-Backend/app/Services/VisitorIdentityService.php`
- `Travelo-Backend/app/Services/TicketService.php`
- `Travelo-Backend/config/filesystems.php`
- `Travelo-Backend/routes/web.php`
- `Travelo-Android/app/build/outputs/apk/debug/app-debug.apk`
- `Travelo-Android/app/build/reports/lint-results-debug.txt`
- `DOKUMENTASI.md`
- `docs/identitas-dan-tiket-2026-10-21.md`
- `docs/privacy-identitas-pengunjung.md`

Tidak ada commit, push, reset database, penghapusan data lama, atau pembayaran nyata pada pass ini.
