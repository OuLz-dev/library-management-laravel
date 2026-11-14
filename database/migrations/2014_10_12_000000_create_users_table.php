<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('phone', 15)->nullable();
            $table->text('address')->nullable();

            // Data keanggotaan perpustakaan (merge dari tabel members lama).
            // Null untuk admin; MySQL mengizinkan banyak NULL pada index unik,
            // jadi beberapa admin tanpa kode tidak saling bertabrakan.
            $table->string('member_code', 50)->nullable()->unique();
            $table->date('join_date')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};