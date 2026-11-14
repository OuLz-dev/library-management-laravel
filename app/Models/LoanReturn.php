<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanReturn extends Model
{
    use HasFactory;

    // Nama class tidak boleh 'Return' (reserved word PHP).
    protected $table = 'returns';

    // PK dinamai <tabel>_id — konfirmasi user, hanya tabel bisnis.
    protected $primaryKey = 'returns_id';

    protected $fillable = ['loan_id', 'return_date', 'processed_by'];

    protected $casts = ['return_date' => 'date'];

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    // Admin yang memproses pengembalian (keputusan desain user).
    public function admin()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
