<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transaksi multi-buku per kode peminjaman (keputusan user, tanpa tabel baru).
     *
     * Satu transaksi kini bisa memuat 1-3 buku: setiap buku tetap menjadi SATU
     * baris loans (stok, status, pengembalian, dan denda tetap per buku), dan
     * baris-baris transaksi yang sama berbagi loan_code. Konsekuensinya
     * constraint UNIQUE di loan_code harus dilepas — keunikan kode tetap
     * dijaga Loan::generateCode() di aplikasi: satu panggilan generator per
     * transaksi, dipakai ulang untuk semua baris transaksi itu.
     *
     * Pengembalian parsial bekerja di atas struktur ini: admin mengembalikan
     * sebagian baris milik satu kode; transaksi dianggap selesai ketika tidak
     * ada lagi baris ber-status 'borrowed' untuk kode itu.
     */
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropUnique('loans_loan_code_unique');

            // Index biasa tetap dipertahankan untuk pencarian kode dan
            // pengelompokan baris per transaksi.
            $table->index('loan_code', 'loans_loan_code_index');
        });
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_loan_code_index');

            // UNIQUE hanya bisa dipulihkan bila belum ada transaksi multi-buku
            // (belum ada kode yang dipakai lebih dari satu baris).
            $table->unique('loan_code', 'loans_loan_code_unique');
        });
    }
};
