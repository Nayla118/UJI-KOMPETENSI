# Laporan Scanner Tiket & Riwayat Tiket — 2026-10-21

Dokumen ini melengkapi `docs/identitas-dan-tiket-2026-10-21.md` dan
`docs/laporan-pemulihan-upload-2026-10-21.md`. Fokus pass ini adalah tiga hal:

1. Web admin memiliki halaman scanner QR untuk redeem tiket di gerbang, dengan
   pemilihan kamera belakang dan popup hasil scan.
2. Setelah tiket dipakai, tiket tidak lagi tampil sebagai aksi aktif di
   **My Bookings** Android dan berpindah ke **Ticket history**.
3. Traveller mendapat popup satu kali bahwa tiketnya baru saja dipindai.

Tidak ada reset database, migrasi destruktif, commit, atau push pada pass ini.

## Keputusan implementasi

- Scanner memakai endpoint yang sudah ada, `POST /admin/tickets/redeem`, dan
  `TicketService::redeem()` yang atomik. Tidak ada jalur redeem kedua.
- Payload QR adalah token tiket bertanda tangan HMAC yang sudah dipakai
  aplikasi Android.
- Kamera dipilih lewat enumerasi perangkat, dengan preferensi label
  back/rear/environment. Pemilih kamera muncul jika perangkat punya lebih dari
  satu kamera, sehingga kamera depan tidak memaksa pengguna menebak.
- Hasil scan tampil sebagai dialog native (`<dialog>`), bukan panel samping.
  Dialog mendapat fokus, bisa ditutup lewat Escape, klik backdrop, tombol
  Close, atau X, dan tetap berada di atas sidebar admin.
- Status aktif/riwayat Android memakai `ticket_usage_status` dari backend.
  Aplikasi tidak menghitung status pemakaian dari jam lokal atau id booking.
- Snapshot id tiket aktif disimpan lokal. Popup “tiket sudah dipindai” hanya
  muncul untuk tiket yang sebelumnya aktif lalu berubah menjadi `used`; tiket
  lama di riwayat tidak memicu popup palsu saat aplikasi pertama dibuka.
- Tiket `used` selalu masuk riwayat. Tiket `expired` hanya masuk riwayat jika
  pembayarannya sukses/terkonfirmasi; booking yang belum dibayar tidak diberi
  kesan pernah menjadi tiket.
- Riwayat tiket bersifat read-only dan **tidak merender QR**. Tiket yang sudah
  dipakai atau kedaluwarsa bukan e-ticket aktif.
- Halaman scanner hanya untuk `admin` dan `staff`. Traveller tetap mendapat
  403, dan pembatasan `staff_destinations` tetap ditegakkan di service.
- Staff yang login diarahkan ke `/admin/scanner`; admin tetap ke dashboard.
  Menu manajemen disembunyikan dari sidebar staff.

## Backend: halaman scanner

File utama:

- `app/Http/Controllers/Admin/TicketController.php`
- `routes/web.php`
- `resources/views/admin/scanner.blade.php`
- `resources/js/scanner.js`
- `resources/views/layouts/admin.blade.php`
- `app/Http/Controllers/Admin/LoginController.php`

Route baru:

| Method | Path | Hak akses |
|---|---|---|
| `GET` | `/admin/scanner` | `admin` atau `staff` |

Fitur halaman:

- Kamera untuk scan QR, dimuat malas dengan `html5-qrcode@2.3.8`. Pada
  HTTPS/localhost kamera mulai otomatis saat halaman scanner dibuka, tanpa
  menekan **Take QR photo**. QR yang terdeteksi langsung dikirim untuk dicek.
  Tombol **Start camera**, **Take QR photo**, dan input manual tetap tersedia
  sebagai fallback.
- Perangkat kamera dienumerasi sebelum start. Aplikasi memilih kamera belakang
  berdasarkan label (back/rear/environment) dan menyediakan dropdown kamera
  jika ada lebih dari satu pilihan. Ada fallback ke `facingMode: environment`
  bila enumerasi tidak tersedia.
- Area pindai mengikuti ukuran viewfinder (80% sisi terpendek, maksimum 360px)
  dan decoder ZXing dipakai eksplisit. Sebelumnya kotak tetap 250px memotong QR
  yang tampil besar di kamera resolusi tinggi.
- Tombol **Scan QR image** membaca screenshot e-ticket langsung di browser.
  Ini menjadi jalur alternatif saat kamera tidak tersedia/tidak mendeteksi QR;
  gambar tidak diunggah dan hanya token hasil decode yang dikirim ke server.
- Tombol stop/start, status kamera, dan pemilih kamera. Hanya hasil sukses yang
  menampilkan tombol **Scan next ticket** di dalam popup dan di kontrol kamera.
  Hasil gagal—termasuk tanggal kunjungan yang belum sesuai—hanya menampilkan
  peringatan dan tombol **Close**. Setelah popup ditutup, kamera otomatis
  dilanjutkan untuk pemindaian berikutnya; pada sesi kedaluwarsa atau gangguan
  koneksi kamera tidak dilanjutkan otomatis. Status membedakan kamera aktif,
  QR terdeteksi, gambar tidak berisi QR, dan token yang ditolak server.
- Input manual sebagai fallback jika kamera tidak tersedia atau izin ditolak.
  Token dikirim langsung ke server, tidak disimpan di browser, dan input
  dibersihkan setelah pengecekan.
- Setiap hasil — sukses maupun gagal — tampil sebagai popup modal. Hasil sukses
  menampilkan referensi, nama pemesan, destinasi, tanggal kunjungan, dan jumlah
  pelancong. Tidak ada nomor identitas, path dokumen, token, atau data pribadi
  sensitif di tampilan.
- Hasil gagal membedakan `used`, `expired`, `not_paid`,
  `forbidden_destination`, `invalid_token`, sesi kedaluwarsa, dan gangguan
  koneksi.
- CSRF dikirim lewat header; request memakai `credentials: same-origin`.
- Nama pemesan dari server dirender dengan `textContent`, bukan `innerHTML`,
  untuk menghindari XSS.
- Halaman menampilkan cakupan akses. Staff tanpa destinasi mendapat peringatan
  eksplisit bahwa akun belum bisa redeem.

Build frontend berhasil dan menghasilkan chunk scanner terpisah, sehingga
halaman admin lain tidak memuat library kamera.

## Android: My Bookings → Ticket history

File utama:

- `data/tickets/TicketHistoryRules.kt`
- `data/tickets/TicketUsageNoticeRules.kt`
- `data/tickets/TicketUsageNoticeStore.kt`
- `data/models/Models.kt`
- `ui/screens/my_bookings/MyBookingsViewModel.kt`
- `ui/screens/my_bookings/MyBookingsScreen.kt`
- `ui/screens/my_bookings/TicketScannedDialog.kt`
- `ui/screens/my_bookings/TicketHistoryScreen.kt`
- `ui/screens/my_bookings/TicketHistoryViewModel.kt`
- `ui/navigation/Screen.kt`
- `ui/navigation/NavGraph.kt`
- `res/values/strings.xml`, `res/values-in/strings.xml`
- `test/.../data/tickets/TicketHistoryRulesTest.kt`
- `test/.../data/tickets/TicketUsageNoticeRulesTest.kt`

Perilaku:

- `TicketHistoryRules` memisahkan booking aktif dan riwayat berdasarkan
  `ticket_usage_status` dari `/api/my-bookings`.
- Booking `used`/`expired` yang memenuhi aturan di atas tidak lagi muncul di
  daftar aktif atau menampilkan tombol e-ticket.
- My Bookings menyimpan snapshot id tiket aktif. Saat polling/refresh
  berikutnya melihat salah satu id itu berubah menjadi `used`, booking langsung
  hilang dari daftar aktif dan popup **Tiket sudah dipindai** muncul.
- Popup menjelaskan bahwa tiket sudah dipindai di gerbang dan sudah pindah ke
  riwayat, dengan tombol **Lihat riwayat tiket** dan **Mengerti**.
- Selama layar My Bookings aktif, refresh senyap berjalan tiap 10 detik. Jadi
  scan dari perangkat admin tidak perlu menunggu pengguna menarik refresh.
- Snapshot disimpan di preferences sesi, sehingga popup juga muncul setelah
  aplikasi dibuka ulang bila snapshot sebelumnya menunjukkan tiket masih aktif.
  Logout menghapus snapshot bersama sesi.
- Ikon riwayat di top bar My Bookings membuka **Ticket history**.
- Jika semua booking sudah masuk riwayat, My Bookings menampilkan empty state
  “No active bookings” dengan tombol ke Ticket history.
- Ticket history memuat ulang data saat layar kembali aktif, menampilkan status
  tiket, tanggal kunjungan, jumlah pelancong, total pembayaran, dan keterangan
  “Used on …” bila `ticket_used_at` tersedia.
- Riwayat tidak menampilkan QR dan tidak memanggil endpoint e-ticket aktif.
- `ticket_used_at` ditambahkan ke model `Booking` Android, tetapi status tetap
  berasal dari backend.

## Perbaikan tampilan QR Android

QR di dialog e-ticket sebelumnya memakai margin satu modul, warna ink gelap
`#493747`, bitmap 720px, dan tampil 180dp. Perubahan pada pass ini:

- Quiet zone dinaikkan ke empat modul sesuai spesifikasi QR.
- QR memakai hitam murni di atas putih, bukan warna brand.
- Bitmap dinaikkan ke 1024px dan ukuran tampilan mengikuti lebar dialog
  (bukan tetap 180dp), sehingga modul lebih besar di layar ponsel.
- `FilterQuality.None` dipakai agar modul tidak diblur saat diperkecil.

Round-trip ZXing dengan penskalaan nearest-neighbor pada 480, 540, 720, 960,
dan 1080px tetap menghasilkan token yang sama.

Perubahan ini tidak mengubah payload: QR tetap berisi token HMAC yang sama dari
`/api/bookings/{id}/e-ticket`.

## Verifikasi

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test` | **86 passed**, 435 assertions |
| `php artisan test tests/Feature/TicketScannerUiTest.php` | **7 passed**, 29 assertions |
| Uji auto-start kamera Edge headless dengan fake media device | **kamera aktif tanpa klik**, video 640×480, tombol Stop tampil, status “Scanning is automatic” |
| Uji izin kamera ditolak (error `NotAllowedError` disimulasikan) | auto-start berhenti dengan aman, status menampilkan fallback, tombol **Start camera** tetap tampil |
| Uji halaman non-HTTPS (`http://scanner.test`) | auto-start tidak dijalankan, warning live camera tampil, fallback **Take QR photo** tetap tersedia |
| Uji popup sukses/gagal di halaman scanner nyata (respons fetch di-mock) | **dialog terbuka**, detail sukses lengkap, popup `used` dan tanggal tidak sesuai tampil tanpa tombol **Scan next ticket**, Close/X/backdrop/Escape menutup dan kamera dilanjutkan |
| Uji decode QR 1024px dan 720px dengan `html5-qrcode.scanFile()` di Edge headless | **keduanya decode tepat** (token 192 karakter) |
| Round-trip ZXing pada QR 480, 540, 720, 960, 1080px (nearest-neighbor) | **semua decode tepat** |
| `gradlew :app:testDebugUnitTest` | **24 passed**, 0 failures |
| `gradlew :app:assembleDebug` | **BUILD SUCCESSFUL** |
| `gradlew :app:lintDebug` | **BUILD SUCCESSFUL** |
| `npm run build` | **berhasil**; chunk scanner terpisah |
| `git diff --check` | tidak ada whitespace error |

Test scanner mencakup guest redirect, traveller 403, akses staff/admin, cakupan
destinasi, peringatan staff tanpa destinasi, redirect login staff/admin, dan
markup dialog hasil scan. Test riwayat mencakup tiket used, expired berbayar,
tiket aktif, booking belum dibayar, dan pemisahan daftar tanpa duplikasi. Test
popup memastikan tiket yang sebelumnya aktif lalu `used` terdeteksi, sedangkan
tiket lama di riwayat, tiket aktif, dan tiket expired tidak memicu popup.

## Batasan

- Auto-start kamera sudah diuji di Edge headless dengan fake media device,
  termasuk simulasi izin ditolak dan halaman non-HTTPS. Kamera fisik nyata,
  dialog izin perangkat, kamera yang sedang dipakai aplikasi lain, dan
  pemilihan dua kamera di ponsel tetap perlu dicoba di perangkat gate. Browser
  dapat tetap meminta satu ketukan pertama pada perangkat yang belum pernah
  memberi izin kamera.
- Pembacaan screenshot QR diuji lewat `html5-qrcode.scanFile()` di Edge headless
  pada gambar 1024px dan 720px, tetapi interaksi file picker tetap perlu dicoba
  di perangkat gate.
- Tiket yang sudah `used` atau `expired` tetap ditolak backend dan tidak akan
  menghasilkan status sukses; itu adalah perilaku yang benar, bukan kegagalan
  scanner.
- Polling Android berjalan tiap 10 detik selama layar My Bookings aktif. Ini
  bukan push notification; popup muncul pada polling/refresh berikutnya, bukan
  seketika saat tombol scan ditekan di perangkat admin. Notifikasi push
  lintas-perangkat belum dibuat.
- End-to-end Android terautentikasi penuh belum dijalankan ulang karena
  kredensial Firebase sintetis tidak tersedia dan hanya ada emulator tanpa sesi
  stabil. Logika transisi tiket diuji sebagai unit test JVM.
- Pembayaran Midtrans sandbox end-to-end tidak dijalankan ulang.
- Halaman riwayat scan admin (log semua scan) belum dibuat. Yang ada adalah
  hasil scan per tiket dan riwayat tiket di Android.
- Penugasan `staff_destinations` masih melalui database; belum ada UI
  pengelolaan staff.
- `npm install` melaporkan 8 vulnerability pada dependency tree; `npm audit
  fix` tidak dijalankan karena dapat mengubah dependency secara luas.

## Tindak lanjut

1. Uji kamera scanner di Chrome Android/desktop dengan HTTPS dan izin kamera
   nyata, termasuk kondisi izin ditolak, kamera sibuk, pemilih dua kamera, dan
   **Scan QR image**.
2. Uji alur scan -> popup tiket dipindai -> Ticket history di perangkat dengan
   sesi login nyata.
3. Jika popup harus muncul seketika lintas-perangkat, pertimbangkan push
   notification atau refresh interval yang lebih pendek; polling 10 detik
   adalah kompromi saat ini.
4. Pertimbangkan halaman log scan admin jika operasional membutuhkannya.
5. Rotasi kredensial dev sebelum environment dibagikan.
