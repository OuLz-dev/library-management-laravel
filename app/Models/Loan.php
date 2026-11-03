<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Loan extends Model
{
    use HasFactory;

    // PK dinamai <tabel>_id; data pengembalian ada di tabel returns (LoanReturn).
    protected $primaryKey = 'loans_id';

    protected $fillable = [
        'loan_code', 'member_id', 'book_id', 'borrow_date', 'due_date', 'status',
        'processed_by',
    ];

    protected $casts = [
        'borrow_date' => 'date',
        'due_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function book()
    {
        return $this->belongsTo(Book::class, 'book_id', 'books_id');
    }

    public function penalty()
    {
        return $this->hasOne(Penalty::class, 'loan_id', 'loans_id');
    }

    public function loanReturn()
    {
        return $this->hasOne(LoanReturn::class, 'loan_id', 'loans_id');
    }

    // Admin yang memproses (membuat) peminjaman ini. Boleh jadi admin BERBEDA
    // dengan yang memproses pengembalian (returns.processed_by) — dua jejak
    // terpisah, dua orang berbeda adalah kasus normal (beda shift/staf).
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Kode peminjaman unik (C1).
     *
     * Suffix 6 karakter acak memberi ~2.1 miliar kombinasi per hari, jauh
     * melebihi kolom varchar(50) dan aman dari race condition.
     */
    public static function generateCode(): string
    {
        return 'PJM-'.date('Ymd').'-'.strtoupper(Str::random(6));
    }

    //  CEK APAKAH PEMINJAMAN MEMILIKI DENDA (REAL-TIME)
    public function getHasFineAttribute()
    {
        if ($this->status === 'returned') {
            return $this->penalty()->exists();
        }

        $now = Carbon::now()->startOfDay();
        $dueDate = Carbon::parse($this->due_date)->startOfDay();

        return $now->gt($dueDate);
    }

    //  HITUNG DENDA REAL-TIME
    public function getCurrentFineAttribute()
    {
        if ($this->status === 'returned') {
            return $this->penalty ? $this->penalty->fine_amount : 0;
        }

        $now = Carbon::now()->startOfDay();
        $dueDate = Carbon::parse($this->due_date)->startOfDay();

        if ($now->gt($dueDate)) {
            $daysLate = $now->diffInDays($dueDate);

            return $daysLate * config('perpustakaan.fine_per_day');
        }

        return 0;
    }

    //  HITUNG HARI TERLAMBAT
    public function getLateDaysAttribute()
    {
        if ($this->status === 'returned') {
            return $this->penalty ? $this->penalty->days_late : 0;
        }

        $now = Carbon::now()->startOfDay();
        $dueDate = Carbon::parse($this->due_date)->startOfDay();

        if ($now->gt($dueDate)) {
            return $now->diffInDays($dueDate);
        }

        return 0;
    }

    //  STATUS SISA HARI
    public function getRemainingDaysAttribute()
    {
        if ($this->status === 'returned') {
            // Sentinel "jauh dari jatuh tempo" supaya badge peringatan
            // tidak muncul untuk buku yang sudah dikembalikan.
            return 999;
        }

        $now = Carbon::now()->startOfDay();
        $dueDate = Carbon::parse($this->due_date)->startOfDay();

        // Negatif = sudah lewat jatuh tempo, positif = masih sisa hari.
        return (int) $now->diffInDays($dueDate, false);
    }

    public function getStatusColorAttribute()
    {
        if ($this->status === 'returned') {
            return 'success';
        }

        $remaining = $this->remaining_days;

        if ($remaining < 0) {
            return 'danger';
        } elseif ($remaining <= 1) {
            return 'warning';
        } else {
            return 'info';
        }
    }

    public function getStatusTextAttribute()
    {
        if ($this->status === 'returned') {
            return 'Sudah Dikembalikan';
        }

        $remaining = $this->remaining_days;

        if ($remaining < 0) {
            $daysLate = abs($remaining);
            if ($daysLate == 1) {
                return 'Terlambat 1 hari';
            }

            return 'Terlambat '.$daysLate.' hari';
        } elseif ($remaining == 0) {
            return 'Jatuh Tempo Hari Ini! ⚠️';
        } elseif ($remaining == 1) {
            return 'Sisa 1 hari lagi ⏰';
        } else {
            return 'Sisa '.$remaining.' hari';
        }
    }

    public function getWarningMessageAttribute()
    {
        if ($this->status === 'returned') {
            return null;
        }

        $remaining = $this->remaining_days;

        if ($remaining < 0) {
            $daysLate = abs($remaining);
            $fine = $daysLate * config('perpustakaan.fine_per_day');

            return '⚠️ Terlambat '.$daysLate.' hari! Denda Rp '.number_format($fine, 0, ',', '.');
        } elseif ($remaining == 0) {
            return '⚠️ Segera kembalikan buku! Jatuh tempo hari ini.';
        } elseif ($remaining <= 1) {
            return '⏰ Jangan lupa kembalikan buku besok.';
        } elseif ($remaining <= 3) {
            return '⏰ Jangan lupa kembalikan buku dalam '.$remaining.' hari.';
        }

        return null;
    }
}
