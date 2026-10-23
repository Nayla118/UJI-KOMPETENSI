# Laporan pengembangan halaman Profile — 22 Oktober 2026

## Ringkasan

Halaman **Profile** Android dirapikan dan ditambah fitur yang benar-benar
memakai data atau aksi yang sudah tersedia. Tidak ada tombol dekoratif yang
hanya memanggil lambda kosong, dan tidak ada data identitas lengkap atau path
dokumen yang ditampilkan.

## Perubahan utama

### 1. Header akun yang lebih jelas

- Avatar dengan ring putih, inisial/photo, dan badge **verified account**.
- Nama, email, dan tombol **Edit profile**.
- Statistik **Trips**, **Upcoming**, dan **Member since** tetap memakai data
  booking/profil dari server.
- Header memakai gradient lembut dan border agar lebih menonjol tanpa
  mengorbankan kontras teks.

### 2. Profile strength

- Persentase kelengkapan dihitung dari nama, email, telepon, tanggal lahir,
  kota, dan ada/tidaknya saved identity.
- Jika masih ada data pribadi yang kosong, muncul aksi **Complete details**
  yang membuka editor profil.
- Jika hanya saved identity yang belum ada, UI menjelaskan bahwa identitas
  pemesan akan tersimpan saat booking berikutnya. Ini menghindari tombol yang
  membuka editor padahal yang kurang bukan data profil.

### 3. Next trip

- Booking non-cancelled terdekat dengan tanggal kunjungan hari ini atau
  setelahnya ditampilkan sebagai kartu **UP NEXT**.
- Kartu memuat judul paket, gambar paket bila tersedia, tanggal kunjungan,
  jumlah traveller, total harga, dan status pembayaran/booking dari server.
- Menekan kartu membuka **My bookings**. Jika tidak ada trip mendatang, kartu
  tidak ditampilkan.

### 4. Quick actions

Empat aksi yang semuanya berfungsi:

- **My bookings** — membuka daftar booking.
- **Ticket history** — membuka riwayat tiket used/expired.
- **Edit profile** — membuka bottom sheet edit yang sudah ada.
- **Help & support** — membuka ringkasan bantuan in-app.

### 5. Privacy & data

Sheet baru berisi:

- Penjelasan data profil yang disimpan.
- Ringkasan saved identity dengan nomor tersamar dan status dokumen.
- Penjelasan dokumen privat, review manual, dan retensi 90 hari.
- Aksi **Delete saved identity** dengan konfirmasi yang sudah ada.
- Penegasan bahwa hanya identitas pemesan yang disimpan; traveller tambahan
  tetap diisi manual.

### 6. Saved identity

- Status dokumen sekarang tampil sebagai chip: siap dipakai, menunggu review,
  perlu diganti, atau belum ada.
- Ditambahkan tanggal pembaruan terakhir bila tersedia.
- Nomor identitas tetap hanya empat karakter terakhir.

### 7. Refresh dan info aplikasi

- Pull-to-refresh di halaman Profile.
- Tombol refresh di top bar.
- Dialog **About Travelo** dengan versi aplikasi dari `BuildConfig`.

## File yang ditambah/diubah

| File | Perubahan |
|---|---|
| `ui/screens/profile/ProfileScreen.kt` | Header, quick actions, next trip, privacy/help/about, pull-to-refresh |
| `ui/screens/profile/ProfileExtras.kt` | Komponen profile strength, next trip, quick action, sheet privasi/bantuan, dialog about |
| `ui/screens/profile/ProfileInsights.kt` | Logika kelengkapan profil, next trip, dan label status booking |
| `ui/screens/profile/ProfileViewModel.kt` | Menyimpan `nextTrip` dari booking server |
| `ui/navigation/NavGraph.kt` | Meneruskan aksi menuju Ticket history |
| `app/src/test/.../ProfileInsightsTest.kt` | 10 unit test baru |

## Verifikasi

| Pemeriksaan | Hasil |
|---|---|
| `:app:testDebugUnitTest` | **40 tests**, 0 failures (10 test baru) |
| `:app:lintDebug` | **BUILD SUCCESSFUL** |
| `:app:assembleDebug` | **BUILD SUCCESSFUL** |
| APK | `Travelo-Android/app/build/outputs/apk/debug/app-debug.apk` |
| Backend full suite (tidak berubah oleh pekerjaan Profile) | **97 passed**, 546 assertions |
| `npm run build` (scanner) | **berhasil** |

## Keterbatasan

- Tampilan Profile belum diuji secara visual di emulator/perangkat fisik pada
  iterasi ini karena login membutuhkan akun Firebase/OTP yang valid. Tidak ada
  autentikasi yang dilewati dan tidak ada data uji palsu yang dimasukkan ke DB.
- Pengujian yang dilakukan adalah kompilasi, unit test, lint, dan pembuatan
  APK. Klaim keberhasilan visual di perangkat fisik tidak dibuat.
- Belum ada commit atau push; seluruh perubahan tetap di working tree.
