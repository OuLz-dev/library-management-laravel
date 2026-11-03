<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index untuk query dashboard & laporan (C8).
     *
     * Query yang dipercepat:
     *   - MemberDashboard: loans WHERE member_id AND status
     *   - AdminDashboard / loans.index: loans WHERE status
     *   - MemberDashboard / penalties.index: penalties WHERE member_id AND status
     *
     * books.category_id tidak ditambahkan — sudah terindeks otomatis sebagai
     * foreign key dari migration asli, menduplikasinya hanya memperlambat INSERT.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->index(['member_id', 'status'], 'loans_member_id_status_index');
            $table->index('status', 'loans_status_index');
        });

        Schema::table('penalties', function (Blueprint $table) {
            $table->index(['member_id', 'status'], 'penalties_member_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_member_id_status_index');
            $table->dropIndex('loans_status_index');
        });

        Schema::table('penalties', function (Blueprint $table) {
            $table->dropIndex('penalties_member_id_status_index');
        });
    }
};
