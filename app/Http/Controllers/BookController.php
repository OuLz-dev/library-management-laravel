<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookController extends Controller
{
    // ========== ADMIN: LIHAT SEMUA BUKU (DENGAN SEARCH & FILTER + PAGINATION 20) ==========
    public function index(Request $request)
    {
        // Ambil semua kategori untuk dropdown filter
        $categories = Category::all();

        // Query buku dengan relasi kategori
        $query = Book::with('category');

        //  FILTER BERDASARKAN KATEGORI
        // Session last_category dipakai tombol "Kembali" di halaman
        // create/edit/show, jadi harus ikut di-reset saat filter dikosongkan.
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
            session(['last_category' => $request->category]);
        } else {
            session(['last_category' => '']);
        }

        //  SEARCH BERDASARKAN JUDUL BUKU
        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        //  PAGINATION 20 BUKU PER HALAMAN
        $books = $query->latest()->paginate(20);

        // Menambahkan query string ke pagination
        $books->appends(request()->query());

        return view('admin.books.index', compact('books', 'categories'));
    }

    // ========== MEMBER: LIHAT KATALOG BUKU (DENGAN SEARCH & FILTER + PAGINATION 20) ==========
    public function memberIndex(Request $request)
    {
        $categories = Category::all();
        $query = Book::with('category');

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
            session(['member_last_category' => $request->category]);
        } else {
            session(['member_last_category' => '']);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('author', 'like', '%'.$search.'%');
            });
        }

        //  FILTER BERDASARKAN KETERSEDIAAN
        if ($request->filled('availability') && in_array($request->availability, ['available', 'out'])) {
            if ($request->availability === 'available') {
                $query->where('available_stock', '>', 0);
            } else {
                $query->where('available_stock', '<=', 0);
            }
        }

        //  PAGINATION 20 BUKU PER HALAMAN
        $books = $query->latest()->paginate(20);

        // Menambahkan query string ke pagination
        $books->appends(request()->query());

        return view('member.books.index', compact('books', 'categories'));
    }

    // ========== TAMPILKAN FORM TAMBAH ==========
    public function create()
    {
        $categories = Category::all();

        return view('admin.books.create', compact('categories'));
    }

    // ========== SIMPAN BUKU BARU ==========
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'isbn' => 'required|string|unique:books',
            'stock' => 'required|integer|min:1',
            'category_id' => 'nullable|exists:categories,categories_id',
            'cover' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->all();
        $data['available_stock'] = $request->stock;

        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $filename = time().'-'.Str::slug($request->title).'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('covers', $filename, 'public');
            $data['cover'] = $path;
        }

        Book::create($data);

        return redirect()->route('admin.books.index')->with('success', 'Buku berhasil ditambahkan');
    }

    // ========== DETAIL BUKU ==========
    public function show(Book $book)
    {
        if (auth()->user()->isMember()) {
            return view('member.books.show', compact('book'));
        }

        return view('admin.books.show', compact('book'));
    }

    // ========== FORM EDIT ==========
    public function edit(Book $book)
    {
        $categories = Category::all();

        return view('admin.books.edit', compact('book', 'categories'));
    }

    // ========== UPDATE BUKU ==========
    public function update(Request $request, Book $book)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            // Kolom ignore unique disebut eksplisit karena PK books bukan 'id'.
            'isbn' => 'required|string|unique:books,isbn,'.$book->getKey().',books_id',
            'stock' => 'required|integer|min:1',
            'cover' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->all();

        // C4: hitung ulang dari jumlah pinjaman aktif, bukan rumus ad-hoc
        // "available + (stock - oldStock)" yang bisa menghasilkan stok minus
        // saat admin menurunkan stock saat buku sedang dipinjam.
        $activeBorrows = $book->loans()->where('status', 'borrowed')->count();
        $data['available_stock'] = max(0, (int) $request->stock - $activeBorrows);

        if ($request->hasFile('cover')) {
            if ($book->cover && Storage::disk('public')->exists($book->cover)) {
                Storage::disk('public')->delete($book->cover);
            }

            $file = $request->file('cover');
            $filename = time().'-'.Str::slug($request->title).'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('covers', $filename, 'public');
            $data['cover'] = $path;
        }

        $book->update($data);

        return redirect()->route('admin.books.index')->with('success', 'Buku berhasil diupdate');
    }

    // ========== HAPUS BUKU ==========
    public function destroy(Book $book)
    {
        // C9 (temuan #13): FK loans.book_id memakai ON DELETE CASCADE,
        // jadi menghapus buku ikut menghapus transaksi & penalty tanpa suara.
        // Keputusan yang dikunci: tolak penghapusan selama ada riwayat
        // peminjaman apa pun — menjamin tidak ada data transaksi yang hilang
        // tanpa perlu ganti FK atau menambah soft-delete.
        if ($book->loans()->where('status', 'borrowed')->exists()) {
            return redirect()->route('admin.books.index')
                ->with('error', 'Buku masih dipinjam dan tidak bisa dihapus');
        }

        if ($book->loans()->exists()) {
            return redirect()->route('admin.books.index')
                ->with('error', 'Buku memiliki riwayat peminjaman dan tidak bisa dihapus');
        }

        if ($book->cover && Storage::disk('public')->exists($book->cover)) {
            Storage::disk('public')->delete($book->cover);
        }

        $book->delete();

        return redirect()->route('admin.books.index')->with('success', 'Buku berhasil dihapus');
    }
}
