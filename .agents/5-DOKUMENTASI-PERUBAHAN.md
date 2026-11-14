# Dokumentasi Teknis Perubahan — Search, Filter & Lupa Password

Tanggal: 2026-10-02 · Stack: Laravel 10, MySQL, Blade + Bootstrap 5 CDN

Dokumen ini mencatat 5 paket perubahan. Pola acuan untuk semuanya:
`admin/books` (`BookController@index` + `books/index.blade.php`).

---

## 1. Search & Filter — Manajemen Member

**File diubah:**
- `app/Http/Controllers/MemberController.php` — `index()` → `index(Request $request)`
- `resources/views/admin/members/index.blade.php`

**Logika backend (`MemberController.php:12-40`):**
- Filter `status`: `where('status', ...)` untuk `active` / `blocked`.
- Search (satu input, OR): `members.member_code` LIKE + `orWhereHas('user')`
  untuk `users.name`, `users.email`, **`users.phone`**.
- `paginate(10)->appends(request()->query())` agar filter terbawa pindah halaman.

**View:** form GET (input search + select status + tombol Filter), badge hasil
(`total()`, badge pencarian, badge status) + tombol Reset Filter, pagination
`appends(...)->links('pagination::bootstrap-5')` (menggantikan pagination manual
yang tidak membawa query string), empty-state dibedakan saat filter aktif
("Tidak ada member yang ditemukan" + "Lihat Semua Member").

## 2. Search & Filter — Transaksi (+ status virtual Terlambat)

**File diubah:**
- `app/Http/Controllers/TransactionController.php` — `index()`, `create()`
- `resources/views/admin/transactions/index.blade.php`
- `resources/views/admin/transactions/create.blade.php` (lihat bagian 4)

**Logika backend (`index`):**
- Filter `status`: `borrowed` / `returned` langsung ke kolom DB;
  **`overdue` bersifat virtual** (tidak ada di DB): `where('status','borrowed')->whereDate('due_date','<',today())`.
- Search (OR): `transaction_code`, `book.title`, `member.member_code`,
  `member.user.name` / `email` / `phone`.
- `orderBy(created_at, desc)->paginate(10)->appends(...)`.

**Keputusan desain (dikunci user): Opsi A — tanpa migrasi.**
Enum `overdue` sudah sengaja dibuang sebelumnya
(`remove_overdue_status...`, keputusan B8 di `.agents/3-TASKS.md`) karena tidak
pernah ditulis kode mana pun; keterlambatan selalu dihitung on-the-fly via
`Transaction::getRemainingDaysAttribute()`. Persist ke DB hanya akan menambah
staleness + butuh scheduler harian.

**View index:** pola sama seperti members (badge status: Dipinjam/Terlambat/Dikembalikan).

## 3. Search & Filter — Denda (+ email/HP member)

**File diubah:**
- `app/Http/Controllers/PenaltyController.php` — `index()`
- `resources/views/admin/penalties/index.blade.php`

**Logika backend:**
- Statistik atas (`$totalUnpaid`/`$totalPaid`) **tetap global** (query tanpa filter).
- Filter `status`: `paid` / `unpaid`. Search (OR): kode transaksi, kode member,
  nama/email/no. HP member, judul buku.
- `$filteredTotal = (clone $query)->sum('fine_amount')` untuk footer tabel
  (total seluruh hasil filter, bukan cuma satu halaman).
- `->get()` diganti `paginate(10)->appends(...)` (sebelumnya semua baris dimuat sekaligus).

**View:** form GET + badge + Reset; kolom Member menampilkan nama + sub-teks
email/no. HP (satu kolom, tabel tidak melebar); penomoran diperbaiki dari
`$index+1` menjadi `$penalties->firstItem() + $index`; pagination Bootstrap 5;
empty-state dibedakan saat filter aktif.

## 4. Tom Select — Form Peminjaman (opsi A: kategori dihapus)

**File diubah:**
- `resources/views/admin/transactions/create.blade.php`
- `app/Http/Controllers/TransactionController.php` — `create()` (+`with('user')`)

**Perubahan:** dropdown kategori + ±50 baris JS filter `data-category` dibuang.
`<select name="member_id">` dan `<select name="book_id">` dipertahankan (kontrak
POST, `required`, `old()`, `@error` tidak berubah) tetapi di-upgrade menjadi
search box via **Tom Select 2.3.1 CDN** (+ tema Bootstrap 5).
- Member dicari by nama/kode/email/HP (teks option memuat semuanya).
- Buku dicari by judul/kategori (teks option memuat `judul (Stok) - kategori`),
  sehingga keyword kategori tetap ketemu tanpa dropdown terpisah.
- Tom Select dipilih atas Select2: tanpa jQuery, lebih ringan, cocok proyek
  tanpa build step (tidak pakai Vite).
- Catatan: butuh internet saat membuka halaman; tanpa JS form tetap submit via native select.

## 5. Lupa Password (reset via email, mode `log`)

**File baru:**
- `app/Http/Controllers/Auth/PasswordResetLinkController.php` (kirim link)
- `app/Http/Controllers/Auth/NewPasswordController.php` (form + simpan)
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/reset-password.blade.php`

**File diubah:** `routes/web.php` (4 route guest `password.request/email/reset/store`),
`resources/views/auth/login.blade.php` (link "Lupa password?" + `session('status')`),
`.env` (`MAIL_MAILER=smtp` → `log`, file ini di-gitignore).

**Konteks:** route + controller reset sebelumnya sengaja dibuang (file
`routes/auth.php` sudah tidak ada); fondasi masih ada (`User` = `Authenticatable`
+ `Notifiable`, tabel `password_reset_tokens`). Versi view mengikuti
`layouts.guest` + Bootstrap (bukan komponen Breeze `<x-guest-layout>` yang
sudah dihapus dari branch ini). Tidak install Breeze — hanya pola standar broker
bawaan Laravel.

**Operasional demo (UAS):** tiap request "Kirim Link Reset" menulis 1 baris email
berisi link `reset-password/{token}` ke `storage/logs/laravel.log`; token di DB
tersimpan ter-hash dan diganti tiap request baru; link kedaluwarsa 60 menit
(default Laravel). Untuk produksi cukup ganti `MAIL_*` ke SMTP sungguhan tanpa
ubah kode.

**Verifikasi yang dilakukan:** `route:list` (5 route password), `sendResetLink` →
`passwords.sent` + link di log, `Password::reset` → `passwords.reset` + hash
password baru valid, `php -l` + `pint --test` PASS semua file tersentuh.

---

## 6. Pemulihan 2026-10-02 (insiden commit manual)

Commit manual `4e64faf "Update filter search"` tidak memuat perubahan bagian 1–4
(terbukti via grep: 0 hasil) dan mengandung **conflict marker git yang
ter-commit** di 5 file (`AdminMiddleware`, `MemberMiddleware`, `BookController`,
`Transaction`, `DatabaseSeeder`) — seluruh route mati 500 (`ParseError`).

Tindakan pemulihan:
- 11 file dipulihkan dari commit `25271a2` (masih utuh di object DB).
- Konflik diselesaikan dengan sisi "Stashed changes" (state branch terbaru) +
  perbaikan signature `BookController@memberIndex(Request $request)` yang
  tertinggal tanpa parameter.
- `DatabaseSeeder.php` dipulihkan dari `25271a2` (versi HEAD ber-marker dan
  memakai akun `library.com`; versi pulihan memakai `gmail.com` sesuai DB dev
  dan `AGENTS.md`), lalu `pint`.
- Controller Auth versi Breeze stok dipertahankan (fungsional, tanpa dependensi
  komponen yang sudah dihapus).
- Verifikasi pasca-pulih: HTTP 200 (`/login`, `/forgot-password`), 302 tertangani
  (`/admin/*` untuk guest), query search/filter/overdue valid di DB dev,
  `pint --test` PASS.

---

## Aturan keamanan yang ditegakkan sesi ini

- Password hanya tersimpan sebagai hash (`Hash::make`); tidak ada fitur
  "lihat password" — admin hanya bisa me-reset, bukan membaca.
- Status `overdue` tidak di-persist (anti-stale); selalu dihitung real-time.
- Statistik denda global tidak ikut filter (angka keuangan tidak menyesatkan).
