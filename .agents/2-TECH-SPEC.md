# 2 — TECH SPEC: Perpustakaan The Origin

> **Project:** `trial_perpustakaan2` · Laravel 10 monolith
> **Sumber:** hasil reverse-engineering — lihat `.agents/4-LEGACY-DECODER.md`
> **Status:** dokumen deskriptif (kondisi *as-is*) + rencana perbaikan. Untuk review sebelum eksekusi task.

---

## 1. Tech Stack

| Layer | Teknologi | Versi / Catatan |
|---|---|---|
| Language | PHP | **8.5.10** (rentang resmi Laravel 10: s/d 8.3 — risiko, jangan "diperbaiki") |
| Framework | Laravel | `^10.0` — monolith, server-rendered, tanpa SPA |
| Auth | Laravel Breeze | `^1.29` (require-dev). Hanya login/register yang dipakai; fitur reset/verify/confirm **tak dirutekan** |
| API token | Laravel Sanctum | `^3.2` — hanya `GET /api/user`, tidak dipakai aplikasi |
| Frontend | Bootstrap 5.3.8 + Font Awesome 6.5.1 | **CDN**, tanpa build step |
| Build tool | Vite / Tailwind / Alpine | **Tidak dipakai** — sisa Breeze (`npm run dev/build` tidak diperlukan) |
| Database | MySQL / MariaDB | `perpustakaan_webprogramming` @127.0.0.1:3306 |
| Queue | `sync` | Tidak ada pekerjaan async |
| Mail | smtp `mailpit:1025` | **Tidak pernah terpakai** (fitur mail tidak ada) |
| Session | `file`, 120 menit | |
| Testing | PHPUnit `^10.0` | `RefreshDatabase` (lihat §5 risiko) |
| Linting | Laravel Pint `^1.0` | Pakai `php vendor/bin/pint` (bin tidak executable), **jangan repo-wide** |
| Deployment | `php artisan serve` | Sail/Docker tidak dipakai |

**Dependency tak terpakai (biarkan — menyentuh `composer.lock` = risiko > manfaat):**
`laravel/breeze`, `laravel/sail`

---

## 2. DB Design

### 2.1 Entity-Relationship

```
┌────────┐   1:1   ┌─────────┐   1:N   ┌───────────────┐   N:1   ┌────────────┐
│ users  │────────►│ members │────────►│ transactions  │────────►│   books    │
└────────┘         └─────────┘         └───────────────┘         └────────────┘
                                                                 │ N:1        │
     │                   │                    │  ▲               ▼            │
     │                   │         1:1        │  │ N:1      ┌────────────┐    │
     │                   └────────────┐       │  │          │ categories │    │
     │                                ▼       │  │          └────────────┘    │
     │                            ┌───────────┴──┴┐                          │
     └── (role, status)           │   penalties   │                          │
                                  └───────────────┘                          │
                                                                             │
                    book_logs ── orphan (tabel ada, tanpa model) ◄───────────┘
```

### 2.2 Skema

**users**
| Kolom | Tipe | Constraint |
|---|---|---|
| id | bigint | PK |
| name / email / password | varchar | email **unik** |
| email_verified_at | timestamp | nullable |
| role | enum | `admin`,`member` default `member` |
| status | enum | `active`,`inactive` default `active` |
| phone | varchar(15) | nullable |
| address | text | nullable |
| last_login_at | timestamp | nullable |

**members**
| Kolom | Tipe | Constraint |
|---|---|---|
| user_id | bigint | FK → users, **unik**, cascade delete |
| member_code | varchar(50) | **unik** |
| join_date | date | |
| status | enum | `active`,`blocked` default `active` |

**books**
| Kolom | Tipe | Constraint |
|---|---|---|
| category_id | bigint | FK → categories, nullable, **set null** |
| isbn | varchar(20) | **unik** |
| stock / available_stock | int | default 1 |
| cover | varchar | path relatif `covers/…` di disk `public` |

**transactions**
| Kolom | Tipe | Constraint |
|---|---|---|
| transaction_code | varchar(50) | **unik** |
| member_id | bigint | FK → members, **cascade** |
| book_id | bigint | FK → books, **cascade** ⚠️ lihat §2.4 |
| borrow_date / due_date | date | |
| return_date | date | nullable |
| status | enum | `borrowed`,`returned`,`overdue` default `borrowed` |

**penalties**
| Kolom | Tipe | Constraint |
|---|---|---|
| transaction_id | bigint | FK → transactions, **cascade** ⚠️ |
| member_id | bigint | FK → members, **cascade** |
| days_late | int | |
| fine_amount | decimal(10,2) | |
| status | enum | `unpaid`,`paid` default `unpaid` |
| paid_date | date | nullable |

**categories** — `name varchar(100)`, `description text`
**book_logs** — `book_id(FK cascade)`, `transaction_id(FK nullable, set null)`, `type enum(borrow|return|add|reduce)`, `quantity`, `description` → **ORPHAN**

### 2.3 Index yang perlu ditambahkan (migration baru)

| Tabel | Kolom | Alasan |
|---|---|---|
| `transactions` | `(member_id, status)` | dashboard member: `WHERE member_id = ? AND status = 'borrowed'` |
| `transactions` | `(status)` | dashboard admin: `WHERE status = 'borrowed'` |
| `penalties` | `(member_id, status)` | `hasUnpaidPenalties()`, ringkasan denda |
| `books` | `(category_id)` | filter katalog (juga sudah jadi FK index, konfirmasi dulu sebelum menambah) |

> **Aturan:** selalu tambah **migration baru**. Jangan pernah mengedit migration yang sudah
> berjalan di database.

### 2.4 ⚠️ Integritas data — cascade berbahaya

```php
transactions.book_id        → onDelete('cascade')
penalties.transaction_id    → onDelete('cascade')
```
`BookController@destroy` tidak punya guard → **hapus 1 buku = seluruh riwayat peminjaman
dan denda terkait hilang permanen.**

**Opsi perbaikan:**
- **(A) Guard** — tolak hapus bila masih ada `status = 'borrowed'` *(minimal, dipilih)*
- **(B) Soft delete** — `Book` pakai `SoftDeletes`, riwayat tetap utuh
- **(C) Ubah FK** — `transactions.book_id` → `set null` + `book_id` nullable *(perubahan skema paling besar)*

**Rekomendasi:** **A** untuk sekarang + **B** bila riwayat wajib dipertahankan.

---

## 3. Interface

### 3.1 Route Map

Lihat `.agents/4-LEGACY-DECODER.md` §3 untuk tabel lengkap (39 route, 35 bernama).

Ringkas:

```
GUEST   /login  /register  /
AUTH    /logout  /profile  /profile/password  /dashboard
ADMIN   /admin/dashboard
        /admin/books*            (resource, 7 route)
        /admin/members           index, create, store, {member}/toggle-status
        /admin/transactions      index, create, store, {transaction}/return
        /admin/penalties         index, {penalty}/pay (GET + PUT)
MEMBER  /member/dashboard  /member/books  /member/books/{book}
        /member/my-borrows  /member/my-penalties
```

**Tidak terdaftar (404):** seluruh `routes/auth.php` — reset password, verifikasi email,
confirm password.

### 3.2 Inventaris View

**Layouts (2)**
| View | Dipakai oleh |
|---|---|
| `layouts/app.blade.php` | semua halaman terautentikasi (navbar Bootstrap, `@yield('content')`) |
| `layouts/guest.blade.php` | login, register (gradient ungu) |

**Hidup (19)**
```
auth/{login,register}
profile/edit
admin/{dashboard,
       books/{index,create,edit,show},
       members/{index,create},
       transactions/{index,create},
       penalties/{index,pay}}
member/{dashboard,borrows,penalties,books/{index,show}}
```

**Mati (10)** — tidak dirender route mana pun → kandidat hapus
```
dashboard.blade.php
layouts/navigation.blade.php
welcome.blade.php
auth/{forgot-password,reset-password,verify-email,confirm-password}
profile/partials/{delete-user-form,update-password-form,update-profile-information-form}
```

**Komponen Blade (13)** — hanya dipakai view mati → kandidat hapus
```
application-logo  auth-session-status  danger-button  dropdown  dropdown-link
input-error  input-label  modal  nav-link  primary-button
responsive-nav-link  secondary-button  text-input
+ app/View/Components/{AppLayout,GuestLayout}.php
```

> ⚠️ `AppLayout`/`GuestLayout` **berfungsi normal** — alasan hapusnya adalah
> *"tidak dirender route apa pun"*, bukan *"view komponennya tidak ada"*.

### 3.3 Konvensi UI
- Bahasa **Indonesia** untuk semua string antarmuka
- Notifikasi flash: `session('success')` hijau, `session('error')` merah — dirender di layout
- Error validasi: `@error` + `is-invalid`
- Bootstrap 5 dari CDN + Font Awesome; layout utama `@yield('content')`, opsional `@stack('scripts')`

---

## 4. Alur (Flow)

### 4.1 Autentikasi
```
POST /login
  LoginRequest::authenticate()
    RateLimiter::tooManyAttempts(key, 5)  → lockout
    Auth::attempt(email, password, remember)
      gagal → RateLimiter::hit() + ValidationException 'auth.failed'
      sukses → RateLimiter::clear()
  session()->regenerate()
  status !== 'active' → Auth::logout() + error "Akun Anda tidak aktif"
  last_login_at = now()
  isAdmin() ? redirect intended admin.dashboard : redirect intended member.dashboard

POST /logout → logout() + invalidate() + regenerateToken() → redirect '/'
```

**Registrasi** (`POST /register`): validasi → `User::create(role=member, status=active)` →
`Member::create(member_code=…)` → `event(new Registered)` → `Auth::login()` → `member.dashboard`

### 4.2 Peminjaman buku (admin)
```
GET  /admin/transactions/create
       members  = Member WHERE status='active'
       books    = Book WHERE available_stock > 0
POST /admin/transactions
  1. validate: member_id exists, book_id exists, borrow_date date,
               due_date after:borrow_date
  2. !$member->canBorrow()              → error "maks 3 buku / status tidak aktif"
  3. $member->hasUnpaidPenalties()      → error "denda belum dibayar"
  4. !$book->isAvailable()              → error "stok tidak tersedia"
  5. BEGIN TX
       Transaction::create(status=borrowed, code='TRX-Ymd-rand(100,999)')
       $book->decreaseStock()
     COMMIT  (ROLLBACK + error message bila exception)
  → redirect index + "Peminjaman berhasil! Kode: …"
```

### 4.3 Pengembalian buku (admin)
```
PUT /admin/transactions/{t}/return
  guard: status === 'returned' → error "Buku sudah dikembalikan"
  BEGIN TX
    returnDate = Carbon::today()
    if returnDate > due_date:
        daysLate  = returnDate.diffInDays(due_date)   // absolut → positif
        fine      = daysLate * 2000
    update transaction(return_date, status='returned')
    book->increaseStock()
    if daysLate > 0 && fine > 0:
        Penalty::create(days_late, fine_amount, status='unpaid')
  COMMIT
  → "…terlambat N hari, denda Rp X" | "…tepat waktu!"
```

### 4.4 Denda real-time (tanpa perlu record penalty)
```php
Transaction::getHasFineAttribute()      // returned → penalty()->exists(); else now > due_date
Transaction::getCurrentFineAttribute()  // returned → penalty.fine_amount; else selisih hari × 2000
Transaction::getLateDaysAttribute()     // returned → penalty.days_late;   else selisih hari
Transaction::getRemainingDaysAttribute()// returned → 999; else (int) diffInDays
Transaction::getStatusColorAttribute()  // success | danger(<0) | warning(≤1) | info
Transaction::getStatusTextAttribute()   // "Sudah Dikembalikan" | "Terlambat N hari" | "Sisa N hari"
Transaction::getWarningMessageAttribute() // pesan peringatan untuk badge UI
```

### 4.5 Pembayaran denda (admin)
```
GET /admin/penalties/{p}/pay   guard status='paid' → error
PUT /admin/penalties/{p}/pay   guard status='paid' → error
  BEGIN TX  →  update(status='paid', paid_date=now())  COMMIT
  → redirect index + "Denda berhasil dibayar!"
```
> Tidak ada nominal uang yang dicatat — hanya status + tanggal.

### 4.6 Katalog (member & admin)
```
GET /admin/books  |  GET /member/books
  ?search=…  → title LIKE '%…%'   → session('last_search')        ⚠️ tak pernah dibaca
  ?category=…→ category_id = …    → session('last_category')       ✅ dipakai tombol "Kembali"
  paginate(20) + appends(query)
```
> **Bug:** saat filter dikosongkan (`?category=`), `last_category` **tidak di-reset** →
> tombol "Kembali" membawa ke kategori lama.

### 4.7 Blokir member (admin)
```
PUT /admin/members/{m}/toggle-status
  members.status : active ⇄ blocked
  users.status   : blocked → inactive ; active → active
```
> ⚠️ Dua kolom, dua enum berbeda. Sesi yang sudah login **tidak ikut dikeluarkan**.

---

## 5. Keamanan

### 5.1 Yang sudah ada ✅

| Area | Implementasi |
|---|---|
| **Autorisasi role** | `admin` / `member` middleware → `abort(403)` + cek `Auth::check()` |
| **Brute-force** | `LoginRequest::ensureIsNotRateLimited()` — 5 percobaan + `Lockout` event |
| **CSRF** | `VerifyCsrfToken` pada grup `web`; semua form pakai `@csrf` |
| **Password** | Hash bcrypt (`Hash::make` / `Hash::check`), min 6 char |
| **Session fixation** | `session()->regenerate()` saat login, `invalidate()` + `regenerateToken()` saat logout |
| **Validasi input** | `$request->validate()` inline di tiap `store`/`update` |
| **XSS** | Blade `{{ }}` auto-escape; tidak ada `{!! !!}` di view |
| **Upload** | `image|mimes:jpeg,png,jpg,gif|max:2048`; nama file di-`Str::slug()` |
| **Mass assignment** | `$fillable` eksplisit di semua model |
| **Rate limit API** | `RouteServiceProvider` → 60/menit per user/IP |

### 5.2 Celah & kelemahan ⚠️

| # | Celah | Dampak | Perbaikan |
|---|---|---|---|
| K1 | **Akses antar-role hanya di level URL prefix** — tanpa Policy/Gate | Endpoint baru yang lupa middleware = bocor | Tambah `Gate` / `BookPolicy` sebagai lapis kedua |
| K2 | **`admin/books/{book}` (resource) punya route `show`** dan `BookController@show` bercabang ke view member/admin berdasarkan role | Aman saat ini, tapi pembagian view di controller rapuh | Pisahkan handler per-role |
| K3 | **Hapus buku tanpa guard** (§2.4) | Kehilangan data permanen | Task C9 |
| K4 | **Sesi tak dikeluarkan saat member diblokir** | Member diblokir tetap bisa pakai sesi lama | Task C6 — cek `users.status` di middleware |
| K5 | **Race condition stok** — tanpa `lockForUpdate`, tanpa cek `> 0` atomik | `available_stock` bisa negatif → over-book | Task C3 |
| K6 | **`transaction_code` bisa tabrakan** (900 kombinasi/hari) | 500 error pada insert | Task C1 |
| K7 | **Tidak ada batas durasi pinjam** — `due_date` bebas | Admin bisa set due date 1 tahun lagi | Task C7 (opsional) |
| K8 | **`phpunit.xml` menunjuk DB dev** | `migrate:fresh` = **hancurkan data dev** | **Task A1–A3 (dulu dari semuanya)** |
| K9 | **Symlink `public/storage` salah arah** | Cover upload tak tampil | Task C0 |
| K10 | **Tidak ada audit log** — `book_logs` orphan | Tak ada jejak siapa mengubah stok | Task B7 (drop **atau** implement) |
| K11 | `phone` divalidasi `max:15` tapi seeder bisa menghasilkan hingga 12 digit | Aman, tapi validasi tidak `numeric` | Opsional: `numeric|digits_between:9,15` |
| K12 | **User bisa mengubah email** tanpa verifikasi ulang | Akun bisa dipindah ke email lain | Opsional: `MustVerifyEmail` (tapi butuh SMTP) |

### 5.3 Status fitur autentikasi

| Fitur | Status |
|---|---|
| Login + remember + rate limit | ✅ aktif |
| Register + auto-buat `Member` | ✅ aktif |
| Ganti password via Profil | ✅ aktif |
| Edit profil | ✅ aktif |
| **Lupa / reset password** | ❌ **404** (`routes/auth.php` tak dimuat) |
| **Verifikasi email** | ❌ **404** — juga `User` tidak implement `MustVerifyEmail` |
| **Confirm password** | ❌ **404** |
| **Hapus akun** | ❌ **tidak ada route** (`profile.destroy` tak didefinisikan) |

> **Keputusan:** hapus ketiga fitur mati. Ganti password tetap tersedia via halaman Profil.

---

## 6. Rencana Perbaikan (ringkas)

Lihat `.agents/3-TASKS.md` untuk daftar task lengkap dengan acceptance criteria.

| Epic | Prioritas | Fokus |
|---|---|---|
| **A** | 🥇 1 | **Amankan test & DB** — buat DB test, perbaiki 19 test gagal, tambah factory + test domain |
| **B** | 🥈 2 | **Bersihkan dead code** — 10 view, 13 komponen, `routes/auth.php`, 7 controller, orphan `book_logs`, enum `overdue` |
| **C** | 🥉 3 | **Bug bisnis & integritas** — symlink storage, kode unik, race stok, tarif denda ke config, guard hapus buku, index |

**Gate wajib tiap epic**
1. `php artisan test` hijau **dan** database dev tidak berubah
2. `php artisan route:list` → **tetap 35 route bernama**
3. Audit `route('...')` → 0 missing di luar file yang sengaja dihapus
4. `php artisan view:cache` sukses
5. `php vendor/bin/pint <file>` hanya untuk file yang disentuh

---

## 7. Risiko Lingkungan

| Risiko | Keterangan |
|---|---|
| PHP 8.5.10 vs Laravel 10 | Di luar rentang uji resmi. Jangan downgrade/upgrade framework tanpa alasan kuat. |
| `pdo_sqlite` tidak terpasang | Test harus pakai MySQL test terpisah — **bukan** sqlite `:memory:`. |
| `DatabaseSeeder` tidak idempoten | `db:seed` kedua error (unique email/ISBN). Pakai `migrate:fresh --seed`. |
| Seeder tanpa transaksi/penalty | Halaman riwayat & denda kosong saat fresh — bukan bug, tapi perlu data uji. |
| `AGENTS.md` melarang `/tmp` | Simpan artefak test di dalam project. |
| Tanpa CI / pre-commit | Tidak ada pengaman otomatis — gate di atas wajib dijalankan manual. |
