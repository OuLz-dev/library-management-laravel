# UML — Sistem Perpustakaan

Sumber kebenaran: `app/Models/*.php`, `database/migrations/*_create_*.php`,
`routes/web.php`, `app/Http/Controllers/*.php`.

## Class Diagram
- `class-diagram.puml` (sumber PlantUML — edit file ini bila model berubah)
- `class-diagram.png` / `class-diagram.svg` (hasil render)

Class diagram menggambarkan model domain yang benar-benar dipakai aplikasi:

| Kelas | Peran |
|---|---|
| `User` | Menampung admin dan member dalam satu tabel; role menentukan hak akses |
| `Category` | Kategori buku |
| `Book` | Data buku, stok, dan stok tersedia |
| `Loan` | Data peminjaman, status, dan perhitungan keterlambatan/denda |
| `LoanReturn` | Data pengembalian buku (satu peminjaman hanya bisa dikembalikan sekali) |
| `Penalty` | Denda keterlambatan dan status pembayarannya |

Relasi utama: `Category → Book → Loan`, `User → Loan/Penalty`,
`Loan → LoanReturn/Penalty`, serta `User → Loan/LoanReturn` lewat `processed_by`
(admin yang memproses). Tidak ada kelas `Member` atau `Transaction` terpisah —
member adalah `User` dengan role `member`, sedangkan transaksi peminjaman
bernama `Loan` dan pengembaliannya `LoanReturn`.

## Sequence Diagram

Semua sequence diagram ada di folder `sequence/`, dipisahkan menjadi `admin/` dan
`member/`. Setiap alur tersedia dalam `.puml` (sumber), `.png`, dan `.svg`.

Diagram memakai bahasa alur sehari-hari (bukan istilah teknis), tetapi tetap
menampilkan proses yang rinci: halaman, sistem, data terkait, dan database.

### Admin

| File | Alur |
|---|---|
| `admin/01-login` | Login: pencarian akun, pemeriksaan password, status akun, pencatatan waktu login, dan pengarahan sesuai peran |
| `admin/02-kelola-data-member` | Melihat & mencari member, menambah member, memblokir/mengaktifkan, dan menghapus member |
| `admin/03-kelola-buku` | Melihat & mencari buku, menambah buku (termasuk sampul), mengubah buku, dan menghapus buku |
| `admin/04-peminjaman-buku` | Pilih member dan buku, pemeriksaan syarat pinjam, denda, stok, pembuatan kode pinjam, dan pengurangan stok |
| `admin/05-pengembalian-buku` | Satu halaman riwayat dengan pencarian khusus riwayat; tombol Kembalikan Buku membuka form untuk memilih peminjaman aktif dan tanggal kembali, lalu sistem menandai selesai, menambah stok, dan mencatat denda bila terlambat |

### Member

| File | Alur |
|---|---|
| `member/01-registrasi` | Pendaftaran akun: pemeriksaan data, keunikan email, pembuatan nomor member, dan login otomatis |
| `member/02-katalog-buku` | Melihat katalog, mencari/memfilter buku, dan membuka detail buku |
| `member/03-riwayat-peminjaman` | Melihat riwayat peminjaman milik sendiri beserta status dan denda |
| `member/04-status-denda` | Melihat total denda yang belum dibayar dan daftar denda milik sendiri |

## Render ulang

Dengan PlantUML lokal:
```bash
cd docs/uml/sequence
java -jar plantuml.jar -tpng admin/*.puml member/*.puml
java -jar plantuml.jar -tsvg admin/*.puml member/*.puml
```

Atau buka file `.puml` dengan ekstensi PlantUML di VS Code, lalu export PNG/SVG.
