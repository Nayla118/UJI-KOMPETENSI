# Kebijakan Privasi Data Identitas Pengunjung

Versi: **2026-10-22**

Dokumen ini menjelaskan bagaimana Travelo memproses data identitas pengunjung
yang diunggah saat membuat booking. Dokumen ini adalah kebijakan teknis dan
operasional; sebelum dipakai produksi, bagian identitas badan usaha, kontak
DPO/penanggung jawab, dan dasar hukum final perlu ditinjau oleh penasihat
hukum.

## 1. Data yang dikumpulkan

Untuk setiap pengunjung pada satu booking:

- Nama lengkap.
- Jenis identitas: KTP, KIA (Kartu Identitas Anak), Kartu Pelajar, atau
  identitas resmi lain yang diizinkan destinasi.
- Nama jenis identitas lain (bila memilih "lainnya").
- Nomor identitas.
- Tanggal lahir (wajib untuk KIA; opsional untuk jenis lain bila diperlukan
  untuk kategori tiket).
- Nomor telepon pemesan (opsional).
- Satu foto/scan dokumen identitas (JPG, PNG, atau PDF, maksimal 5 MB).

Data pemesan disimpan sebagai baris pengunjung dengan penanda `is_booker`.
Akun pengguna (email Firebase) tetap menjadi pemilik booking.

Untuk pemesan, data di atas juga disimpan sekali di `saved_identities` agar
booking berikutnya tidak perlu mengisi ulang. Salinan dokumen private untuk
akun disimpan terpisah dari salinan per booking.

## 2. Tujuan dan dasar pemrosesan

Data diproses untuk:

- Memastikan jumlah dan identitas pengunjung sesuai dengan tiket yang
  dipesan.
- Mengisi otomatis data pemesan pada booking berikutnya dan memakai ulang
  dokumen yang sudah tersimpan, sehingga pengguna tidak perlu upload berulang.
- Verifikasi manual oleh petugas destinasi saat diperlukan.
- Pencegahan penyalahgunaan tiket dan penyelesaian sengketa.
- Pemenuhan kewajiban operasional destinasi.

Dasar pemrosesan adalah persetujuan pengguna. Saat membuat booking dengan
data pengunjung, aplikasi menampilkan pemberitahuan dan meminta persetujuan.
Waktu persetujuan dan versi kebijakan disimpan di
`bookings.privacy_consent_at` dan `bookings.privacy_consent_version`.

## 3. Penyimpanan dan keamanan

- Dokumen disimpan di **private storage** (`storage/app/private` melalui disk
  `local`), bukan di direktori publik dan tidak dapat diakses lewat
  `/storage/...`.
- Nama file dibuat acak oleh server; nama file asli dari perangkat tidak
  dipakai sebagai nama penyimpanan.
- Tipe dan ukuran file divalidasi di server berdasarkan isi file.
- Endpoint dokumen memerlukan autentikasi. Pengguna hanya dapat mengakses
  dokumen milik booking-nya sendiri.
- Petugas hanya dapat mengakses dokumen untuk destinasi yang ditugaskan
  kepadanya. Admin memiliki akses ke semua destinasi.
- Respons API hanya mengembalikan nomor identitas yang disamarkan (4 karakter
  terakhir) dan status dokumen. Path penyimpanan tidak pernah dikirim.
- Pengecualian untuk prefill: `GET /api/auth/identity` mengembalikan nomor
  identitas milik akun yang sedang login (bukan akun lain), dengan header
  `Cache-Control: no-store`. Endpoint ini tidak mengembalikan path dokumen.
- Log aplikasi tidak memuat nama, nomor identitas, path file, token, password,
  atau OTP.

## 4. Status dokumen

Upload yang berhasil **bukan** berarti identitas terverifikasi. Status yang
mungkin:

| Status | Arti |
|---|---|
| `missing` | Belum ada dokumen. |
| `pending` | Dokumen tersimpan, menunggu pemeriksaan manual petugas. |
| `verified` | Petugas telah memeriksa dokumen. Ini bukan verifikasi resmi dari penerbit identitas. |
| `rejected` | Petugas menolak dokumen; alasan disimpan untuk perbaikan. |
| `purged` | Dokumen sudah dihapus karena masa retensi. |

Tidak ada facial recognition, OCR otomatis, atau klaim verifikasi resmi dari
instansi penerbit.

## 5. Retensi dan penghapusan

- Dokumen identitas per booking dihapus otomatis **90 hari setelah tanggal
  kunjungan** (dapat diubah lewat `TRAVELO_IDENTITY_RETENTION_DAYS`).
- Penghapusan dijalankan setiap hari pukul 02.00 oleh perintah
  `php artisan travelo:purge-identity-documents`.
- Setelah dihapus, status menjadi `purged` dan `document_path` dikosongkan.
  Data booking (nama, tanggal, nominal) tetap disimpan untuk catatan
  transaksi.
- Salinan dokumen level akun (`saved_identities`) **tidak** ikut terhapus oleh
  retensi booking. Salinan ini disimpan sampai pemilik menggantinya,
  menghapusnya lewat booking, atau memanggil `DELETE /api/auth/identity`.
  Data scalar akun ikut terhapus oleh endpoint tersebut.
- Pengguna dapat menghapus dokumennya sendiri sebelum tiket digunakan lewat
  `DELETE /api/bookings/{booking}/visitors/{visitor}/identity-document`.
  Untuk pemesan, tindakan ini juga menghapus salinan dokumen level akun.
- Dokumen tidak dapat diubah setelah tiket ditandai digunakan.

## 6. Hak pengguna

Pengguna dapat:

- Melihat daftar pengunjung dan status dokumennya
  (`GET /api/bookings/{booking}/visitors`).
- Mengoreksi data pengunjung sebelum tiket digunakan
  (`PATCH /api/bookings/{booking}/visitors/{visitor}`). Perubahan pada jenis
  atau nomor identitas mengembalikan status dokumen menjadi `pending`.
- Mengganti atau menghapus dokumen sebelum tiket digunakan.
- Mengunduh dokumennya sendiri
  (`GET /api/bookings/{booking}/visitors/{visitor}/identity-document`).
- Melihat data diri yang tersimpan untuk akunnya
  (`GET /api/auth/identity`) dan menghapusnya
  (`DELETE /api/auth/identity`). Di aplikasi, data ini tampil di layar
  **Profile** dengan nomor tersamar dan memerlukan konfirmasi sebelum dihapus.
- Mengubah jenis/nomor identitas pemesan akan menghapus salinan dokumen akun
  yang sudah tidak cocok; dokumen baru perlu diunggah.

Permintaan penghapusan data lain dapat disampaikan melalui kontak resmi
Travelo yang akan diisi sebelum produksi.

## 7. Berbagi data

Data tidak dijual. Data hanya dapat diakses oleh:

- Pengguna pemilik booking.
- Petugas destinasi terkait (terbatas pada destinasi yang ditugaskan).
- Admin Travelo untuk keperluan operasional dan audit.

Midtrans hanya menerima data pembayaran yang diperlukan dan tidak menerima
dokumen identitas.
