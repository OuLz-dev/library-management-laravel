<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin yang memproses (membuat) peminjaman — melengkapi jejak
     * returns.processed_by untuk admin yang memproses pengembalian.
     * Keduanya memang boleh beda orang (beda shift/staf — normal).
     */
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('processed_by')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by');
        });
    }
};
