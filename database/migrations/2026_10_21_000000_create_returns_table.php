<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pengembalian terpisah dari loans (permintaan user).
     *
     * - loan_id UNIQUE menjamin satu peminjaman hanya bisa dikembalikan sekali.
     * - processed_by mencatat admin yang memproses (FK users.id).
     * - Status 'borrowed'/'returned' tetap disimpan di loans (keputusan desain)
     *   supaya filter daftar & hitungan dashboard tetap satu query.
     */
    public function up()
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id('returns_id');
            $table->foreignId('loan_id')->unique()->constrained('loans', 'loans_id')->onDelete('cascade');
            $table->date('return_date');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('returns');
    }
};
