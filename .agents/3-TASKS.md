# 3 — TASKS: Perpustakaan The Origin

> **Sumber:** `.agents/4-LEGACY-DECODER.md` (analisis) · `.agents/2-TECH-SPEC.md` (spesifikasi)
> **Dibuat:** 30 Sep 2026
> **Prioritas yang dipilih:** ① Amankan test & DB  ② Bersihkan dead code  ③ Perbaiki bug bisnis

**Status:** `[ ]` belum dikerjakan · `[~]` dikerjakan · `[x]` selesai & lolos gate · `[-]` dibuang

---

## Gate wajib (jalankan setiap selesai 1 Epic)

```bash
# 1. Test hijau DAN database dev tidak berubah
php artisan test
mysql -uroot -p1234 -e "SELECT COUNT(*) FROM perpustakaan_webprogramming.books;"   # harus tetap 151

# 2. Route count TETAP 35 bernama
php artisan route:list --json | php -r '$r=json_decode(stream_get_contents(STDIN),true); echo count(array_filter(array_column($r,"name"))),PHP_EOL;'

# 3. Audit route(): 0 missing di luar file yang sengaja dihapus
grep -rhoE "route\('[a-zA-Z0-9_.]+'" resources/views app | sed "s/route('//;s/'$//" | sort -u

# 4. View cache sukses
php artisan view:cache && php artisan view:clear

# 5. Format hanya file yang disentuh
php vendor/bin/pint <file>
```

---

# ▶ EPIC A — Amankan Test & DB  *(prioritas 🥇)*

> **PERINGATAN:** jangan menjalankan `php artisan test` **sebelum A1–A3 selesai** —
> `RefreshDatabase` akan `migrate:fresh` ke `perpustakaan_webprogramming` dan
> **menghapus seluruh data dev**.

### A1 — Buat database test
- [x] `mysql -uroot -p1234 -e "CREATE DATABASE IF NOT EXISTS perpustakaan_test;"`
- **AC:** `SHOW DATABASES` menampilkan `perpustakaan_test`.

### A2 — Arahkan phpunit ke DB test
- [x] Di blok `<php>` `phpunit.xml`, tambahkan:
  ```xml
  <env name="DB_CONNECTION" value="mysql"/>
  <env name="DB_DATABASE"   value="perpustakaan_test"/>
  ```
- **AC:** env phpunit menimpa `.env` (Laravel Dotenv *immutable* — tidak menimpa env yang sudah ada).
- **Catatan:** `pdo_sqlite` **tidak terpasang** → sqlite `:memory:` bukan opsi.

### A3 — ✅ VERIFIKASI GUARD  *(gate terpenting)*
- [x] Jalankan **1 test saja**: `php vendor/bin/phpunit tests/Unit/ExampleTest.php`
- [x] Cek DB dev: `mysql -uroot -p1234 -e "SELECT COUNT(*) FROM perpustakaan_webprogramming.books;"` → **151**
- [x] Cek DB test: `mysql -uroot -p1234 -e "SHOW TABLES FROM perpustakaan_test;"` → ada tabel migration
- **AC:** data dev **tidak berubah** setelah test berjalan.

### A4 — Baseline
- [x] `php artisan test` → catat hasil lengkap sebelum mengubah apa pun
- **Estimasi:** 25 method → **±19 gagal, 6 lulus**
  - PASS: `Unit/ExampleTest`, `ProfileTest::profile_page_is_displayed`, `AuthenticationTest::{login_screen, invalid_password, logout}`, `RegistrationTest::registration_screen`
  - FAIL: `ExampleTest`(1) · `ProfileTest`(4) · `AuthenticationTest`(1) · `RegistrationTest`(1) · `EmailVerificationTest`(3) · `PasswordResetTest`(4) · `PasswordConfirmationTest`(3) · `PasswordUpdateTest`(2)
- **AC:** daftar gagal terekam — supaya "merah" tidak dianggap ulah perubahan kita.

### A5 — Fix `tests/Feature/ExampleTest.php`
- [x] `assertStatus(200)` → `assertRedirect('/login')`
- **AC:** `GET /` = 302 ke `/login`.

### A6 — Fix `tests/Feature/Auth/AuthenticationTest.php`
- [x] `assertRedirect(RouteServiceProvider::HOME)` → `assertRedirect('/member/dashboard')`
  (factory default `role=member`, login langsung ke dashboard per-role)
- **AC:** `test_users_can_authenticate_using_the_login_screen` lulus.

### A7 — Fix `tests/Feature/Auth/RegistrationTest.php`
- [x] Sama dengan A6 → `assertRedirect('/member/dashboard')`
- **AC:** `test_new_users_can_register` lulus.

### A8 — Fix `tests/Feature/ProfileTest.php`
- [x] `->patch('/profile', …)` → `->put('/profile', …)` (2 method)
- [x] Hapus `test_user_can_delete_their_account` — route `DELETE /profile` **memang tidak ada**
- [x] Hapus `test_correct_password_must_be_provided_to_delete_account` — alasan sama
- **AC:** 3 method tersisa lulus. `assertRedirect('/profile')` tetap valid (ada `from('/profile')`).

### A9 — Hapus 3 test fitur yang route-nya tak terdaftar
- [x] Hapus `tests/Feature/Auth/EmailVerificationTest.php` (3 method)
- [x] Hapus `tests/Feature/Auth/PasswordResetTest.php` (4 method)
- [x] Hapus `tests/Feature/Auth/PasswordConfirmationTest.php` (3 method)
- **Alasan:** `routes/auth.php` tak dimuat → semua endpoint 404. Konsisten dengan Epic B (B3).
- **AC:** sisa test file = 6 (`Unit/ExampleTest`, `Feature/ExampleTest`, `ProfileTest`, `AuthenticationTest`, `RegistrationTest`, `PasswordUpdateTest`).

### A10 — Fix `tests/Feature/Auth/PasswordUpdateTest.php`  ⚠️ **JANGAN dihapus**
- [x] `->put('/password', …)` → `->put('/profile/password', …)` (2 method)
- [x] `assertSessionHasErrorsIn('updatePassword', 'current_password')` → `assertSessionHas('error')`
  (controller pakai `back()->with('error', …)`, bukan FormRequest ErrorBag)
- **AC:** fitur ganti password **tertes** — karena fiturnya hidup.

### A11 — Tambah factory
- [x] `database/factories/CategoryFactory.php` → `name`, `description`
- [x] `database/factories/BookFactory.php` → `title`, `author`, `isbn` (unique), `stock`, `available_stock`, relasi `category`
- [x] `database/factories/MemberFactory.php` → `member_code` (unique), `join_date`, `status`, relasi `user`
- [x] `database/factories/TransactionFactory.php` → `transaction_code` (unique), `borrow_date`, `due_date`, relasi `member` + `book`, `status`
- [x] `database/factories/PenaltyFactory.php` → `days_late`, `fine_amount`, `status`, relasi `transaction` + `member`
- **AC:** `Book::factory()->create()` berhasil tanpa error.

### A12 — Tambah test domain  *(ini test yang melindungi Epic C)*
- [x] `tests/Feature/BookTest.php` — CRUD admin, upload cover, filter `?search` / `?category`, pagination 20
- [x] `tests/Feature/TransactionBorrowTest.php`
  - pinjam sukses → `available_stock` berkurang
  - ke-4 pinjam → ditolak (`Member::canBorrow` < 3)
  - member `status=blocked` → ditolak
  - ada penalty `unpaid` → ditolak
  - stok 0 → ditolak
- [x] `tests/Feature/TransactionReturnTest.php`
  - kembali tepat waktu → **tidak** ada `Penalty`, stok naik, `status=returned`
  - kembali terlambat → `Penalty` dibuat, `fine_amount = hari × 2000`
  - doble return → ditolak
- [x] `tests/Feature/PenaltyTest.php` — bayar denda → `status=paid` + `paid_date`; doble bayar → ditolak
- [x] `tests/Feature/MemberTest.php` — `toggleStatus` sinkron `members.status` ↔ `users.status`; `canBorrow()` false saat blocked
- [x] `tests/Feature/RoleAccessTest.php` — member akses `/admin/*` → 403; admin akses `/member/*` → 403; guest → redirect login
- **AC:** minimal 1 test per rule bisnis di Tech Spec §4.

### A13 — Format
- [x] `php vendor/bin/pint <file yang disentuh>` saja — **jangan repo-wide** (repo memang tidak pint-clean)
- [x] ✅ jalankan seluruh **Gate**

---

# ▶ EPIC B — Bersihkan dead code  *(prioritas 🥈)*

### B1 — Hapus 10 view mati
- [x] `resources/views/dashboard.blade.php`
- [x] `resources/views/layouts/navigation.blade.php`
- [x] `resources/views/welcome.blade.php`
- [x] `resources/views/auth/forgot-password.blade.php`
- [x] `resources/views/auth/reset-password.blade.php`
- [x] `resources/views/auth/verify-email.blade.php`
- [x] `resources/views/auth/confirm-password.blade.php`
- [x] `resources/views/profile/partials/delete-user-form.blade.php`
- [x] `resources/views/profile/partials/update-password-form.blade.php`
- [x] `resources/views/profile/partials/update-profile-information-form.blade.php`
- **Verifikasi sebelum hapus:** `grep -rn "partials\.\|@include" resources/views` → nol hasil
- **AC:** `php artisan view:cache` tetap sukses; login/register/profil tetap tampil.

### B2 — Hapus 13 komponen + 2 View Component
- [x] `resources/views/components/` → seluruh 13 file:
  `application-logo, auth-session-status, danger-button, dropdown, dropdown-link, input-error, input-label, modal, nav-link, primary-button, responsive-nav-link, secondary-button, text-input`
- [x] `app/View/Components/AppLayout.php`
- [x] `app/View/Components/GuestLayout.php`
- [x] Hapus direktori `resources/views/components/` bila kosong
- ⚠️ **Justifikasi di Tech Spec:** *"tidak dirender route apa pun"* — **bukan** *"view-nya tak ada"*
  (kedua class merender `layouts.app` / `layouts.guest` dan **berfungsi normal**)
- **AC:** `grep -rn "<x-" resources/views` → nol hasil.

### B3 — Hapus `routes/auth.php` + 7 controller Auth mati
- [x] Hapus `routes/auth.php`
- [x] Hapus: `Auth/ConfirmablePasswordController.php`
- [x] Hapus: `Auth/EmailVerificationNotificationController.php`
- [x] Hapus: `Auth/EmailVerificationPromptController.php`
- [x] Hapus: `Auth/NewPasswordController.php`
- [x] Hapus: `Auth/PasswordController.php`
- [x] Hapus: `Auth/PasswordResetLinkController.php`
- [x] Hapus: `Auth/VerifyEmailController.php`
- ⚠️ **PERTAHANKAN** `AuthenticatedSessionController` + `RegisteredUserController`
- [x] Opsional: lepas listener `SendEmailVerificationNotification` di `EventServiceProvider`
  (`User` tak implement `MustVerifyEmail` → listener no-op, aman dibiarkan bila ragu)
- **AC:** named route **tetap 35**; login & register tetap berfungsi.

### B4 — Hapus `TransactionController@show`
- [x] Hapus method `show()` (route ✗, view `admin.transactions.show` ✗)
- **AC:** `grep -rn "transactions.show"` → nol.

### B5 — Hapus `PenaltyController@createPenaltyFromTransaction`
- [x] Hapus method (tak dirutekan, duplikat logika `returnBook`)
- **AC:** `grep -rn "createPenaltyFromTransaction"` → nol.

### B6 — Hapus `Transaction::calculateFine()`
- [x] Hapus method — **salah tanda** (`diffInDays($due_date, false)` negatif saat terlambat → selalu 0) dan tak pernah dipanggil
- **AC:** `grep -rn "calculateFine" resources app` → nol.

### B7 — Migration: drop `book_logs`  *(keputusan: HAPUS)*
- [x] `php artisan make:migration drop_book_logs_table`
- [x] `Schema::dropIfExists('book_logs')`
- ⚠️ **Jangan edit** `2026_06_29_214737_create_book_logs_table.php` (sudah berjalan)
- [x] Hapus dead import `Transaction` & `Penalty` di `DatabaseSeeder.php` (sekalian dengan B8/B9? tidak — lakukan di sini, sekalian)
  *(lihat B11)*
- **AC:** `php artisan migrate` sukses; `SHOW TABLES` tanpa `book_logs`.

### B8 — Migration: buang enum `overdue`  *(keputusan: BUANG)*
- [x] `php artisan make:migration remove_overdue_status_from_transactions_table`
- [x] Ubah `status` enum jadi `enum('borrowed','returned')`
  (`DB::statement("ALTER TABLE transactions MODIFY status ENUM('borrowed','returned') NOT NULL DEFAULT 'borrowed'")` — sesuaikan sintaks MariaDB)
- **Alasan:** overdue dihitung real-time oleh `getRemainingDaysAttribute()`, enum mati membingungkan.
- **AC:** `Transaction::where('status','overdue')->count()` tetap 0; tidak ada kode yang menulis `'overdue'`.
  → cek dulu: `grep -rn "'overdue'" app resources`

### B9 — Bersihkan session key mati
- [x] Hapus `session(['last_search' => …])` di `BookController@index`
- [x] Hapus `session(['member_last_search' => …])` di `BookController@memberIndex`
- [x] **Perbaiki bug:** reset `last_category` / `member_last_category` saat filter dikosongkan
  ```php
  session(['last_category' => $request->category ?: '']);
  ```
- ⚠️ `last_category` **DIPERTAHANKAN** — dipakai tombol "Kembali" di 4 view
- **AC:** klik filter → kosongkan → tombol "Kembali" tidak bawa kategori lama.

### B10 — Rapikan `Transaction::getRemainingDaysAttribute()`
- [x] Hapus cabang if/else yang **identik** (keduanya `return (int) $diffInDays`)
- [x] Evaluasi return `999` untuk buku yang sudah kembali — cek pemakaian view dulu:
  `grep -rn "remaining_days" resources/views`
- **AC:** badge status admin & member tidak berubah tampilannya.

### B11 — Hapus 2 dead import di `DatabaseSeeder`
- [x] `use App\Models\Transaction;`
- [x] `use App\Models\Penalty;`
- **AC:** `grep -n "Transaction\|Penalty" database/seeders/DatabaseSeeder.php` → nol.

### B12 — ✅ GATE Epic B
- [x] named route **tetap 35**
- [x] audit `route('...')` → **0 missing** di luar file yang dihapus
- [x] `php artisan view:cache` + `php artisan route:list` sukses
- [x] smoke test manual: login admin → semua menu · login member → semua menu
- [x] `php artisan test` **hijau**
- [x] `php vendor/bin/pint <file yang disentuh>`

---

# ▶ EPIC C — Perbaiki bug bisnis & integritas  *(prioritas 🥉)*

### C0 — Fix symlink `public/storage`  ⬅ **dulu dari yang lain di Epic C**
- [x] `php artisan storage:link --force`
- [x] Verifikasi: `readlink public/storage` → `/home/firmnware/Documents/Laravel/trial_perpustakaan2/storage/app/public`
- [x] Test: admin upload cover buku → cover tampil di index & show
- **AC:** upload baru tampil (sekarang 404 karena symlink menunjuk ke `~/Downloads/...`).

### C1 — `transaction_code` anti-tabrakan
- [x] Ganti `'TRX-' . date('Ymd') . '-' . rand(100, 999)` (hanya 900 kombinasi/hari)
  → `'TRX-' . date('Ymd') . '-' . strtoupper(Str::random(6))`
- [x] Atau: retry loop saat `QueryException` unique violation
- [x] Test: 200 insert berturut-turut → nol duplikat
- **AC:** kolom `varchar(50)` cukup (`TRX-20260930-ABC123` = 19 char).

### C2 — Generator tunggal `member_code`
- [x] Pindahkan ke `Member::booted()` → `creating` hook, **hanya jika `member_code` kosong**
  ```php
  static::creating(fn ($m) => $m->member_code ??= 'MBR-'.str_pad(
      static::withTrashed()->max('id') + 1, 5, '0', STR_PAD_LEFT));
  ```
  *(pakai `lockForUpdate`/transaksi bila ingin aman race)*
- [x] Hapus duplikasi di `RegisteredUserController` (`User::count()`)
- [x] Hapus duplikasi di `MemberController@store` (`Member::count()+1`)
- [x] Pertahankan `DatabaseSeeder` (set kode eksplisit `MBR-00001…00022` → hook tidak menimpa)
- **AC:** kode unik setelah hapus + tambah member; seeder tetap menghasilkan kode eksplisit.

### C3 — Race condition stok
- [x] Di `TransactionController@store`, dalam transaksi:
  ```php
  $book = Book::whereKey($id)->where('available_stock','>',0)->lockForUpdate()->first();
  if (!$book) { DB::rollback(); return back()->with('error','Stok buku tidak tersedia'); }
  ```
- [x] `Book::decreaseStock/increaseStock` → `decrement()` / `increment()` (atomik), bukan read-modify-write
- **AC:** `available_stock` tidak pernah < 0.

### C4 — `BookController@update` tak boleh bikin stok negatif
- [x] Ganti rumus ad-hoc `available_stock + (stock - old_stock)` →
  ```php
  $data['available_stock'] = max(0, $request->stock - $book->transactions()
      ->where('status','borrowed')->count());
  ```
- **AC:** turunkan `stock` saat buku sedang dipinjam → `available_stock` ≥ 0.

### C5 — Ekstrak konstanta ke config
- [x] Buat `config/perpustakaan.php`:
  ```php
  return [
      'fine_per_day'        => 2000,
      'max_active_borrows'  => 3,
      'loan_days_default'   => 7,
  ];
  ```
- [x] Ganti `2000` di **4 lokasi**: `TransactionController@returnBook`,
  `PenaltyController@createPenaltyFromTransaction` *(atau sudah dihapus di B5)*,
  `Transaction::getCurrentFineAttribute`, `Transaction::getWarningMessageAttribute`
- [x] Ganti literal `3` di `Member::canBorrow()` → `config('perpustakaan.max_active_borrows')`
- **AC:** `grep -rn "\* 2000\|2000;" app` → nol.

### C6 — Sinkronkan blokir member
- [x] `MemberMiddleware`: tambah cek `Auth::user()->status === 'active'` → kalau tidak, logout + redirect login
- [x] `MemberController@toggleStatus`: buat mapping eksplisit & eksplisit komentar
  (`members.blocked` ↔ `users.inactive`)
- [x] Test: member diblokir → sesi lama langsung ditendang
- **AC:** tidak ada sesi aktif untuk akun non-aktif.

### C7 — *(opsional)* Validasi durasi pinjam
- [x] `due_date` → `after:borrow_date|before_or_equal:+30 days`
- [x] Opsional: isi default `borrow_date = today`, `due_date = today + loan_days_default`
- **AC:** admin tak bisa set jatuh tempo > 30 hari.

### C8 — Migration index
- [x] `php artisan make:migration add_indexes_for_reporting`
- [x] `transactions (member_id, status)` · `transactions (status)` · `penalties (member_id, status)`
- [x] `books (category_id)` — **cek dulu** apakah sudah terindeks sebagai FK, kalau sudah jangan diduplikasi
- ⚠️ Migration **baru** — jangan edit migration lama
- **AC:** `EXPLAIN` query dashboard member/admin menunjukkan `ref`, bukan `ALL`.

### C9 — Guard hapus buku  ⬅ **temuan #13 (data integrity)**
- [x] `BookController@destroy`: tolak bila masih ada peminjaman aktif
  ```php
  if ($book->transactions()->where('status','borrowed')->exists()) {
      return back()->with('error','Buku masih dipinjam dan tidak bisa dihapus');
  }
  ```
- [x] Eval: buku yang **pernah** dipinjam → opsi soft-delete **atau** ubah FK `transactions.book_id`
  ke `set null` + kolom nullable *(keputusan perlu dikunci sebelum implement)*
- [x] Test: hapus buku dengan peminjaman aktif → ditolak; riwayat transaksi tetap ada
- **AC:** tidak ada transaksi/penalty yang hilang akibat hapus buku.

### C10 — ✅ GATE Epic C
- [x] seluruh Gate di atas
- [x] smoke test: pinjam → kembali terlambat → penalty muncul → bayar → lunas
- [x] upload cover tampil (C0)
- [x] `php artisan test` **hijau** + DB dev utuh

---

# 📌 DI LUAR CAKUPAN (catatan saja — jangan dikerjakan tanpa instruksi)

| Item | Alasan ditunda |
|---|---|
| PHP 8.5 vs Laravel 10 | Risiko kompatibilitas — **jangan** upgrade/downgrade framework |
| Hapus `laravel/breeze` / `laravel/sail` dari `composer.json` | Menyentuh `composer.lock` → risiko > manfaat |
| Hapus `public/build` (136K, gitignored) | Bersih-bersih lokal, tidak mempengaruhi aplikasi |
| Idempoten `DatabaseSeeder` | Butuh `firstOrCreate` di seluruh 151 buku — perubahan besar |
| Seed data transaksi/penalty | Halaman riwayat & denda kosong setelah fresh — butuh keputusan data uji |
| `Phone` validasi `numeric` | Tidak ada laporan bug |
| Policy / Gate sebagai lapis kedua | Hardening, di luar 3 epic |
| Audit log (`book_logs`) | Keputusan sudah diambil: **drop** (B7) |

---

# 📊 Ringkasan

| Epic | Task | Estimasi risiko |
|---|---|---|
| **A** Amankan test & DB | A1–A13 (13 task) | Rendah — perubahan mayoritas di `tests/` + `phpunit.xml` |
| **B** Bersihkan dead code | B1–B12 (12 task) | Rendah — semua target sudah terverifikasi tak terpakai |
| **C** Bug bisnis & integritas | C0–C10 (11 task) | **Sedang–tinggi** — menyentuh logika bisnis & skema DB |
| **Total** | **36 task** | |

**Urutan eksekusi:** A → B → C. **Jangan loncat.** Tanpa Epic A, setiap `php artisan test`
masih bisa menghapus database dev.

---

# 📝 CATATAN EKSEKUSI (keputusan yang diambil di tengah jalan)

| Task | Keputusan | Alasan |
|---|---|---|
| **C9** | Guard memakai **dua cek**: (1) peminjaman aktif → ditolak, (2) riwayat peminjaman apa pun → ditolak | AC "*tidak ada transaksi/penalty yang hilang*" tidak mungkin dipenuhi selama FK masih `ON DELETE CASCADE`. Opsi soft-delete / FK `set null` butuh migrasi + perubahan tampilan; **menolak penghapusan** memberi jaminan penuh tanpa mengubah skema. |
| **A8** | `assertNull(email_verified_at)` → `assertNotNull` | `ProfileController@update` memang tidak menyentuh kolom itu (K12 di Tech Spec). Test lama meng-encode perilaku Breeze yang tidak ada di app ini. |
| **A12** | Test "stok tidak negatif" ditunda ke **C4** | Sengaja tidak ditulis di Epic A supaya suite selalu hijau; bug-nya memang baru diperbaiki di Epic C. |
| **C5** | `config/perpustakaan.php` berisi `fine_per_day`, `max_active_borrows`, `loan_days_default`, `max_loan_days` | `max_loan_days` ditambahkan untuk task C7. |
| **C8** | `books.category_id` **tidak** diindeks | Sudah ada `books_category_id_foreign` — menduplikasi hanya memperlambat INSERT. |
| **fix** | `UserFactory` kini mengisi `role` + `status` | Default-nya hanya ada di migration; model hasil factory yang belum di-reload membawa `null`, sehingga middleware C6 menendang semua user test. |
| **fix** | `MemberFactory` tidak lagi mengisi `member_code` | Kalau diisi, hook `Member::booted()` (C2) tidak pernah jalan — factory harus memakai jalur produksi. |
| **infra** | `config/database.php` memakai `Pdo\Mysql::ATTR_SSL_CA` bila tersedia | PHP 8.5 menandai `PDO::MYSQL_ATTR_SSL_CA` deprecated; menghilangkan noise di setiap run test. |
| **infra** | `phpunit.xml` dimigrasi skema PHPUnit 10 | Menghapus 1 runner deprecation. |
| **infra** | Symlink `public/storage` diperbaiki (`storage:link --force`) | Menunjuk ke `~/Downloads/...`, jadi 153 cover 404. |
| **infra** | Migration 3 baru **sudah dijalankan ke DB dev** | `book_logs` berisi 0 baris (aman di-drop); data dev utuh: books=151, users=23, members=22, tx=3. |

## Status akhir

- **105/105 checkbox selesai** (Epic A → B → C, berurutan).
- **92 test, 289 assertions — hijau**, dari baseline 19 gagal.
- Named route tetap **35**; audit `route()` **0 missing** (sebelumnya 7).
- `view:cache` sukses; `pint --test` PASS pada 19 file yang disentuh.
- DB dev tidak pernah tersentuh `migrate:fresh` (guard A3 terbukti).
