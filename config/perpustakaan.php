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
    | Dipakai User::canBorrow().
    */
    'max_active_borrows' => 3,

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
