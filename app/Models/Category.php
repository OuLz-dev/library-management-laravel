<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    // PK dinamai <tabel>_id (bukan 'id') — konfirmasi user, hanya tabel bisnis.
    protected $primaryKey = 'categories_id';

    public function books()
    {
        // FK/localKey eksplisit — guess otomatis menghasilkan books.category_categories_id.
        return $this->hasMany(Book::class, 'category_id', 'categories_id');
    }
}