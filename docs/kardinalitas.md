# Kardinalitas Antar Tabel

Sistem Informasi Perpustakaan (trial_perpustakaan2)

Skema basis data: MariaDB / MySQL. PK tabel bisnis bernama `<tabel>_id`
(hanya tabel `users` framework yang tetap memakai PK `id`).

Notasi kardinalitas: **1:1**, **1:M**, **M:M**.

---

## Ringkasan Relasi

| No | Relasi | Kardinalitas | FK yang Menghubungkan | Alasan Singkat |
|----|--------|:------------:|----------------------|----------------|
| 1 | `categories` → `books` | **1:M** | `books.category_id` | Satu kategori memuat banyak buku |
| 2 | `users` → `loans` | **1:M** | `loans.member_id` | Satu user bisa meminjam banyak buku |
| 3 | `books` → `loans` | **1:M** | `loans.book_id` | Satu judul buku bisa dipinjam berkali-kali |
| 4 | `loans` → `returns` | **1:1** | `returns.loan_id` (UNIQUE) | Satu peminjaman hanya dikembalikan sekali |
| 5 | `loans` → `penalties` | **1:1** | `penalties.loan_id` | Denda lahir dari peminjaman yang telat (diketahui saat pengembalian) |
| 6 | `users` → `penalties` | **1:M** | `penalties.member_id` | Satu user bisa terkena banyak denda |
| 7 | `users` ↔ `books` | **M:M** | diurai via junction `loans` | Banyak user meminjam banyak buku |

> **Catatan peran:** `users` adalah satu tabel gabungan (admin + member,
> dibedakan kolom `role`) — TIDAK dipisah. Kolom audit `processed_by`
> di `loans` dan `returns` tetap menunjuk ke tabel `users` yang sama
> (nullable: admin yang meminjamkan / menerima pengembalian boleh
> berbeda orang), sehingga tidak menambah relasi baru.

---

## PK & FK Tiap Tabel

**PK (Primary Key)** = identitas unik baris di tabel itu sendiri.
**FK (Foreign Key)** = kolom "tunjuk" yang berisi PK dari tabel lain
(menghubungkan dua tabel).

| No | Tabel | Kolom | Peran | Menunjuk ke | Keterangan |
|----|-------|-------|:-----:|-------------|------------|
| 1 | `users` | `id` | **PK** | — | Identitas user (tabel acuan, tidak punya FK) |
| 2 | `categories` | `categories_id` | **PK** | — | Identitas kategori (tidak punya FK) |
| 3 | `books` | `books_id` | **PK** | — | Identitas buku |
| 4 | `books` | `category_id` | **FK** | `categories.categories_id` | Buku ini milik kategori mana (boleh kosong / NULL) |
| 5 | `loans` | `loans_id` | **PK** | — | Identitas peminjaman |
| 6 | `loans` | `member_id` | **FK** | `users.id` | Peminjaman ini milik user (member) siapa |
| 7 | `loans` | `book_id` | **FK** | `books.books_id` | Peminjaman ini meminjam buku yang mana |
| 8 | `loans` | `processed_by` | **FK** | `users.id` | Admin yang meminjamkan (boleh kosong / NULL) |
| 9 | `returns` | `returns_id` | **PK** | — | Identitas pengembalian |
| 10 | `returns` | `loan_id` | **FK UNIQUE** | `loans.loans_id` | Pengembalian ini untuk peminjaman yang mana (UNIQUE = satu peminjaman hanya dikembalikan sekali) |
| 11 | `returns` | `processed_by` | **FK** | `users.id` | Admin yang menerima pengembalian (boleh kosong / NULL) |
| 12 | `penalties` | `penalties_id` | **PK** | — | Identitas denda |
| 13 | `penalties` | `loan_id` | **FK** | `loans.loans_id` | Denda ini lahir dari peminjaman yang mana (telat diketahui dari pengembaliannya) |
| 14 | `penalties` | `member_id` | **FK** | `users.id` | Denda ini milik user (member) siapa |

Membaca tabel di atas:

- Baris berperan **PK** = "kartu identitas" tabel itu, mis. `loans_id`
  membedakan satu peminjaman dari peminjaman lain.
- Baris berperan **FK** = "tali penghubung", mis. `loans.book_id`
  mengaitkan baris peminjaman ke satu baris di tabel `books`.
- Kata **UNIQUE** pada FK (`loan_id` di `returns`) berarti nilainya
  tidak boleh kembar — inilah yang memaksa kardinalitas 1:1.
- FK bertanda *(boleh kosong / NULL)* = opsional: baris boleh ada tanpa
  nilai itu (peminjaman belum punya admin pemroses, dst.).

---

### Aturan Integritas FK

| Aturan | Artinya | Dipakai di |
|--------|---------|------------|
| `CASCADE` | Induk dihapus → baris anak ikut terhapus | `member_id` & `book_id` di `loans`, `loan_id` di `returns`, `loan_id` & `member_id` di `penalties` |
| `SET NULL` | Induk dihapus → kolom FK anak jadi NULL (anak tetap hidup) | `books.category_id` |
| `NULL ON DELETE` | Induk (admin) dihapus → jejak audit tetap, namanya jadi kosong | `processed_by` di `loans` & `returns` |

---

## Pembahasan Tiap Relasi

### 1. `categories` **1:M** `books`

- Satu kategori memiliki banyak buku; satu buku hanya satu kategori.
- FK: `books.category_id` → `categories.categories_id`.
- Menghapus kategori tidak ikut menghapus buku: `ON DELETE SET NULL`.

### 2. `users` **1:M** `loans`

- FK: `loans.member_id` → `users.id`, `ON DELETE CASCADE`.
- Satu user boleh mempunyai banyak baris peminjaman (riwayat).
- Batas 3 peminjaman aktif ditegakkan di level aplikasi, bukan kardinalitas.

### 3. `books` **1:M** `loans`

- FK: `loans.book_id` → `books.books_id`, `ON DELETE CASCADE`.
- Satu buku muncul di banyak baris peminjaman; satu peminjaman hanya
  merujuk satu buku.

### 4. `loans` **1:1** `returns`

- FK: `returns.loan_id` → `loans.loans_id`, **UNIQUE** (+ `ON DELETE CASCADE`).
- Constraint UNIQUE memaksa kardinalitas 1:1 di level database: dua baris
  `returns` untuk satu peminjaman yang sama pasti ditolak.
- Bentuk penuhnya **1 : 0..1** — selama buku masih dipinjam, baris
  `returns`-nya belum ada.

### 5. `loans` **1:1** `penalties`

- Denda SELALU berasal dari keterlambatan, dan keterlambatan baru diketahui
  saat pengembalian terjadi — tetapi baris denda ditautkan ke
  **peminjamannya** (FK `penalties.loan_id` → `loans.loans_id`, sesuai
  skema di phpMyAdmin).
- Satu peminjaman menghasilkan paling banyak satu denda (dijamin logika
  aplikasi: denda hanya dibuat sekali saat proses pengembalian);
  peminjaman tepat waktu tidak membuat denda sama sekali
  (bentuk penuh **1 : 0..1**).
- `days_late` dan `fine_amount` (decimal) diturunkan (derived) dari
  `returns.return_date` versus `loans.due_date` — dilihat lewat
  `returns.loan_id`.
- FK `penalties.member_id` → `users.id` tetap disimpan per kejadian,
  supaya daftar "denda milik siapa" tidak perlu join berlapis.

### 6. `users` **1:M** `penalties`

- FK: `penalties.member_id` → `users.id`, `ON DELETE CASCADE`.
- Satu user boleh punya banyak riwayat denda (satu per kejadian telat).

### 7. `users` **M:M** `books` (via junction `loans`)

- Banyak user meminjam banyak buku; relasi ini tidak bisa ditampung oleh
  satu FK saja.
- Solusi: **junction table** `loans` (FK `member_id` ke `users`, FK
  `book_id` ke `books`). Setiap baris `loans` = satu kejadian pinjam,
  sehingga M:M terurai menjadi dua 1:M.

---

## Kode Kardinalitas

| Kode | Makna | Contoh di skema ini |
|------|-------|---------------------|
| 1:1 | Satu ke satu | `loans`–`returns` (UNIQUE `loan_id`), `loans`–`penalties` (`loan_id`) |
| 1:M | Satu ke banyak | Relasi 1–3, 6 |
| M:M | Banyak ke banyak | `users`–`books`, diurai via junction `loans` |

> Catatan sejarah (2026): tabel `members` lama telah di-merge ke `users`
> (relasi `User` 1:1 `Member` tidak ada lagi), dan `returns` adalah tabel
> baru hasil pemisahan dari `loans`. Tabel dulu bernama `transactions`
> kini bernama `loans`.

> Catatan pembacaan: baris denda ditautkan ke `loans` (FK `loan_id`),
> sedangkan "telat tidaknya" barulah terlihat lewat tabel `returns`
> (perbandingan `returns.return_date` vs `loans.due_date`). Struktur ini
> disesuaikan dengan struktur nyata di phpMyAdmin. Screenshot Designer
> phpMyAdmin yang menampilkan `loans.loan_id` adalah tampilan lama yang belum
> di-refresh — kolom di database sekarang bernama `loans_id` dan `loans`
> sudah memiliki kolom `processed_by`.
