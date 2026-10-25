# 4 — LEGACY DECODER: Hasil Analisis Codebase

> **Project:** `trial_perpustakaan2` — Aplikasi Perpustakaan (Bahasa Indonesia)
> **Tanggal analisis:** 30 Sep 2026
> **Status:** Read-only. Belum ada kode yang diubah saat analisis ini dibuat.
> **Load ulang:** ucapkan *"baca legacy"* / *"load legacy analysis"*.

---

## 1. Stack & Konfigurasi

| Item | Nilai |
|---|---|
| Framework | Laravel **10** (PHP `^8.1`), monolith, tanpa SPA |
| PHP runtime | **8.5.10** ⚠️ di luar rentang uji Laravel 10 (s/d 8.3) |
| Auth | Breeze **1.29** (require-dev), Sanctum terpasang — hanya dipakai `GET api/user` |
| UI | **Bootstrap 5.3.8 (CDN)** + Font Awesome 6.5.1. Tailwind / Alpine / Vite **tidak dipakai** (nol `@vite` di view) |
| Database | MySQL/MariaDB `perpustakaan_webprogramming` @127.0.0.1 (root/1234) |
| Queue / Mail | `sync` / smtp `mailpit` — **tidak pernah dipakai** |
| Session | `file`, lifetime 120 menit |
| Tests | PHPUnit 10 — **25 method di 9 file, ±19 gagal** (lihat §7) |
| Branch | `menambahkan_AGENTS.md` *(sebelumnya `menambahkan_database_seeder`)* |
| Working tree | Bersih. 6 file yang tadinya ter-modifikasi sudah di-commit `1e0105b` "Menambahkan file AGENTS.md" (sudah di-push). Kini hanya `.agents/` yang untracked. |

**Command penting**
```bash
php artisan serve                      # serve
php artisan migrate:fresh --seed       # rebuild data dev
php artisan test                       # ⚠️ lihat §7 sebelum dijalankan
php vendor/bin/pint <file>             # format (wajib pakai `php`, bin tak executable)
npm run dev / build                    # TIDAK diperlukan
```

**Login seed:** `admin@gmail.com` / `password123` · `member@gmail.com` / `password123`

---

## 2. Alur Request & Entry Point

```
GET /  ──(guest)──►  redirect route('login')

POST /login
  └─► LoginRequest::authenticate()
        ├─ RateLimiter: 5 percobaan, lockout
        ├─ Auth::attempt(+ remember)
        ├─ status !== 'active'  → logout + error "Akun Anda tidak aktif"
        ├─ stamp last_login_at = Carbon::now()
        └─ isAdmin() ? redirect intended admin.dashboard
                     : redirect intended member.dashboard

GET /dashboard (auth) ──► isAdmin() ? admin.dashboard : member.dashboard
```

**Role gating** — `app/Http/Kernel.php`:
```php
'admin'  => \App\Http\Middleware\AdminMiddleware::class,
'member' => \App\Http\Middleware\MemberMiddleware::class,
```
Keduanya: `Auth::check()` → kalau belum login redirect `login`; lalu `User::isAdmin()` / `isMember()` → kalau salah, `abort(403)`.

- `AdminMiddleware` / `MemberMiddleware` → cek `users.role` enum (`admin` | `member`)
- `RedirectIfAuthenticated` (alias `guest`) → redirect ke `RouteServiceProvider::HOME = '/dashboard'`

> **Catatan:** login tidak pernah mengarah ke `/dashboard`, langsung ke dashboard per-role.

---

## 3. Peta Route

**39 route terdaftar, 35 bernama** — semuanya di `routes/web.php` (satu-satunya file yang dimuat; lihat §5).

### Guest (`middleware guest`)
| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/` | — | closure → `redirect route('login')` |
| GET | `/login` | `login` | `AuthenticatedSessionController@create` |
| POST | `/login` | — | `AuthenticatedSessionController@store` |
| GET | `/register` | `register` | `RegisteredUserController@create` |
| POST | `/register` | — | `RegisteredUserController@store` |

### Auth (`middleware auth`)
| Method | URI | Name | Handler |
|---|---|---|---|
| POST | `/logout` | `logout` | `AuthenticatedSessionController@destroy` |
| GET | `/profile` | `profile.edit` | `ProfileController@edit` |
| PUT | `/profile` | `profile.update` | `ProfileController@update` |
| PUT | `/profile/password` | `profile.password` | `ProfileController@updatePassword` |
| GET | `/dashboard` | `dashboard` | closure → dispatch per-role |

### Admin (`middleware admin`, prefix `/admin`, name `admin.`)
| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/admin/dashboard` | `admin.dashboard` | `AdminDashboardController@index` |
| * | `/admin/books` | `admin.books.*` | `Route::resource` (7 route: index/create/store/show/edit/update/destroy) |
| GET | `/admin/members` | `admin.members.index` | `MemberController@index` |
| GET | `/admin/members/create` | `admin.members.create` | `MemberController@create` |
| POST | `/admin/members` | `admin.members.store` | `MemberController@store` |
| PUT | `/admin/members/{member}/toggle-status` | `admin.members.toggle-status` | `MemberController@toggleStatus` |
| GET | `/admin/transactions` | `admin.transactions.index` | `TransactionController@index` |
| GET | `/admin/transactions/create` | `admin.transactions.create` | `TransactionController@create` |
| POST | `/admin/transactions` | `admin.transactions.store` | `TransactionController@store` |
| PUT | `/admin/transactions/{transaction}/return` | `admin.transactions.return` | `TransactionController@returnBook` |
| GET | `/admin/penalties` | `admin.penalties.index` | `PenaltyController@index` |
| GET | `/admin/penalties/{penalty}/pay` | `admin.penalties.pay.form` | `PenaltyController@showPayForm` |
| PUT | `/admin/penalties/{penalty}/pay` | `admin.penalties.pay` | `PenaltyController@payPenalty` |

### Member (`middleware member`, prefix `/member`, name `member.`)
| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/member/dashboard` | `member.dashboard` | `MemberDashboardController@index` |
| GET | `/member/books` | `member.books.index` | `BookController@memberIndex` |
| GET | `/member/books/{book}` | `member.books.show` | `BookController@show` |
| GET | `/member/my-borrows` | `member.borrows` | `MemberDashboardController@myBorrows` |
| GET | `/member/my-penalties` | `member.penalties` | `MemberDashboardController@myPenalties` |

### Lainnya (bukan aplikasi)
`sanctum.csrf-cookie`, `api/user`, 3 route `_ignition/*`

### ⚠️ Route yang TIDAK terdaftar (404)
`routes/auth.php` **tidak pernah dimuat** — `RouteServiceProvider` hanya memuat `web.php` + `api.php`:
`password.request`, `password.email`, `password.reset`, `password.store`, `password.update`,
`verification.notice`, `verification.verify`, `verification.send`, `password.confirm`

---

## 4. Domain Model & Entity-Relationship

```
┌────────┐   1:1   ┌─────────┐   1:N   ┌───────────────┐   N:1   ┌────────┐
│ users  │────────►│ members │────────►│ transactions  │────────►│ books  │
└────────┘         └─────────┘         └───────────────┘         └────────┘
     │                   │                    │   ▲                  │
     │                   │         1:1        │   │ N:1              │ N:1
     │                   └────────────┐       │   │                  │
     │                                ▼       │   │                  ▼
     │                           ┌────────────┴─┐ │           ┌────────────┐
     └── (role, status)          │  penalties   │◄┘           │ categories │
                                 └──────────────┘             └────────────┘

       book_logs  ← TABEL ADA, TIDAK PERNAH DITULIS / DIBACA (tanpa model)
```

### users
`id, name, email(unik), email_verified_at, password, role enum(admin|member) default 'member', status enum(active|inactive) default 'active', phone varchar(15), address, last_login_at, remember_token, timestamps`

### members
`id, user_id (FK unik, cascade), member_code varchar(50) unik, join_date date, status enum(active|blocked) default 'active', timestamps`

### categories
`id, name varchar(100), description text, timestamps`

### books
`id, category_id (FK nullable, set null), title, author, publisher, year int, isbn varchar(20) unik, cover, stock default 1, available_stock default 1, description, timestamps`

### transactions
`id, transaction_code varchar(50) unik, member_id (FK cascade), book_id (FK cascade), borrow_date, due_date, return_date nullable, status enum(borrowed|returned|overdue) default 'borrowed', timestamps`

### penalties
`id, transaction_id (FK cascade), member_id (FK cascade), days_late int, fine_amount decimal(10,2), status enum(unpaid|paid) default 'unpaid', paid_date date nullable, timestamps`

### book_logs
`id, book_id (FK cascade), transaction_id (FK nullable, set null), type enum(borrow|return|add|reduce), quantity, description, timestamps`
→ **ORPHAN**: tidak ada model `BookLog`, tidak ada kode yang menulis/membaca.

**Index:** hanya yang dibuat otomatis (PK + unique + FK InnoDB).
**Belum ada index** untuk: `transactions(member_id, status)`, `transactions(status)`,
`penalties(member_id, status)`, `books(category_id)`.

---

## 5. Business Rules (terekstrak dari kode)

| # | Rule | Lokasi kode |
|---|---|---|
| 1 | Maksimal **3 buku aktif** per member (`< 3`) | `Member::canBorrow()` |
| 2 | Dilarang pinjam bila ada **denda belum lunas** | `TransactionController@store` → `Member::hasUnpaidPenalties()` |
| 3 | Member harus `members.status === 'active'` untuk pinjam | `Member::canBorrow()` |
| 4 | User harus `users.status === 'active'` untuk login | `AuthenticatedSessionController@store` |
| 5 | Denda **Rp 2.000/hari**, hanya dibuat saat pengembalian terlambat | `TransactionController@returnBook` |
| 6 | Denda **real-time** dihitung dari `Carbon::now()` vs `due_date` sebelum buku kembali | `Transaction::getCurrentFineAttribute()` |
| 7 | `available_stock` turun saat pinjam, naik saat kembali | `Book::decreaseStock/increaseStock` |
| 8 | Blokir member → `members.status=blocked` **dan** `users.status=inactive` (enum beda!) | `MemberController@toggleStatus` |
| 9 | Transaksi tak boleh dikembalikan dua kali | `TransactionController@returnBook` guard `status === 'returned'` |
| 10 | Denda tak boleh dibayar dua kali | `PenaltyController@showPayForm` / `@payPenalty` |
| 11 | Password minimal 6 karakter | `RegisteredUserController`, `MemberController`, `ProfileController` |
| 12 | Filter katalog: search `title LIKE`, filter `category_id`, 20/hal (buku), 10/hal (member) | `BookController@index` / `@memberIndex` |

**Durasi pinjam** diisi manual admin; validasi hanya `due_date after:borrow_date` — tidak ada batas min/max.

---

## 6. Struktur & Inventaris

### Controllers hidup (9)
`AdminDashboardController`, `MemberDashboardController`, `BookController`, `MemberController`,
`TransactionController`, `PenaltyController`, `ProfileController`,
`Auth/AuthenticatedSessionController`, `Auth/RegisteredUserController`

### Controllers MATI (7) — hanya direferensikan `routes/auth.php`
`ConfirmablePasswordController`, `EmailVerificationNotificationController`,
`EmailVerificationPromptController`, `NewPasswordController`, `PasswordController`,
`PasswordResetLinkController`, `VerifyEmailController`

### Models (6)
`User`, `Member`, `Category`, `Book`, `Transaction`, `Penalty` — **tanpa `BookLog`**

### Views hidup (19)
`layouts/{app,guest}` · `auth/{login,register}` · `profile/edit`
`admin/{dashboard, books/{index,create,edit,show}, members/{index,create}, transactions/{index,create}, penalties/{index,pay}}`
`member/{dashboard, borrows, penalties, books/{index,show}}`

### Views MATI (10) — tidak dirender route mana pun
`dashboard.blade.php` · `layouts/navigation.blade.php` · `welcome.blade.php`
`auth/{forgot-password,reset-password,verify-email,confirm-password}`
`profile/partials/{delete-user-form,update-password-form,update-profile-information-form}`

### Komponen (13) — SEMUanya hanya dipakai view mati
`application-logo, auth-session-status, danger-button, dropdown, dropdown-link, input-error, input-label, modal, nav-link, primary-button, responsive-nav-link, secondary-button, text-input`

> ⚠️ **Koreksi penting:** `App\View\Components\{AppLayout,GuestLayout}` **berfungsi normal**
> (`return view('layouts.app')` / `view('layouts.guest')`). Alasan menghapusnya adalah
> **tidak ada route yang merendernya** — *bukan* karena view komponennya hilang.

### Seeder
`DatabaseSeeder` (482 baris) membuat: **1 admin + 22 member + 15 kategori + 151 buku**
(153 file cover di `storage/app/public/covers`).

> **Tidak ada transaksi & tidak ada penalty yang di-seed** → dashboard, riwayat, dan halaman
> denda **kosong** saat `migrate:fresh --seed`.
> Seeder **tidak idempoten** (unique email/ISBN) → `db:seed` kedua akan error.
> Impor `Transaction` & `Penalty` dipakai tapi tak pernah dipakai (dead import).

---

## 7. 🔴 Temuan

### KRITIS

| # | Temuan | Lokasi |
|---|---|---|
| **1** | **`php artisan test` MENGHAPUS database dev.** `phpunit.xml` tidak set `DB_CONNECTION` (baris sqlite di-comment), **tidak ada `.env.testing`**, `pdo_sqlite` **tidak terpasang**, semua test pakai `RefreshDatabase` → `migrate:fresh` ke MySQL `perpustakaan_webprogramming` | `phpunit.xml` |
| **2** | **Symlink `public/storage` salah arah** → `/home/firmnware/Downloads/trial_perpustakaan2/storage/app/public`. Upload cover disimpan ke `Documents/...` tapi disajikan dari `Downloads/...` → **cover baru 404**. Sekarang kebetulan sama-sama 153 file | `public/storage` |
| **3** | **±19 dari 25 method test gagal.** Detail:<br>· `ExampleTest` → `/` 302, bukan 200<br>· `ProfileTest` 4 gagal → `PATCH`/`DELETE /profile`, route hanya `PUT` (405)<br>· `AuthenticationTest` + `RegistrationTest` → expect `/dashboard`, aktual `/member/dashboard`<br>· `EmailVerificationTest` (3), `PasswordResetTest` (4), `PasswordConfirmationTest` (3) → route tak terdaftar (404)<br>· `PasswordUpdateTest` (2) → `PUT /password`, route `/profile/password` (404) + assertion bag `updatePassword` tak sesuai impl | `tests/Feature/**` |

### BUG / RISIKO FUNGSIONAL

| # | Temuan | Lokasi |
|---|---|---|
| **4** | **`BookController@destroy` menghapus buku tanpa guard** → FK `transactions.book_id` = `cascade`, lalu `penalties.transaction_id` = `cascade` → **1 klik hapus buku menghapus seluruh riwayat peminjaman & denda** | `BookController.php:146` |
| **5** | `TransactionController@show` merender `admin.transactions.show` — route ✗, view ✗ → dead code | `TransactionController.php:140` |
| **6** | `Transaction::calculateFine()` **salah tanda** (`return_date->diffInDays($due_date, false)` → negatif saat terlambat → selalu return 0) dan **tidak pernah dipanggil** | `Transaction.php:39` |
| **7** | `transaction_code` = `'TRX-'.date('Ymd').'-'.rand(100,999)` → hanya **900 kombinasi/hari** → kena unique constraint | `TransactionController.php:59` |
| **8** | `member_code` dihasilkan **3 cara berbeda**: `Member::count()+1` (admin), `User::count()` (register), `str_pad($i+3)` (seeder) → mudah duplikat setelah delete | 3 lokasi |
| **9** | **Race condition stok**: `decreaseStock()` read-modify-write tanpa lock, tanpa `where available_stock > 0` → bisa negatif | `Book.php:55` |
| **10** | `BookController@update`: `available_stock = available_stock + (stock - old_stock)` → **bisa negatif** jika stok diturunkan saat buku dipinjam | `BookController.php:127` |
| **11** | Angka denda `2000` di-hardcode di **4 tempat**; batas `3` buku literal | Controller ×2, Model ×2 |
| **12** | Blokir member tidak mengeluarkan sesi aktif; 2 kolom status beda enum (`blocked` vs `inactive`) bisa tidak sinkron | `MemberController@toggleStatus` |
| **13** | `PenaltyController@createPenaltyFromTransaction` **tidak dirutekan / tak dipanggil** — duplikat logika `returnBook` | `PenaltyController.php:70` |

### DEAD CODE / SISA BREEZE

- `routes/auth.php` (11 route tak dimuat) + 7 controller Auth + 4 view auth → **tidak bisa diakses**
- `resources/views/profile/partials/*` (3) — merender `route('password.update')`, `route('profile.destroy')`, `route('verification.send')` yang tak ada; tak di-`@include` siapa pun
- `resources/views/dashboard.blade.php`, `layouts/navigation.blade.php`, `welcome.blade.php`
- 13 `resources/views/components/*` + `app/View/Components/{AppLayout,GuestLayout}.php`
- `book_logs` — tabel tanpa model
- `transactions.status` enum **`'overdue'` tidak pernah di-set** (overdue dihitung on-the-fly)
- `session(['last_search'])` / `member_last_search` ditulis, **tak pernah dibaca**
- `Transaction::getRemainingDaysAttribute()` — cabang if/else identik; return `999` untuk buku kembali
- `DatabaseSeeder` — impor `Transaction`, `Penalty` tak terpakai
- `composer.json` — `laravel/breeze` & `laravel/sail` tak direferensikan di `app/config/routes`
- `public/build` (136K, gitignored) — output vite, tak dipakai

### AUDIT YANG LULUS ✅

**Integritas route — BERSIH.** Seluruh `route('...')` di view + app dicocokkan terhadap 35 route bernama:
7 pemanggilan route tak terdaftar, **semuanya berada di 9 file yang memang masuk daftar hapus**.
**Tidak ada view hidup yang memanggil route mati.**

> `admin.members.toggle-status` sempat terbaca "tak terpakai" — artefak CRLF, ternyata dipakai di
> `admin/members/index.blade.php:55`.

### STRUKTUR / TESTABILITY

- Logic bisnis (denda, stok, aturan pinjam) menyebar di **Model + Controller**, tanpa Service Layer
- Hanya `LoginRequest` yang pakai FormRequest; buku/member/transaksi pakai `$request->validate()` inline
- Tidak ada Policy/Gates — role hanya via middleware prefix URL
- Tidak ada test domain sama sekali (CRUD, peminjaman, pengembalian, denda, RBAC)

---

## 8. Riwayat Perubahan Terakhir (sudah di-commit)

Commit **`1e0105b` — "Menambahkan file AGENTS.md"** di branch `menambahkan_AGENTS.md`
(sudah di-push ke origin) berisi 7 file / +207 −134:

`AGENTS.md` (baru) · `MemberController` · `TransactionController` · `Models/Transaction` ·
`DatabaseSeeder` · `admin/members/create` · `admin/transactions/index`

**Isi perubahan:**
- Perbaikan logika pengembalian — penalty **hanya** dibuat saat `daysLate > 0`
- Hapus atribut `fine_status` / `fine_status_text` / `fine_status_color` dari model
- Tampilan tabel transaksi: tanggal kembali + label "Tidak ada denda"
- Form tambah member → 2 kolom + `phone max:15`
- Format array buku di seeder

> ✅ Aman — sudah jadi titik kembalikan sebelum mulai refactor Epic A/B/C.

**Branch lain yang tersedia:**
`main` · `add_database_seeder_dan_pagination` · `denda_real_time` · `filter_katalog_buku`

---

## 9. Risiko Lingkungan (catat, jangan "diperbaiki")

| Risiko | Keterangan |
|---|---|
| PHP **8.5.10** vs Laravel 10 | Di luar rentang uji resmi (s/d 8.3). Aplikasi jalan, tapi bisa jadi sumber bug aneh di dependency. |
| `pdo_sqlite` tidak terpasang | Strategi sqlite `:memory:` untuk test **tidak tersedia** tanpa install extension. |
| `DatabaseSeeder` tidak idempoten | `db:seed` kedua error (unique email/ISBN). |
| `AGENTS.md` melarang akses `/tmp` | Simpan artefak test di dalam project bila perlu. |
