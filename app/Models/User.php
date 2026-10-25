<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'status', 'phone', 'address',
        'member_code', 'join_date', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'last_login_at' => 'datetime', 'join_date' => 'date'];

    /**
     * Generator tunggal kode member (C2).
     *
     * Kode dibuat hanya di tempat ini — registrasi, pembuatan member lewat
     * admin, maupun seeder bisa mengisi kode secara eksplisit atau membiarkan
     * hook ini mengisinya. Format MBR-<5 digit> berbasis max(id)+1, naikkan
     * sampai benar-benar bebas agar tidak tabrakan setelah penghapusan.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->role !== 'member' || ! empty($user->member_code)) {
                return;
            }

            $nextId = (int) static::max('id') + 1;

            do {
                $code = 'MBR-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
                $nextId++;
            } while (static::where('member_code', $code)->exists());

            $user->member_code = $code;
        });
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isMember()
    {
        return $this->role === 'member';
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'member_id');
    }

    public function penalties()
    {
        return $this->hasMany(Penalty::class, 'member_id');
    }

    public function getActiveBorrowsCount()
    {
        return $this->loans()->where('status', 'borrowed')->count();
    }

    public function canBorrow()
    {
        return $this->status === 'active'
            && $this->getActiveBorrowsCount() < config('perpustakaan.max_active_borrows');
    }

    public function hasUnpaidPenalties()
    {
        return $this->penalties()->where('status', 'unpaid')->exists();
    }
}
