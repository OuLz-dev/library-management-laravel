<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ---------- INDEX + PAGINATION ----------

    public function test_admin_can_list_books_with_pagination_of_20(): void
    {
        Book::factory()->count(25)->create();

        $response = $this->actingAs($this->admin)->get('/admin/books');

        $response->assertOk()->assertViewHas('books', function ($books) {
            return $books->count() === 20 && $books->total() === 25;
        });
    }

    public function test_search_filters_books_by_title(): void
    {
        Book::factory()->create(['title' => 'Laskar Pelangi Unik']);
        Book::factory()->create(['title' => 'Buku Lain Sama Sekali']);

        $response = $this->actingAs($this->admin)
            ->get('/admin/books?search='.urlencode('Laskar Pelangi'));

        $response->assertOk()->assertViewHas('books', function ($books) {
            return $books->count() === 1
                && $books->first()->title === 'Laskar Pelangi Unik';
        });
    }

    public function test_category_filter_returns_only_matching_books(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        Book::factory()->count(2)->create(['category_id' => $categoryA->getKey()]);
        Book::factory()->create(['category_id' => $categoryB->getKey()]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/books?category='.$categoryA->getKey());

        $response->assertOk()->assertViewHas('books', function ($books) use ($categoryA) {
            return $books->count() === 2
                && $books->every(fn ($b) => $b->category_id === $categoryA->getKey());
        });
    }

    public function test_member_catalog_shows_books_with_pagination_of_20(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        Book::factory()->count(23)->create();

        $response = $this->actingAs($member)->get('/member/books');

        $response->assertOk()->assertViewHas('books', function ($books) {
            return $books->count() === 20 && $books->total() === 23;
        });
    }

    // ---------- CREATE ----------

    public function test_admin_can_create_a_book(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin)->post('/admin/books', [
            'title' => 'Buku Baru',
            'isbn' => '978-602-1234-999',
            'stock' => 5,
            'category_id' => $category->getKey(),
            'author' => 'Penulis',
            'publisher' => 'Penerbit',
            'year' => 2024,
            'description' => 'Deskripsi',
        ]);

        $response->assertRedirect('/admin/books');
        $this->assertDatabaseHas('books', [
            'title' => 'Buku Baru',
            'isbn' => '978-602-1234-999',
            'stock' => 5,
            // available_stock diisi dari stock saat create
            'available_stock' => 5,
        ]);
    }

    public function test_duplicate_isbn_is_rejected(): void
    {
        $existing = Book::factory()->create();

        $response = $this->actingAs($this->admin)->post('/admin/books', [
            'title' => 'Duplikat',
            'isbn' => $existing->isbn,
            'stock' => 1,
        ]);

        $response->assertSessionHasErrors('isbn');
    }

    public function test_stock_must_be_at_least_one(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/books', [
            'title' => 'Tanpa Stok',
            'isbn' => '978-602-1234-998',
            'stock' => 0,
        ]);

        $response->assertSessionHasErrors('stock');
    }

    public function test_non_image_cover_is_rejected(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->post('/admin/books', [
            'title' => 'Buku Dengan Sampul',
            'isbn' => '978-602-1234-997',
            'stock' => 1,
            'cover' => UploadedFile::fake()->create('dokumen.txt', 10, 'text/plain'),
        ]);

        $response->assertSessionHasErrors('cover');
        $this->assertDatabaseMissing('books', ['isbn' => '978-602-1234-997']);
    }

    public function test_image_cover_is_stored_on_public_disk(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->post('/admin/books', [
            'title' => 'Buku Dengan Sampul',
            'isbn' => '978-602-1234-996',
            'stock' => 1,
            'cover' => UploadedFile::fake()->create('cover.jpg', 20, 'image/jpeg'),
        ]);

        $response->assertSessionHasNoErrors();

        $book = Book::where('isbn', '978-602-1234-996')->firstOrFail();
        $this->assertNotNull($book->cover);
        Storage::disk('public')->assertExists($book->cover);
    }

    // ---------- UPDATE ----------

    public function test_admin_can_update_a_book(): void
    {
        $book = Book::factory()->create(['stock' => 5, 'available_stock' => 3]);

        $response = $this->actingAs($this->admin)
            ->put('/admin/books/'.$book->getKey(), [
                'title' => 'Judul Diubah',
                'isbn' => $book->isbn,
                'stock' => 8,
            ]);

        $response->assertRedirect('/admin/books');

        $book->refresh();
        $this->assertSame('Judul Diubah', $book->title);
        $this->assertSame(8, $book->stock);
        // C4: available_stock = stock - pinjaman aktif. Karena tidak ada
        // transaksi aktif di test ini, seluruh stok tersedia (8 - 0 = 8).
        $this->assertSame(8, $book->available_stock);
    }

    public function test_lowering_stock_while_borrowed_never_makes_available_stock_negative(): void
    {
        // C4: rumus lama "available + (stock - oldStock)" menghasilkan
        // 0 + (1 - 5) = -4 saat 5 eksemplar sedang dipinjam.
        // Rumus baru: max(0, stock - pinjaman_aktif) = max(0, 1 - 5) = 0.
        $book = Book::factory()->create(['stock' => 5, 'available_stock' => 0]);
        \App\Models\Loan::factory()->count(5)->create([
            'book_id' => $book->getKey(),
            'status' => 'borrowed',
        ]);

        $response = $this->actingAs($this->admin)->put('/admin/books/'.$book->getKey(), [
            'title' => $book->title,
            'isbn' => $book->isbn,
            'stock' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $book->refresh();
        $this->assertSame(1, $book->stock);
        $this->assertSame(0, $book->available_stock);
        $this->assertGreaterThanOrEqual(0, $book->available_stock);
    }

    public function test_available_stock_accounts_for_active_borrows_on_update(): void
    {
        $book = Book::factory()->create(['stock' => 5, 'available_stock' => 3]);
        $member = \App\Models\User::factory()->create();
        \App\Models\Loan::factory()->count(2)->for($member)->for($book)->create([
            'status' => 'borrowed',
        ]);

        $this->actingAs($this->admin)->put('/admin/books/'.$book->getKey(), [
            'title' => $book->title,
            'isbn' => $book->isbn,
            'stock' => 5,
        ]);

        // 5 stok - 2 sedang dipinjam = 3 tersedia.
        $this->assertSame(3, $book->refresh()->available_stock);
    }

    // ---------- READ ----------

    public function test_admin_and_member_see_different_book_detail_views(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($this->admin)
            ->get('/admin/books/'.$book->getKey())
            ->assertOk()
            ->assertViewIs('admin.books.show');

        $member = User::factory()->create(['role' => 'member']);
        $this->actingAs($member)
            ->get('/member/books/'.$book->getKey())
            ->assertOk()
            ->assertViewIs('member.books.show');
    }

    // ---------- DELETE ----------

    public function test_admin_can_delete_a_book_without_borrow_history(): void
    {
        $book = Book::factory()->create();

        $response = $this->actingAs($this->admin)->delete('/admin/books/'.$book->getKey());

        $response->assertRedirect('/admin/books');
        $this->assertDatabaseMissing('books', ['books_id' => $book->getKey()]);
    }

    public function test_book_with_active_borrow_cannot_be_deleted(): void
    {
        // C9 (temuan #13): FK loans.book_id ON DELETE CASCADE —
        // tanpa guard ini, menghapus buku ikut menghapus peminjaman & penalty.
        $book = Book::factory()->create();
        $loan = \App\Models\Loan::factory()->create([
            'book_id' => $book->getKey(),
            'status' => 'borrowed',
        ]);

        $response = $this->actingAs($this->admin)->delete('/admin/books/'.$book->getKey());

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('books', ['books_id' => $book->getKey()]);
        $this->assertDatabaseHas('loans', ['loans_id' => $loan->getKey()]);
    }

    public function test_book_with_returned_history_cannot_be_deleted(): void
    {
        // Buku dengan riwayat (sudah kembali) pun ditolak — kalau diizinkan,
        // kaskade FK akan menghapus baris transaksi beserta penaltinya.
        $book = Book::factory()->create();
        $loan = \App\Models\Loan::factory()->returned()->create([
            'book_id' => $book->getKey(),
        ]);
        $penalty = \App\Models\Penalty::factory()->create([
            'loan_id' => $loan->getKey(),
            'member_id' => $loan->member_id,
        ]);

        $response = $this->actingAs($this->admin)->delete('/admin/books/'.$book->getKey());

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('books', ['books_id' => $book->getKey()]);
        $this->assertDatabaseHas('loans', ['loans_id' => $loan->getKey()]);
        $this->assertDatabaseHas('penalties', ['penalties_id' => $penalty->getKey()]);
    }
}
