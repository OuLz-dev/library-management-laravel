<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Denda per hari keterlambatan (Rupiah)
    |--------------------------------------------------------------------------
    | Sebelumnya literal 2000 tersebar di TransactionController@returnBook
    | dan Transaction::getCurrentFineAttribute/getWarningMessageAttribute.
    */
    'fine_per_day' => 2000,

    /*
    |--------------------------------------------------------------------------
    | Jumlah pinjaman aktif maksimal per member
    |--------------------------------------------------------------------------
    | Dipakai User::canBorrow(). Menghitung per BUKU (baris loans), bukan per
    | transaksi — satu transaksi 3 buku memenuhi seluruh kuota ini.
    */
    'max_active_borrows' => 3,

    /*
    |--------------------------------------------------------------------------
    | Jumlah buku maksimal per transaksi peminjaman
    |--------------------------------------------------------------------------
    | Form peminjaman punya 3 slot buku: slot 1 wajib, slot 2-3 opsional.
    | Dipakai validasi LoanController@store (max book_ids).
    */
    'max_books_per_transaction' => 3,

    /*
    |--------------------------------------------------------------------------
    | Lama pinjaman default (hari)
    |--------------------------------------------------------------------------
    | Dipakai saat admin membuka form peminjaman baru.
    */
    'loan_days_default' => 7,

    /*
    |--------------------------------------------------------------------------
    | Batas maksimal durasi pinjaman (hari)
    |--------------------------------------------------------------------------
    | Dipakai validasi due_date di LoanController@store.
    */
    'max_loan_days' => 30,

];
