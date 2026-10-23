# Laporan Pemulihan Upload Dokumen Identitas — 2026-10-21

Dokumen ini melengkapi `docs/laporan-audit-2026-10-21.md`. Fokus pass ini adalah
satu masalah audit: booking dapat sudah dibuat, tetapi upload dokumen identitas
gagal atau proses Android mati sebelum upload selesai. Tidak ada booking baru
yang boleh dibuat saat pengguna melanjutkan.

## Ringkasan keputusan

- Antrean pemulihan disimpan di SharedPreferences terpisah:
  `travelo_document_recovery`.
- Yang disimpan hanya `bookingId`, `userId` (id backend), judul tour, jumlah
  penumpang, waktu dibuat, dan waktu percobaan terakhir.
- Tidak ada nomor identitas, isi dokumen, URI picker, nama file asli, path
  privat, token, atau kredensial di antrean.
- File tidak ikut disimpan. Setelah restart pengguna memilih file lagi; ini
  menghindari asumsi bahwa URI lama masih bisa dibaca.
- Sumber kebenaran dokumen tetap backend. Layar pemulihan memuat daftar visitor
  dan hanya mengunggah baris yang `hasDocument == false`.
- Upload tetap dijalankan saat aplikasi terbuka (tidak memakai WorkManager).
  Tidak ada bearer token permanen di input worker karena tidak ada worker.
- Logout tidak menghapus antrean, tetapi antrean difilter dengan user id dan
  endpoint backend tetap owner-scoped.

## Perubahan file

### Android — baru

| File | Isi |
|---|---|
| `Travelo-Android/app/src/main/java/com/naylaaisyah/traveloapp/data/recovery/DocumentRecoveryStore.kt` | Model job, aturan antrean murni, codec JSON, dan penyimpanan SharedPreferences terpisah. |
| `Travelo-Android/app/src/main/java/com/naylaaisyah/traveloapp/ui/screens/my_bookings/CompleteDocumentsScreen.kt` | Layar **Lengkapi Dokumen**; memuat status visitor, memilih ulang file, dan menampilkan status loading/offline/error/selesai. |
| `Travelo-Android/app/src/main/java/com/naylaaisyah/traveloapp/ui/screens/my_bookings/CompleteDocumentsViewModel.kt` | Memuat ulang status backend, hanya mengunggah dokumen yang kurang, mencegah double upload, dan memakai booking yang sudah ada. |
| `Travelo-Android/app/src/main/java/com/naylaaisyah/traveloapp/util/DocumentFilePicker.kt` | Helper bersama untuk validasi file, pembacaan byte saat upload, dan penanganan URI kedaluwarsa. |
| `Travelo-Android/app/src/test/java/com/naylaaisyah/traveloapp/data/recovery/DocumentRecoveryRulesTest.kt` | 14 unit test JVM untuk antrean, status, ownership, legacy, dan e-ticket. |

### Android — diubah

| File | Perubahan |
|---|---|
| `.../ui/screens/booking/BookingViewModel.kt` | Menyimpan job sebelum upload pertama, melewati visitor yang sudah punya dokumen, memperbarui status setelah upload sukses, dan menghapus job hanya setelah semua dokumen lengkap. |
| `.../ui/screens/booking/BookingScreen.kt` | Memakai helper picker bersama; tidak menyimpan URI ke antrean. |
| `.../ui/screens/my_bookings/MyBookingsViewModel.kt` | Menampilkan job lokal, merekonsiliasi dengan daftar booking backend, dan menghapus job yang sudah selesai/tidak valid. |
| `.../ui/screens/my_bookings/MyBookingsScreen.kt` | Kartu **Lengkapi Dokumen** untuk booking yang dokumennya kurang; tombol e-ticket disembunyikan sampai dokumen lengkap. |
| `.../ui/screens/home/HomeViewModel.kt` | Mengekspos job pemulihan milik user yang sedang login. |
| `.../ui/screens/home/HomeScreen.kt` | Banner setelah restart dengan aksi langsung **Lengkapi Dokumen**. |
| `.../ui/navigation/Screen.kt` | Route `complete_documents/{bookingId}`. |
| `.../ui/navigation/NavGraph.kt` | Wiring route pemulihan dari Home dan My Bookings. |
| `.../res/values/strings.xml`, `.../res/values-in/strings.xml` | Teks UI baru, termasuk label **Lengkapi Dokumen**. |
| `DOKUMENTASI.md` | Keterbatasan lama “belum ada pemulihan upload” diganti dengan desain yang sudah diterapkan dan catatan belum diuji di perangkat. |

### Backend — test

| File | Perubahan |
|---|---|
| `Travelo-Backend/tests/Feature/TicketLifecycleTest.php` | Test baru: e-ticket tetap 409 saat baru satu dari dua dokumen terunggah, lalu terbuka setelah dokumen kedua. Memastikan dua visitor, bukan duplikat. |
| `Travelo-Backend/tests/Feature/VisitorIdentityTest.php` | Menambah assertion bahwa upload pengganti tetap satu baris visitor dan tidak membuat baris/file duplikat. |

Tidak ada perubahan kode produksi backend pada pass ini. Endpoint owner-scoped
dan aturan e-ticket yang sudah ada dipakai ulang.

## Desain pemulihan

### 1. Kapan job dibuat

Job dibuat segera setelah `POST /bookings` sukses dan sebelum upload dokumen
pertama. Jika proses mati tepat setelah booking dibuat, referensi booking sudah
tersimpan.

Jika upload sukses seluruhnya, job dihapus. Jika ada kegagalan jaringan,
validasi, atau proses berhenti, job tetap ada.

### 2. Apa yang ditampilkan setelah restart

- Home menampilkan banner jika ada job milik user yang login.
- My Bookings menampilkan kartu **Lengkapi Dokumen** untuk:
  - booking dari backend yang visitor-nya belum lengkap, dan
  - job lokal yang belum ada di daftar backend (misalnya saat offline).
- E-ticket tidak ditampilkan sebagai aksi jika dokumen belum lengkap. Backend
  tetap menolak dengan 409, jadi UI tidak menjadi satu-satunya gate.

### 3. Alur layar pemulihan

1. Buka `complete_documents/{bookingId}` memakai booking id yang sudah ada.
2. Muat visitor dari endpoint owner-scoped.
3. Tampilkan status tiap visitor: belum ada, menunggu review, sudah diperiksa,
   ditolak, atau dihapus karena retensi.
4. Hanya visitor tanpa dokumen yang boleh dipilihkan file.
5. Sebelum upload, status visitor dibaca ulang dari backend. Ini mencegah
   upload ulang jika respons sebelumnya hilang tetapi server sudah menyimpan
   file.
6. Upload dijalankan berurutan; tombol dikunci selama proses.
7. Jika semua dokumen sudah ada, job dihapus dan layar menampilkan status
   selesai. Jika sebagian masih gagal, job dipertahankan.

### 4. URI lama dan pilih ulang

URI picker tidak pernah masuk ke penyimpanan persisten. Di dalam sesi, file
dibaca saat tombol upload ditekan. Jika URI sudah tidak valid, pembacaan
mengembalikan `null`, pilihan tersebut dihapus, dan pengguna diminta memilih
ulang. Setelah restart pengguna memang memilih file lagi.

### 5. Logout, login, dan akses lintas user

- `SessionManager.clearSession()` membersihkan file `travelo_prefs`, bukan file
  `travelo_document_recovery`.
- Job difilter dengan `SessionManager.userId` (id user backend).
- Endpoint visitor tetap memakai `$request->user()->bookings()`, sehingga
  booking milik akun lain menghasilkan 404.
- Login kembali dengan akun yang sama akan menampilkan job yang sama.

### 6. Booking lama

Booking tanpa baris visitor dianggap kompatibel dan tidak membutuhkan aksi.
E-ticket booking lama yang sudah dibayar tetap dapat dibuka. Booking dengan
baris visitor wajib memiliki dokumen pada setiap baris dan jumlah baris yang
sesuai.

## Hasil pengujian

| Pemeriksaan | Perintah | Hasil |
|---|---|---|
| Unit test Android | `.\gradlew.bat :app:testDebugUnitTest --console=plain` | **BUILD SUCCESSFUL**; 14 test, 0 failure |
| Build debug | `.\gradlew.bat :app:assembleDebug --console=plain` | **BUILD SUCCESSFUL**; APK 26.377.122 byte |
| Lint debug | `.\gradlew.bat :app:lintDebug --console=plain` | **BUILD SUCCESSFUL**; 0 error, 26 warning |
| Test backend terkait | `php artisan test tests/Feature/VisitorIdentityTest.php tests/Feature/TicketLifecycleTest.php` | **29 passed, 165 assertions** |
| Suite backend penuh | `php artisan test` | **78 passed, 394 assertions** |
| Whitespace diff | `git diff --check` | Bersih |
| Perangkat | `adb devices -l` | Hanya `emulator-5554`; tidak ada perangkat fisik |

Unit test Android mencakup:

- job bertahan setelah encode/decode (restart),
- job tidak memuat URI, nama file, nomor identitas, path, atau token,
- retry memperbarui satu job dan tidak menduplikasi booking,
- kegagalan tidak menghapus job; hanya penyelesaian yang menghapus,
- job tetap ada setelah logout dan hanya terlihat oleh user pemilik,
- hanya visitor `hasDocument == false` yang diunggah,
- URI lama tidak dipercaya; setelah pilih ulang, rencana upload siap tanpa booking baru,
- respons yang hilang tidak menyebabkan upload ulang,
- booking dengan dokumen sebagian masih butuh aksi,
- booking yang dibuat tetapi upload belum mulai tetap butuh aksi,
- booking lama tanpa visitor tetap kompatibel,
- jumlah visitor tidak cocok memblokir penyelesaian,
- aksi e-ticket terkunci saat belum bayar atau dokumen belum lengkap,
- booking lama yang sudah bayar tetap bisa membuka e-ticket.

Test backend menambahkan bukti bahwa:

- e-ticket tetap 409 saat baru sebagian dokumen terunggah,
- e-ticket terbuka setelah semua dokumen yang kurang terunggah,
- upload pengganti tidak menambah baris visitor duplikat.

## Status per kriteria

| Kriteria | Status | Bukti/catatan |
|---|---|---|
| Metadata upload tersimpan setelah app ditutup/proses mati/restart | **PASS** | SharedPreferences terpisah; unit test round-trip. Force-stop fisik belum diuji. |
| Kehilangan jaringan tidak menghapus pekerjaan | **PASS** | Job hanya dihapus setelah semua dokumen ada; unit test. |
| Setelah restart tampil booking yang perlu tindakan + tombol **Lengkapi Dokumen** | **PASS** | Banner Home, kartu My Bookings, route pemulihan. E2E perangkat **BLOCKED**. |
| Pilih ulang file jika URI lama tidak valid | **PASS** | URI tidak dipersistensi; kegagalan baca menghapus pilihan dan meminta pilih ulang. Instrumentasi picker **BLOCKED**. |
| Hanya upload dokumen yang masih kurang | **PASS** | `hasDocument` filter di BookingViewModel dan CompleteDocumentsViewModel; test backend parsial. |
| Upload parsial tidak membuat booking/upload duplikat | **PASS** | Booking id dari navigasi; refresh sebelum upload; guard `uploading`; test row count dan upsert. |
| Logout lalu login kembali | **PASS** | File antrean terpisah dari sesi; filter user id; unit test. |
| Akses lintas user | **PASS** | Filter user id lokal + endpoint owner-scoped; test backend existing. |
| Booking lama tetap kompatibel | **PASS** | Visitor kosong = tidak butuh aksi; unit test dan test e-ticket legacy. |
| E-ticket tetap terkunci sampai syarat terpenuhi | **PASS** | UI menyembunyikan aksi; backend 409; test parsial. |
| Loading, offline, error, pembatalan, double-upload aman | **PASS** | State loading/error, retry, guard double upload, job tetap saat dibatalkan. E2E perangkat **BLOCKED**. |
| Tidak ada token/nomor identitas/isi dokumen di antrean | **PASS** | Model dan unit test isi JSON; tidak ada token di input worker karena tidak memakai WorkManager. |
| Dokumen tetap privat; `pending` bukan `verified` | **PASS** | Tidak ada perubahan storage; status hanya ditampilkan sebagai menunggu review. |
| Build, lint, unit test, test backend | **PASS** | Lihat tabel hasil. |
| Uji emulator/perangkat terautentikasi | **BLOCKED** | Hanya emulator yang tersedia; tidak ada kredensial Firebase sintetis dan sesi stabil. |

## Risiko residual

1. **Belum ada uji end-to-end terautentikasi di perangkat/emulator.** Build,
   unit test, dan lint lolos, tetapi alur picker → upload → restart belum
   dijalankan dengan akun nyata.
2. **Jendela `SharedPreferences.apply()`.** Penulisan job memakai `apply()`;
   force-stop ekstrem tepat setelah booking dibuat dapat kehilangan job lokal.
   Mitigasi: My Bookings juga mendeteksi booking tidak lengkap langsung dari
   data backend, jadi recovery masih bisa ditemukan saat online.
3. **Rekonsiliasi menghapus job jika booking tidak ada dalam respons sukses
   `/my-bookings`.** Saat ini endpoint mengembalikan semua booking user. Jika
   suatu saat endpoint diberi pagination, logika ini perlu disesuaikan.
4. **Kasus jumlah visitor tidak cocok.** My Bookings akan menampilkan aksi,
   tetapi layar pemulihan hanya bisa mengunggah baris visitor yang ada. Kasus
   ini tidak normal karena backend membuat visitor dalam transaksi; belum ada
   alur untuk membuat baris yang hilang.
5. **Antrean bertahan setelah logout dan disimpan di penyimpanan privat app.**
   Tidak ada enkripsi tambahan; isinya hanya booking id, judul tour, jumlah
   penumpang, dan waktu. Perangkat yang sudah di-root/compromised tetap dapat
   membaca prefs privat.
6. **Tidak ada retry otomatis di background.** Upload dilanjutkan oleh pengguna
   saat aplikasi dibuka. Ini disengaja agar tidak menyimpan bearer token
   permanen.
7. **Pembayaran Midtrans sandbox dan scanner admin belum diuji** pada pass ini;
   keduanya tetap keterbatasan yang sudah tercatat di audit sebelumnya.
