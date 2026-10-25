<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $primaryKey = 'books_id';

    protected $fillable = [
        'category_id',
        'title',
        'author',
        'publisher',
        'year',
        'isbn',
        'cover',
        'stock',
        'available_stock',
        'description',
    ];

    // Accessor untuk mendapatkan URL cover
    public function getCoverUrlAttribute()
    {
        if ($this->cover) {
            return asset('storage/'.$this->cover);
        }

        return null;
    }

    // Helper untuk cek apakah ada cover
    public function hasCover()
    {
        return $this->cover && file_exists(storage_path('app/public/'.$this->cover));
    }

    public function category()
    {
        // FK/ownerKey eksplisit karena PK tabel bisnis bukan 'id' lagi —
        // guess otomatis Laravel melenceng (category_categories_id).
        return $this->belongsTo(Category::class, 'category_id', 'categories_id');
    }

    public function loans()
    {
        // FK/localKey eksplisit — guess otomatis menghasilkan loans.book_books_id.
        return $this->hasMany(Loan::class, 'book_id', 'books_id');
    }

    public function isAvailable()
    {
        return $this->available_stock > 0;
    }

    // C3: decrement/increment atomik di level database (UPDATE ... = x ± 1),
    // bukan read-modify-write yang bisa kalah dalam race condition.
    public function decreaseStock($quantity = 1)
    {
        $this->decrement('available_stock', $quantity);
    }

    public function increaseStock($quantity = 1)
    {
        $this->increment('available_stock', $quantity);
    }
}
