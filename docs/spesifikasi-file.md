# Spesifikasi File

Sistem Informasi Perpustakaan

---

## a. Spesifikasi data user (admin & anggota) 01

| Atribut | Nilai |
| --- | --- |
| Nama file | users |
| Akronim | users.sql |
| Tipe File | Relational Database |
| Organisasi File | Indexed Sequential |
| Akses File | Random |
| Media | Solid State Drive |
| Panjang Record | 1180 |
| Kunci Field | id |
| Software | MySQL |

---

## b. Spesifikasi data kategori buku 05

| Atribut | Nilai |
| --- | --- |
| Nama file | categories |
| Akronim | categories.sql |
| Tipe File | Relational Database |
| Organisasi File | Indexed Sequential |
| Akses File | Random |
| Media | Solid State Drive |
| Panjang Record | 382 |
| Kunci Field | id |
| Software | MySQL |

---

## c. Spesifikasi data buku

| Atribut | Nilai |
| --- | --- |
| Nama file | books |
| Akronim | books.sql |
| Tipe File | Relational Database |
| Organisasi File | Indexed Sequential |
| Akses File | Random |
| Media | Solid State Drive |
| Panjang Record | 1366 |
| Kunci Field | id |
| Software | MySQL |

---

## d. Spesifikasi data anggota (member) 02

| Atribut | Nilai |
| --- | --- |
| Nama file | members |
| Akronim | members.sql |
| Tipe File | Relational Database |
| Organisasi File | Indexed Sequential |
| Akses File | Random |
| Media | Solid State Drive |
| Panjang Record | 92 |
| Kunci Field | id |
| Software | MySQL |

---

## e. Spesifikasi data transaksi peminjaman 03

| Atribut | Nilai |
| --- | --- |
| Nama file | transactions |
| Akronim | transactions.sql |
| Tipe File | Relational Database |
| Organisasi File | Indexed Sequential |
| Akses File | Random |
| Media | Solid State Drive |
| Panjang Record | 109 |
| Kunci Field | id |
| Software | MySQL |

---

## f. Spesifikasi data denda (penalti) 04

| Atribut | Nilai |
| --- | --- |
| Nama file | penalties |
| Akronim | penalties.sql |
| Tipe File | Relational Database |
| Organisasi File | Indexed Sequential |
| Akses File | Random |
| Media | Solid State Drive |
| Panjang Record | 69 |
| Kunci Field | id |
| Software | MySQL |

---

## Lampiran: rincian perhitungan Panjang Record

Konvensi penulisan panjang field: `varchar(n)` = n · `text` = 255 · `enum` = 1 · `date` = 3 · `timestamp` = 8 · `decimal(10,2)` = 5 · integer (primary/foreign key) = 11.

### a. users — 1180

| Field | Tipe | Panjang |
| --- | --- | --- |
| id | bigint (PK) | 11 |
| name | varchar(255) | 255 |
| email | varchar(255) | 255 |
| email_verified_at | timestamp | 8 |
| password | varchar(255) | 255 |
| role | enum | 1 |
| status | enum | 1 |
| phone | varchar(15) | 15 |
| address | text | 255 |
| last_login_at | timestamp | 8 |
| remember_token | varchar(100) | 100 |
| created_at | timestamp | 8 |
| updated_at | timestamp | 8 |
| **Total** | | **1180** |

### b. categories — 382

| Field | Tipe | Panjang |
| --- | --- | --- |
| id | bigint (PK) | 11 |
| name | varchar(100) | 100 |
| description | text | 255 |
| created_at | timestamp | 8 |
| updated_at | timestamp | 8 |
| **Total** | | **382** |

### c. books — 1366

| Field | Tipe | Panjang |
| --- | --- | --- |
| id | bigint (PK) | 11 |
| category_id | bigint (FK) | 11 |
| title | varchar(255) | 255 |
| author | varchar(255) | 255 |
| publisher | varchar(255) | 255 |
| year | integer | 11 |
| isbn | varchar(20) | 20 |
| cover | varchar(255) | 255 |
| stock | integer | 11 |
| available_stock | integer | 11 |
| description | text | 255 |
| created_at | timestamp | 8 |
| updated_at | timestamp | 8 |
| **Total** | | **1366** |

### d. members — 92

| Field | Tipe | Panjang |
| --- | --- | --- |
| id | bigint (PK) | 11 |
| user_id | bigint (FK) | 11 |
| member_code | varchar(50) | 50 |
| join_date | date | 3 |
| status | enum | 1 |
| created_at | timestamp | 8 |
| updated_at | timestamp | 8 |
| **Total** | | **92** |

### e. transactions — 109

| Field | Tipe | Panjang |
| --- | --- | --- |
| id | bigint (PK) | 11 |
| transaction_code | varchar(50) | 50 |
| member_id | bigint (FK) | 11 |
| book_id | bigint (FK) | 11 |
| borrow_date | date | 3 |
| due_date | date | 3 |
| return_date | date | 3 |
| status | enum | 1 |
| created_at | timestamp | 8 |
| updated_at | timestamp | 8 |
| **Total** | | **109** |

### f. penalties — 69

| Field | Tipe | Panjang |
| --- | --- | --- |
| id | bigint (PK) | 11 |
| transaction_id | bigint (FK) | 11 |
| member_id | bigint (FK) | 11 |
| days_late | integer | 11 |
| fine_amount | decimal(10,2) | 5 |
| status | enum | 1 |
| paid_date | date | 3 |
| created_at | timestamp | 8 |
| updated_at | timestamp | 8 |
| **Total** | | **69** |
