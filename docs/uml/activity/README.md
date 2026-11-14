# Activity Diagram — Sistem Perpustakaan

Sumber kebenaran: `routes/web.php`, `app/Http/Controllers/*.php`, dan
`app/Models/*.php`. Setiap aksi digambar dalam **satu file terpisah** agar alur
peminjaman, pengembalian, dan pembayaran denda tidak tercampur.

Diagram memakai swimlane tiga kolom:

| Kolom | Isi |
|---|---|
| `Pengguna` / `Admin` / `Member` | Aktor yang menjalankan aksi |
| `Sistem` | Validasi, perhitungan, dan aturan bisnis |
| `Database` | Baca/tulis data dan perubahan stok |

Kolom `Database` hanya muncul bila alur benar-benar mengakses data. Pada
`umum/03-logout` kolom tersebut tidak ditampilkan karena logout hanya
menghapus sesi tanpa menyentuh database.

## Daftar Diagram

### Umum (`umum/`)

| File | Aksi |
|---|---|
| `01-login` | Login: cek kredensial, status akun, catat waktu login, arahkan sesuai peran |
| `02-registrasi` | Registrasi member: validasi data, email unik, kode member otomatis, login otomatis |
| `03-logout` | Logout: hapus sesi dan arahkan ke halaman login |
| `04-lupa-password` | Ajukan reset password: cek email lalu kirim tautan |
| `05-reset-password` | Reset password: validasi token dan simpan password baru |
| `06-update-profil` | Ubah profil: validasi data dan email unik |
| `07-ubah-password` | Ubah password: cek password lama dan konfirmasi |

### Admin (`admin/`)

| File | Aksi |
|---|---|
| `01-dashboard-admin` | Dashboard: statistik buku, member, pinjaman aktif, denda, dan peminjaman terbaru |
| `02-daftar-buku` | Daftar buku dengan pencarian judul dan filter kategori |
| `03-tambah-buku` | Tambah buku termasuk validasi ISBN/stok dan upload cover |
| `04-detail-buku` | Lihat detail buku |
| `05-edit-buku` | Edit buku dan hitung ulang `available_stock` dari pinjaman aktif |
| `06-hapus-buku` | Hapus buku dengan penolakan bila masih dipinjam atau punya riwayat |
| `07-daftar-member` | Daftar member dengan filter status dan pencarian |
| `08-tambah-member` | Tambah member baru dan pembuatan kode member otomatis |
| `09-blokir-aktifkan-member` | Blokir/aktifkan member (toggle status) |
| `10-hapus-member` | Hapus member dengan penolakan bila masih meminjam atau punya riwayat |
| `11-peminjaman-buku` | Peminjaman: validasi tanggal, kelayakan member, denda, stok, kode pinjam, dan pengurangan stok |
| `12-daftar-peminjaman` | Daftar peminjaman aktif dengan pencarian |
| `13-riwayat-pengembalian` | Riwayat pengembalian dengan pencarian khusus riwayat |
| `14-pengembalian-buku` | Pengembalian: validasi tanggal, cegah kembar, tambah stok, dan buat denda bila terlambat |
| `15-daftar-denda` | Daftar denda dengan filter status, pencarian, dan statistik |
| `16-bayar-denda` | Bayar denda dan cegah pembayaran ganda |

### Member (`member/`)

| File | Aksi |
|---|---|
| `01-dashboard-member` | Dashboard member: ringkasan pinjaman, denda, dan peringatan jatuh tempo |
| `02-katalog-buku` | Katalog dengan pencarian serta filter kategori dan ketersediaan |
| `03-detail-buku` | Lihat detail buku dari katalog |
| `04-riwayat-peminjaman` | Riwayat peminjaman milik member yang sedang login |
| `05-status-denda` | Denda tercatat dan denda berjalan milik sendiri |

## Aturan Bisnis yang Tampil di Diagram

| Aturan | Nilai |
|---|---|
| Maksimal pinjaman aktif per member | 3 buku |
| Lama pinjaman default | 7 hari |
| Batas maksimal jatuh tempo | 30 hari sejak tanggal pinjam |
| Denda keterlambatan | Rp2.000 per hari |
| Status pinjaman | `borrowed` / `returned` |
| Status denda | `unpaid` / `paid` |
| Status member | `active` / `inactive` |

## Render ulang

Setiap file `.puml` sudah disertai hasil render `.png` dan `.svg`.

Dengan PlantUML lokal:

```bash
cd docs/uml/activity
java -jar plantuml.jar -tpng umum/*.puml admin/*.puml member/*.puml
java -jar plantuml.jar -tsvg umum/*.puml admin/*.puml member/*.puml
```

Atau buka file `.puml` dengan ekstensi PlantUML di VS Code, lalu export PNG/SVG.
