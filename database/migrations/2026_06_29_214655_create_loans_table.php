<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('loans', function (Blueprint $table) {
            // PK dinamai <tabel>_id; return_date pindah ke tabel returns.
            $table->id('loans_id');
            $table->string('loan_code', 50)->unique();

            // Merge dari tabel members lama: member langsung merujuk users
            // (users adalah tabel framework, PK-nya tetap 'id').
            $table->foreignId('member_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('book_id')->constrained('books', 'books_id')->onDelete('cascade');
            $table->date('borrow_date');
            $table->date('due_date');
            // 'overdue' tidak pernah ditulis kode mana pun (dihitung on-the-fly),
            // jadi enum langsung dibersihkan saat pembuatan tabel.
            $table->enum('status', ['borrowed', 'returned'])->default('borrowed');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('loans');
    }
};
