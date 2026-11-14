# Black Box Testing

Pengujian black box dilakukan untuk memastikan setiap fungsi aplikasi perpustakaan berjalan sesuai kebutuhan, dengan menekankan pada masukan (input) yang diberikan pengguna dan keluaran (output) yang dihasilkan sistem, tanpa memperhatikan struktur kode di dalamnya.

Pengujian dikelompokkan berdasarkan peran (role) pengguna. Aplikasi memiliki dua peran, yaitu **admin** sebagai pengelola data perpustakaan dan **anggota** sebagai peminjam. Skenario yang berlaku untuk kedua peran dikelompokkan tersendiri pada bagian A.

Hasil pengujian diberi keterangan **Sesuai** apabila keluaran sistem sama dengan keluaran yang diharapkan.

---

## A. Pengujian Umum (Berlaku untuk Semua Peran)

### A.1 Autentikasi

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Login dengan email dan password yang benar | Email dan password akun terdaftar | Berhasil masuk dan diarahkan ke dashboard sesuai peran | Sesuai |
| 2 | Login dengan password salah | Email terdaftar dengan password salah | Tetap di halaman login dan muncul pesan kesalahan | Sesuai |
| 3 | Login menggunakan akun yang dinonaktifkan | Email dan password akun berstatus nonaktif | Login ditolak, muncul pesan akun tidak aktif | Sesuai |
| 4 | Logout | Klik menu keluar saat sudah login | Sesi berakhir dan diarahkan kembali ke halaman login | Sesuai |

### A.2 Lupa Password

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Mengajukan reset password dengan email terdaftar | Email akun yang terdaftar | Tautan reset password dikirim dan muncul pesan konfirmasi | Sesuai |
| 2 | Mengajukan reset password dengan email tidak terdaftar | Email yang tidak ada di sistem | Permintaan ditolak, muncul pesan email tidak ditemukan | Sesuai |

### A.3 Hak Akses

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Anggota membuka halaman admin | Login sebagai anggota, akses menu admin | Akses ditolak dengan pesan kesalahan | Sesuai |
| 2 | Admin membuka halaman anggota | Login sebagai admin, akses menu anggota | Akses ditolak dengan pesan kesalahan | Sesuai |
| 3 | Pengunjung membuka halaman admin tanpa login | Akses menu admin tanpa login | Diarahkan ke halaman login | Sesuai |

---

## B. Pengujian pada Peran Admin

### B.1 Manajemen Buku

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Menambah buku dengan data lengkap | Judul, penulis, penerbit, ISBN, kategori, stok | Data buku tersimpan dan muncul pada daftar buku | Sesuai |
| 2 | Menambah buku dengan ISBN yang sudah terdaftar | ISBN milik buku lain | Ditolak, muncul pesan ISBN sudah digunakan | Sesuai |
| 3 | Menambah buku dengan stok 0 | Stok diisi 0 | Ditolak, muncul pesan stok minimal 1 | Sesuai |
| 4 | Mengubah data buku | Ubah judul atau stok lalu simpan | Perubahan tersimpan dan tampil pada daftar buku | Sesuai |
| 5 | Menghapus buku yang masih dipinjam | Buku dengan transaksi peminjaman aktif | Ditolak, muncul pesan buku masih dipinjam | Sesuai |

### B.2 Manajemen Anggota

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Menambah anggota baru | Nama, email, dan password anggota | Akun dan data anggota dibuat, kode anggota terisi otomatis | Sesuai |
| 2 | Menambah anggota dengan email yang sudah dipakai | Email milik pengguna lain | Ditolak, muncul pesan email sudah terdaftar | Sesuai |
| 3 | Memblokir anggota | Klik tombol blokir pada anggota aktif | Status anggota berubah menjadi diblokir dan tidak dapat meminjam | Sesuai |

### B.3 Transaksi Peminjaman

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Meminjam buku dengan stok tersedia | Anggota aktif, buku tersedia, tanggal pinjam dan jatuh tempo valid | Transaksi tersimpan, stok buku berkurang 1 | Sesuai |
| 2 | Meminjam buku yang stoknya habis | Buku dengan stok tersedia 0 | Ditolak, muncul pesan stok buku tidak tersedia | Sesuai |
| 3 | Meminjam saat anggota berstatus diblokir | Anggota berstatus diblokir | Ditolak, muncul pesan anggota tidak dapat meminjam | Sesuai |
| 4 | Meminjam saat anggota memiliki denda belum lunas | Anggota dengan denda berstatus belum bayar | Ditolak, muncul pesan denda belum dibayar | Sesuai |
| 5 | Meminjam saat sudah mencapai batas pinjaman | Anggota dengan 3 pinjaman aktif | Ditolak, muncul pesan batas peminjaman tercapai | Sesuai |

### B.4 Pengembalian Buku

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Mengembalikan buku tepat waktu | Transaksi dengan jatuh tempo belum terlewat | Status menjadi dikembalikan, stok bertambah, tidak ada denda | Sesuai |
| 2 | Mengembalikan buku melewati jatuh tempo | Transaksi yang sudah lewat jatuh tempo | Status dikembalikan dan denda dibuat otomatis (Rp2.000 per hari) | Sesuai |
| 3 | Mengembalikan buku yang sudah dikembalikan | Transaksi berstatus dikembalikan | Ditolak, muncul pesan buku sudah dikembalikan | Sesuai |

### B.5 Denda

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Menampilkan daftar denda | Buka menu denda | Daftar denda tampil beserta total belum bayar dan sudah bayar | Sesuai |
| 2 | Membayar denda | Klik tombol bayar pada denda yang belum lunas | Status denda berubah menjadi lunas dan tanggal bayar terisi | Sesuai |
| 3 | Membayar denda yang sudah lunas | Denda berstatus lunas | Ditolak, muncul pesan denda sudah dibayar | Sesuai |

---

## C. Pengujian pada Peran Anggota

### C.1 Dashboard Anggota

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Melihat ringkasan pada dashboard | Login sebagai anggota | Ringkasan pinjaman aktif dan total denda tampil pada dashboard | Sesuai |

### C.2 Katalog Buku

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Melihat daftar katalog buku | Login sebagai anggota, buka menu katalog | Daftar buku tampil lengkap dengan informasi stok | Sesuai |
| 2 | Mencari buku berdasarkan judul | Kata kunci judul buku | Hanya buku yang sesuai kata kunci yang ditampilkan | Sesuai |

### C.3 Detail Buku

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Melihat detail buku dari katalog | Klik salah satu buku pada katalog | Detail buku tampil tanpa tombol ubah atau hapus | Sesuai |

### C.4 Riwayat Peminjaman dan Denda Saya

| No | Skenario Pengujian | Input | Output yang Diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| 1 | Melihat riwayat peminjaman pribadi | Buka menu pinjaman saya | Hanya menampilkan transaksi milik anggota yang sedang login | Sesuai |
| 2 | Melihat daftar denda pribadi | Buka menu denda saya | Hanya menampilkan denda milik anggota yang sedang login | Sesuai |

---

## Rekapitulasi

| Bagian | Jumlah Skenario |
| --- | --- |
| A. Pengujian Umum | 9 |
| B. Pengujian pada Peran Admin | 19 |
| C. Pengujian pada Peran Anggota | 6 |
| **Total** | **34** |

Dari **34 skenario** pengujian yang dilakukan, seluruhnya menghasilkan keluaran sesuai dengan yang diharapkan. Dengan demikian fungsi-fungsi utama aplikasi perpustakaan pada masing-masing peran dapat dinyatakan berjalan dengan baik.
