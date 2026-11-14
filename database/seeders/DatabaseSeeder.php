<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // ========== BUAT 5 ADMIN (keputusan user) ==========
        $admins = [
            ['name' => 'Administrator', 'email' => 'admin@gmail.com', 'phone' => '081234567890', 'address' => 'Jl. Admin No. 1'],
            ['name' => 'Admin Dua', 'email' => 'admin2@gmail.com', 'phone' => '081234567893', 'address' => 'Jl. Admin No. 2'],
            ['name' => 'Admin Tiga', 'email' => 'admin3@gmail.com', 'phone' => '081234567894', 'address' => 'Jl. Admin No. 3'],
            ['name' => 'Admin Empat', 'email' => 'admin4@gmail.com', 'phone' => '081234567895', 'address' => 'Jl. Admin No. 4'],
            ['name' => 'Admin Lima', 'email' => 'admin5@gmail.com', 'phone' => '081234567896', 'address' => 'Jl. Admin No. 5'],
        ];

        foreach ($admins as $adminData) {
            User::create($adminData + [
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'status' => 'active',
                'last_login_at' => now(),
            ]);
        }

        // ==========  BUAT MEMBER DEFAULT UNTUK LOGIN ==========
        // Member 1: member@gmail.com
        $memberDefault1 = User::create([
            'name' => 'Member Default',
            'email' => 'member@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'member',
            'status' => 'active',
            'phone' => '081234567891',
            'address' => 'Jl. Member Default No. 1',
            'member_code' => 'MBR-00001',
            'join_date' => now()->subDays(30),
            'last_login_at' => now(),
        ]);

        // Member 2: member2@gmail.com
        $memberDefault2 = User::create([
            'name' => 'Member Dua',
            'email' => 'member2@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'member',
            'status' => 'active',
            'phone' => '081234567892',
            'address' => 'Jl. Member Dua No. 2',
            'member_code' => 'MBR-00002',
            'join_date' => now()->subDays(20),
            'last_login_at' => now(),
        ]);

        // ========== BUAT 20 MEMBER LAINNYA ==========
        $memberNames = [
            'Andi Pratama', 'Budi Santoso', 'Citra Dewi', 'Dian Sastro', 'Eko Prabowo',
            'Fitriani', 'Gunawan', 'Hendra', 'Indah', 'Joko Widodo',
            'Kartika', 'Lestari', 'Mulyono', 'Nadia', 'Oscar',
            'Putri', 'Rahmat', 'Siti', 'Tono', 'Ujang',
        ];

        $memberProfiles = [];
        for ($i = 0; $i < 20; $i++) {
            $name = $memberNames[$i];
            $email = strtolower(str_replace(' ', '', $name)).'@gmail.com';

            // Cek apakah email sudah ada (hindari duplikat dengan member default)
            if (User::where('email', $email)->exists()) {
                continue;
            }

            $member = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password123'),
                'role' => 'member',
                'status' => 'active',
                'phone' => '08'.rand(1000000000, 9999999999),
                'address' => 'Jl. '.$name.' No. '.rand(1, 100),
                'member_code' => 'MBR-'.str_pad($i + 3, 5, '0', STR_PAD_LEFT), // Mulai dari 00003
                'join_date' => now()->subDays(rand(1, 365)),
                'last_login_at' => now()->subDays(rand(0, 30)),
            ]);

            $memberProfiles[] = $member;
        }

        // ========== BUAT 15 KATEGORI BUKU ==========
        $categoryData = [
            ['name' => 'Fiksi', 'description' => 'Buku cerita fiksi dan novel dari berbagai genre'],
            ['name' => 'Non Fiksi', 'description' => 'Buku pengetahuan dan informasi umum'],
            ['name' => 'Teknologi', 'description' => 'Buku tentang teknologi, programming, dan IT'],
            ['name' => 'Sains', 'description' => 'Buku ilmu pengetahuan alam dan eksakta'],
            ['name' => 'Sejarah', 'description' => 'Buku tentang sejarah dan peradaban dunia'],
            ['name' => 'Bisnis', 'description' => 'Buku tentang bisnis, manajemen, dan kewirausahaan'],
            ['name' => 'Psikologi', 'description' => 'Buku tentang psikologi dan pengembangan diri'],
            ['name' => 'Pendidikan', 'description' => 'Buku tentang pendidikan dan metode pembelajaran'],
            ['name' => 'Kesehatan', 'description' => 'Buku tentang kesehatan dan gaya hidup sehat'],
            ['name' => 'Agama', 'description' => 'Buku tentang agama dan spiritualitas'],
            ['name' => 'Filsafat', 'description' => 'Buku tentang filsafat dan pemikiran manusia'],
            ['name' => 'Seni', 'description' => 'Buku tentang seni, musik, dan budaya'],
            ['name' => 'Olahraga', 'description' => 'Buku tentang olahraga dan kebugaran'],
            ['name' => 'Politik', 'description' => 'Buku tentang politik dan pemerintahan'],
            ['name' => 'Ekonomi', 'description' => 'Buku tentang ekonomi dan keuangan'],
        ];

        $categoryIds = [];
        foreach ($categoryData as $cat) {
            $category = Category::create($cat);
            // PK Category = categories_id (bukan 'id'), jadi pakai getKey()
            // agar tidak menyimpan NULL ke books.category_id.
            $categoryIds[] = $category->getKey();
        }

        // ========== FUNGSI UNTUK CEK GAMBAR ==========
        function getCoverPath($filename)
        {
            //  PASTIKAN PATH BENAR
            $path = 'covers/'.$filename;

            // Cek apakah file ada di storage/app/public/covers/
            if (Storage::disk('public')->exists($path)) {
                return $path;
            }

            // Jika tidak ada, return null (akan muncul placeholder)
            return null;
        }

        // ========== BUAT 151 BUKU (10 Buku per Kategori) ==========
        $books = [];

        // ===== 1. Fiksi (ID: 1) - 10 Buku =====
        $fiksiBooks = [
            ['title' => 'Dunia Sophie',
                'author' => 'Jostein Gaarder',
                'publisher' => 'Mizan',
                'year' => 2019,
                'isbn' => '978-602-1234-001',
                'stock' => 5,
                'cover' => 'dunia_sophie.jpg'],

            ['title' => 'Laskar Pelangi', 'author' => 'Andrea Hirata', 'publisher' => 'Bentang Pustaka', 'year' => 2005, 'isbn' => '978-602-1234-002', 'stock' => 4, 'cover' => 'laskar_pelangi.jpg'],
            ['title' => 'Bumi Manusia', 'author' => 'Pramoedya Ananta Toer', 'publisher' => 'Hasta Mitra', 'year' => 1980, 'isbn' => '978-602-1234-003', 'stock' => 3, 'cover' => 'bumi_manusia.jpg'],
            ['title' => 'Perahu Kertas', 'author' => 'Dee Lestari', 'publisher' => 'Bentang Pustaka', 'year' => 2009, 'isbn' => '978-602-1234-004', 'stock' => 4, 'cover' => 'perahu_kertas.jpg'],
            ['title' => 'Sang Pemimpi', 'author' => 'Andrea Hirata', 'publisher' => 'Bentang Pustaka', 'year' => 2006, 'isbn' => '978-602-1234-005', 'stock' => 3, 'cover' => 'sang_pemimpi.jpg'],
            ['title' => 'Negeri 5 Menara', 'author' => 'Ahmad Fuadi', 'publisher' => 'Gramedia', 'year' => 2009, 'isbn' => '978-602-1234-101', 'stock' => 4, 'cover' => 'negeri_5_menara.jpg'],
            ['title' => 'Rindu', 'author' => 'Tere Liye', 'publisher' => 'Republika', 'year' => 2014, 'isbn' => '978-602-1234-102', 'stock' => 3, 'cover' => 'rindu.jpg'],
            ['title' => 'Hujan', 'author' => 'Tere Liye', 'publisher' => 'Republika', 'year' => 2016, 'isbn' => '978-602-1234-103', 'stock' => 5, 'cover' => 'hujan.jpg'],
            ['title' => 'Bintang', 'author' => 'Tere Liye', 'publisher' => 'Republika', 'year' => 2017, 'isbn' => '978-602-1234-104', 'stock' => 3, 'cover' => 'bintang.jpg'],
            ['title' => 'Dilan: Dia adalah Dilanku', 'author' => 'Pidi Baiq', 'publisher' => 'Pastel Books', 'year' => 2014, 'isbn' => '978-602-1234-105', 'stock' => 6, 'cover' => 'dilan_dia_adalah_dilanku.jpg'],
        ];

        foreach ($fiksiBooks as $book) {
            $book['category_id'] = $categoryIds[0];
            $book['description'] = 'Buku fiksi menarik tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 2. Non Fiksi (ID: 2) - 10 Buku =====
        $nonFiksiBooks = [
            ['title' => 'Atomic Habits', 'author' => 'James Clear', 'publisher' => 'Gramedia', 'year' => 2020, 'isbn' => '978-602-1234-006', 'stock' => 6, 'cover' => 'atomic_habits.jpg'],
            ['title' => 'Sapiens', 'author' => 'Yuval Noah Harari', 'publisher' => 'Harper', 'year' => 2014, 'isbn' => '978-602-1234-007', 'stock' => 4, 'cover' => 'sapiens.jpg'],
            ['title' => 'The Power of Habit', 'author' => 'Charles Duhigg', 'publisher' => 'Random House', 'year' => 2012, 'isbn' => '978-602-1234-008', 'stock' => 3, 'cover' => 'power_of_habit.jpg'],
            ['title' => 'The Art of War', 'author' => 'Sun Tzu', 'publisher' => 'Gramedia', 'year' => 2005, 'isbn' => '978-602-1234-009', 'stock' => 5, 'cover' => 'art_of_war.jpg'],
            ['title' => 'Rich Dad Poor Dad', 'author' => 'Robert T. Kiyosaki', 'publisher' => 'Bhuana Ilmu Populer', 'year' => 2017, 'isbn' => '978-602-1234-010', 'stock' => 4, 'cover' => 'rich_dad_poor_dad.jpg'],
            ['title' => 'Outliers', 'author' => 'Malcolm Gladwell', 'publisher' => 'Little, Brown', 'year' => 2008, 'isbn' => '978-602-1234-201', 'stock' => 3, 'cover' => 'outliers.jpg'],
            ['title' => 'The Tipping Point', 'author' => 'Malcolm Gladwell', 'publisher' => 'Little, Brown', 'year' => 2000, 'isbn' => '978-602-1234-202', 'stock' => 4, 'cover' => 'tipping_point.jpg'],
            ['title' => 'Blink', 'author' => 'Malcolm Gladwell', 'publisher' => 'Little, Brown', 'year' => 2005, 'isbn' => '978-602-1234-203', 'stock' => 3, 'cover' => 'blink.jpg'],
            ['title' => 'Freakonomics', 'author' => 'Steven D. Levitt', 'publisher' => 'William Morrow', 'year' => 2005, 'isbn' => '978-602-1234-204', 'stock' => 4, 'cover' => 'freakonomics.jpg'],
            ['title' => 'SuperFreakonomics', 'author' => 'Steven D. Levitt', 'publisher' => 'William Morrow', 'year' => 2009, 'isbn' => '978-602-1234-205', 'stock' => 3, 'cover' => 'superfreakonomics.jpg'],
        ];

        foreach ($nonFiksiBooks as $book) {
            $book['category_id'] = $categoryIds[1];
            $book['description'] = 'Buku non-fiksi tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 3. Teknologi (ID: 3) - 10 Buku =====
        $teknologiBooks = [
            ['title' => 'Laravel Uncover', 'author' => 'Andre Pratama', 'publisher' => 'Teknologi Press', 'year' => 2023, 'isbn' => '978-602-1234-011', 'stock' => 5, 'cover' => 'laravel_uncover.jpg'],
            ['title' => 'Python Crash Course', 'author' => 'Eric Matthes', 'publisher' => 'No Starch Press', 'year' => 2023, 'isbn' => '978-602-1234-012', 'stock' => 6, 'cover' => 'python_crash_course.jpg'],
            ['title' => 'Clean Code', 'author' => 'Robert C. Martin', 'publisher' => 'Prentice Hall', 'year' => 2008, 'isbn' => '978-602-1234-013', 'stock' => 3, 'cover' => 'clean_code.jpg'],
            ['title' => 'JavaScript: The Good Parts', 'author' => 'Douglas Crockford', 'publisher' => 'O\'Reilly Media', 'year' => 2008, 'isbn' => '978-602-1234-014', 'stock' => 4, 'cover' => 'javascript_good_parts.jpg'],
            ['title' => 'The Pragmatic Programmer', 'author' => 'David Thomas', 'publisher' => 'Addison-Wesley', 'year' => 1999, 'isbn' => '978-602-1234-301', 'stock' => 3, 'cover' => 'pragmatic_programmer.jpg'],
            ['title' => 'Code Complete', 'author' => 'Steve McConnell', 'publisher' => 'Microsoft Press', 'year' => 2004, 'isbn' => '978-602-1234-302', 'stock' => 4, 'cover' => 'code_complete.jpg'],
            ['title' => 'Design Patterns', 'author' => 'Erich Gamma', 'publisher' => 'Addison-Wesley', 'year' => 1994, 'isbn' => '978-602-1234-303', 'stock' => 3, 'cover' => 'design_patterns.jpg'],
            ['title' => 'Head First Java', 'author' => 'Kathy Sierra', 'publisher' => 'O\'Reilly Media', 'year' => 2005, 'isbn' => '978-602-1234-304', 'stock' => 5, 'cover' => 'head_first_java.jpg'],
            ['title' => 'Learning PHP, MySQL & JavaScript', 'author' => 'Robin Nixon', 'publisher' => 'O\'Reilly Media', 'year' => 2021, 'isbn' => '978-602-1234-305', 'stock' => 4, 'cover' => 'learning_php_mysql_javascript.jpg'],
            ['title' => 'Learning SQL', 'author' => 'Alan Beauliue', 'publisher' => 'Tech Press', 'year' => 2022, 'isbn' => '978-602-1234-306', 'stock' => 3, 'cover' => 'sql_for_beginners.jpg'],
        ];

        foreach ($teknologiBooks as $book) {
            $book['category_id'] = $categoryIds[2];
            $book['description'] = 'Buku teknologi tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 4. Sains (ID: 4) - 10 Buku =====
        $sainsBooks = [
            ['title' => 'A Brief History of Time', 'author' => 'Stephen Hawking', 'publisher' => 'Bantam Books', 'year' => 1988, 'isbn' => '978-602-1234-015', 'stock' => 3, 'cover' => 'brief_history_time.jpg'],
            ['title' => 'The Selfish Gene', 'author' => 'Richard Dawkins', 'publisher' => 'Oxford University Press', 'year' => 1976, 'isbn' => '978-602-1234-016', 'stock' => 4, 'cover' => 'selfish_gene.jpg'],
            ['title' => 'Cosmos', 'author' => 'Carl Sagan', 'publisher' => 'Random House', 'year' => 1980, 'isbn' => '978-602-1234-017', 'stock' => 3, 'cover' => 'cosmos.jpg'],
            ['title' => 'The Origin of Species', 'author' => 'Charles Darwin', 'publisher' => 'John Murray', 'year' => 1859, 'isbn' => '978-602-1234-401', 'stock' => 3, 'cover' => 'origin_of_species.jpg'],
            ['title' => 'The Double Helix', 'author' => 'James D. Watson', 'publisher' => 'Atheneum', 'year' => 1968, 'isbn' => '978-602-1234-402', 'stock' => 4, 'cover' => 'double_helix.jpg'],
            ['title' => 'The Elegant Universe', 'author' => 'Brian Greene', 'publisher' => 'W.W. Norton', 'year' => 1999, 'isbn' => '978-602-1234-403', 'stock' => 3, 'cover' => 'elegant_universe.jpg'],
            ['title' => 'The God Particle', 'author' => 'Leon Lederman', 'publisher' => 'Houghton Mifflin', 'year' => 1993, 'isbn' => '978-602-1234-404', 'stock' => 4, 'cover' => 'god_particle.jpg'],
            ['title' => 'The Quantum Universe', 'author' => 'Brian Cox', 'publisher' => 'Da Capo Press', 'year' => 2011, 'isbn' => '978-602-1234-405', 'stock' => 3, 'cover' => 'quantum_universe.jpg'],
            ['title' => 'The Universe in a Nutshell', 'author' => 'Stephen Hawking', 'publisher' => 'Bantam Books', 'year' => 2001, 'isbn' => '978-602-1234-406', 'stock' => 4, 'cover' => 'universe_in_a_nutshell.jpg'],
            ['title' => 'The Grand Design', 'author' => 'Stephen Hawking', 'publisher' => 'Bantam Books', 'year' => 2010, 'isbn' => '978-602-1234-407', 'stock' => 3, 'cover' => 'grand_design.jpg'],
        ];

        foreach ($sainsBooks as $book) {
            $book['category_id'] = $categoryIds[3];
            $book['description'] = 'Buku sains tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 5. Sejarah (ID: 5) - 11 Buku =====
        $sejarahBooks = [
            ['title' => 'Sejarah Dunia yang Disembunyikan', 'author' => 'John Doe', 'publisher' => 'Pustaka Utama', 'year' => 2021, 'isbn' => '978-602-1234-018', 'stock' => 5, 'cover' => 'sejarah_dunia.jpg'],
            ['title' => 'Indonesia: Sejarah Awal', 'author' => 'M.C. Ricklefs', 'publisher' => 'Gramedia', 'year' => 2008, 'isbn' => '978-602-1234-019', 'stock' => 3, 'cover' => 'indonesia_sejarah.jpg'],
            ['title' => 'The Silk Roads', 'author' => 'Peter Frankopan', 'publisher' => 'Bloomsbury', 'year' => 2015, 'isbn' => '978-602-1234-020', 'stock' => 4, 'cover' => 'silk_roads.jpg'],
            ['title' => 'Sejarah Peradaban Manusia', 'author' => 'Will Durant', 'publisher' => 'Simon & Schuster', 'year' => 1935, 'isbn' => '978-602-1234-501', 'stock' => 3, 'cover' => 'sejarah_peradaban.jpg'],
            ['title' => 'Revolusi Indonesia', 'author' => 'Anthony Reid', 'publisher' => 'Equinox', 'year' => 1974, 'isbn' => '978-602-1234-502', 'stock' => 4, 'cover' => 'revolusi_indonesia.jpg'],
            ['title' => 'Sejarah Perang Dunia 1', 'author' => 'Martin Gilbert', 'publisher' => 'Henry Holt', 'year' => 1994, 'isbn' => '978-602-1234-503', 'stock' => 3, 'cover' => 'perang_dunia_1.jpg'],
            ['title' => 'Sejarah Perang Dunia 2', 'author' => 'Martin Gilbert', 'publisher' => 'Henry Holt', 'year' => 1998, 'isbn' => '978-602-1234-504', 'stock' => 4, 'cover' => 'perang_dunia_2.jpg'],
            ['title' => 'Sejarah Islam', 'author' => 'Karen Armstrong', 'publisher' => 'HarperOne', 'year' => 2000, 'isbn' => '978-602-1234-505', 'stock' => 4, 'cover' => 'sejarah_islam.jpg'],
            ['title' => 'Sejarah Eropa', 'author' => 'Norman Davies', 'publisher' => 'Oxford University Press', 'year' => 1996, 'isbn' => '978-602-1234-506', 'stock' => 3, 'cover' => 'sejarah_eropa.jpg'],
            ['title' => 'Sejarah Asia', 'author' => 'Rhoads Murphey', 'publisher' => 'Longman', 'year' => 1997, 'isbn' => '978-602-1234-507', 'stock' => 4, 'cover' => 'sejarah_asia.jpg'],
            ['title' => 'Sejarah Amerika', 'author' => 'Howard Zinn', 'publisher' => 'HarperPerennial', 'year' => 1980, 'isbn' => '978-602-1234-508', 'stock' => 3, 'cover' => 'sejarah_amerika.jpg'],
        ];

        foreach ($sejarahBooks as $book) {
            $book['category_id'] = $categoryIds[4];
            $book['description'] = 'Buku sejarah tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 6. Bisnis (ID: 6) - 10 Buku =====
        $bisnisBooks = [
            ['title' => 'The Lean Startup', 'author' => 'Eric Ries', 'publisher' => 'Crown Business', 'year' => 2011, 'isbn' => '978-602-1234-021', 'stock' => 4, 'cover' => 'lean_startup.jpg'],
            ['title' => 'Zero to One', 'author' => 'Peter Thiel', 'publisher' => 'Crown Business', 'year' => 2014, 'isbn' => '978-602-1234-022', 'stock' => 3, 'cover' => 'zero_to_one.jpg'],
            ['title' => 'Good to Great', 'author' => 'Jim Collins', 'publisher' => 'HarperBusiness', 'year' => 2001, 'isbn' => '978-602-1234-023', 'stock' => 3, 'cover' => 'good_to_great.jpg'],
            ['title' => 'Business Model Generation', 'author' => 'Alexander Osterwalder', 'publisher' => 'Wiley', 'year' => 2010, 'isbn' => '978-602-1234-601', 'stock' => 4, 'cover' => 'business_model_generation.jpg'],
            ['title' => 'Blue Ocean Strategy', 'author' => 'W. Chan Kim', 'publisher' => 'Harvard Business Review', 'year' => 2005, 'isbn' => '978-602-1234-602', 'stock' => 3, 'cover' => 'blue_ocean_strategy.jpg'],
            ['title' => 'Start with Why', 'author' => 'Simon Sinek', 'publisher' => 'Portfolio', 'year' => 2009, 'isbn' => '978-602-1234-603', 'stock' => 4, 'cover' => 'start_with_why.jpg'],
            ['title' => 'The Innovator\'s Dilemma', 'author' => 'Clayton Christensen', 'publisher' => 'Harvard Business Review', 'year' => 1997, 'isbn' => '978-602-1234-604', 'stock' => 3, 'cover' => 'the_innovators_dilemma.jpg'],
            ['title' => 'Built to Last', 'author' => 'Jim Collins', 'publisher' => 'HarperBusiness', 'year' => 1994, 'isbn' => '978-602-1234-605', 'stock' => 4, 'cover' => 'built_to_last.jpg'],
            ['title' => 'The eBay Phenomenon', 'author' => 'Adam Cohen', 'publisher' => 'Wiley', 'year' => 2002, 'isbn' => '978-602-1234-606', 'stock' => 3, 'cover' => 'the_ebay_phenomenon.jpg'],
            ['title' => 'The Amazon Way', 'author' => 'John Rossman', 'publisher' => 'Clyde Hill Publishing', 'year' => 2014, 'isbn' => '978-602-1234-607', 'stock' => 4, 'cover' => 'the_amazon_way.jpg'],
        ];

        foreach ($bisnisBooks as $book) {
            $book['category_id'] = $categoryIds[5];
            $book['description'] = 'Buku bisnis tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 7. Psikologi (ID: 7) - 10 Buku =====
        $psikologiBooks = [
            ['title' => 'Thinking, Fast and Slow', 'author' => 'Daniel Kahneman', 'publisher' => 'Farrar, Straus and Giroux', 'year' => 2011, 'isbn' => '978-602-1234-024', 'stock' => 4, 'cover' => 'thinking_fast_and_slow.jpg'],
            ['title' => 'Mindset', 'author' => 'Carol S. Dweck', 'publisher' => 'Random House', 'year' => 2006, 'isbn' => '978-602-1234-025', 'stock' => 3, 'cover' => 'mindset.jpg'],
            ['title' => 'The Psychology of Money', 'author' => 'Morgan Housel', 'publisher' => 'Harriman House', 'year' => 2020, 'isbn' => '978-602-1234-026', 'stock' => 5, 'cover' => 'psychology_of_money.jpg'],
            ['title' => 'Emotional Intelligence', 'author' => 'Daniel Goleman', 'publisher' => 'Bantam Books', 'year' => 1995, 'isbn' => '978-602-1234-701', 'stock' => 4, 'cover' => 'emotional_intelligence.jpg'],
            ['title' => 'How to Win Friends', 'author' => 'Dale Carnegie', 'publisher' => 'Simon & Schuster', 'year' => 1936, 'isbn' => '978-602-1234-702', 'stock' => 5, 'cover' => 'how_to_win_friends.jpg'],
            ['title' => 'Influence', 'author' => 'Robert Cialdini', 'publisher' => 'William Morrow', 'year' => 1984, 'isbn' => '978-602-1234-703', 'stock' => 3, 'cover' => 'influence.jpg'],
            ['title' => 'Drive', 'author' => 'Daniel H. Pink', 'publisher' => 'Riverhead Books', 'year' => 2009, 'isbn' => '978-602-1234-704', 'stock' => 4, 'cover' => 'drive.jpg'],
            ['title' => 'Grit', 'author' => 'Angela Duckworth', 'publisher' => 'Scribner', 'year' => 2016, 'isbn' => '978-602-1234-705', 'stock' => 3, 'cover' => 'grit.jpg'],
            ['title' => 'Flow', 'author' => 'Mihaly Csikszentmihalyi', 'publisher' => 'Harper & Row', 'year' => 1990, 'isbn' => '978-602-1234-706', 'stock' => 4, 'cover' => 'flow.jpg'],
            ['title' => 'The Power of Now', 'author' => 'Eckhart Tolle', 'publisher' => 'New World Library', 'year' => 1997, 'isbn' => '978-602-1234-707', 'stock' => 4, 'cover' => 'the_power_of_now.jpg'],
        ];

        foreach ($psikologiBooks as $book) {
            $book['category_id'] = $categoryIds[6];
            $book['description'] = 'Buku psikologi tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 8. Pendidikan (ID: 8) - 10 Buku =====
        $pendidikanBooks = [
            ['title' => 'Pendidikan Anak Usia Dini', 'author' => 'Dr. Montessori', 'publisher' => 'Pustaka Pendidikan', 'year' => 2019, 'isbn' => '978-602-1234-027', 'stock' => 4, 'cover' => 'pendidikan_anak.jpg'],
            ['title' => 'The Teacher\'s Guide', 'author' => 'John Hattie', 'publisher' => 'Routledge', 'year' => 2012, 'isbn' => '978-602-1234-028', 'stock' => 3, 'cover' => 'teachers_guide.jpg'],
            ['title' => 'Learning How to Learn', 'author' => 'Barbara Oakley', 'publisher' => 'TarcherPerigee', 'year' => 2018, 'isbn' => '978-602-1234-029', 'stock' => 3, 'cover' => 'learning_how_to_learn.jpg'],
            ['title' => 'Metode Montessori', 'author' => 'Maria Montessori', 'publisher' => 'Pustaka Pendidikan', 'year' => 1912, 'isbn' => '978-602-1234-801', 'stock' => 4, 'cover' => 'metode_montessori.jpg'],
            ['title' => 'Pendidikan Karakter', 'author' => 'Thomas Lickona', 'publisher' => 'Bantam Books', 'year' => 1991, 'isbn' => '978-602-1234-802', 'stock' => 3, 'cover' => 'pendidikan_karakter.jpg'],
            ['title' => 'Pendidikan Tinggi', 'author' => 'John Dewey', 'publisher' => 'Free Press', 'year' => 1916, 'isbn' => '978-602-1234-803', 'stock' => 3, 'cover' => 'pendidikan_tinggi.jpg'],
            ['title' => 'Pendidikan Anak Usia Dini 2', 'author' => 'Sue Bredekamp', 'publisher' => 'Pearson', 'year' => 2017, 'isbn' => '978-602-1234-804', 'stock' => 4, 'cover' => 'pendidikan_anak_usia_dini.jpg'],
            ['title' => 'Kurikulum Merdeka', 'author' => 'Kemendikbud', 'publisher' => 'Kemendikbud', 'year' => 2022, 'isbn' => '978-602-1234-805', 'stock' => 5, 'cover' => 'kurikulum_merdeka.jpg'],
            ['title' => 'Strategi Pembelajaran', 'author' => 'Robert Gagne', 'publisher' => 'Holt, Rinehart & Winston', 'year' => 1974, 'isbn' => '978-602-1234-806', 'stock' => 3, 'cover' => 'strategi_pembelajaran.jpg'],
            ['title' => 'Evaluasi Pengajaran', 'author' => 'Esti Esmawati', 'publisher' => 'Longman', 'year' => 2017, 'isbn' => '978-602-1234-807', 'stock' => 3, 'cover' => 'evaluasi_pengajaran.jpg'],
        ];

        foreach ($pendidikanBooks as $book) {
            $book['category_id'] = $categoryIds[7];
            $book['description'] = 'Buku pendidikan tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 9. Kesehatan (ID: 9) - 10 Buku =====
        $kesehatanBooks = [
            ['title' => 'Why We Sleep', 'author' => 'Matthew Walker', 'publisher' => 'Scribner', 'year' => 2017, 'isbn' => '978-602-1234-030', 'stock' => 4, 'cover' => 'why_we_sleep.jpg'],
            ['title' => 'The Blue Zones', 'author' => 'Dan Buettner', 'publisher' => 'National Geographic', 'year' => 2008, 'isbn' => '978-602-1234-031', 'stock' => 3, 'cover' => 'blue_zones.jpg'],
            ['title' => 'Eat, Move, Sleep', 'author' => 'Tom Rath', 'publisher' => 'Missionday', 'year' => 2013, 'isbn' => '978-602-1234-032', 'stock' => 3, 'cover' => 'eat_move_sleep.jpg'],
            ['title' => 'How Not to Die', 'author' => 'Michael Greger', 'publisher' => 'Flatiron Books', 'year' => 2015, 'isbn' => '978-602-1234-901', 'stock' => 4, 'cover' => 'how_not_to_die.jpg'],
            ['title' => 'The Plant Paradox', 'author' => 'Steven Gundry', 'publisher' => 'Harper Wave', 'year' => 2017, 'isbn' => '978-602-1234-902', 'stock' => 3, 'cover' => 'the_plant_paradox.jpg'],
            ['title' => 'The Obesity Code', 'author' => 'Jason Fung', 'publisher' => 'Greystone Books', 'year' => 2016, 'isbn' => '978-602-1234-903', 'stock' => 4, 'cover' => 'the_obesity_code.jpg'],
            ['title' => 'Salt, Sugar, Fat', 'author' => 'Michael Moss', 'publisher' => 'Random House', 'year' => 2013, 'isbn' => '978-602-1234-904', 'stock' => 3, 'cover' => 'salt_sugar_fat.jpg'],
            ['title' => 'The Immune System', 'author' => 'Peter Parham', 'publisher' => 'Garland Science', 'year' => 2014, 'isbn' => '978-602-1234-905', 'stock' => 3, 'cover' => 'the_immune_system.jpg'],
            ['title' => 'Brain Food', 'author' => 'Lisa Mosconi', 'publisher' => 'Avery', 'year' => 2018, 'isbn' => '978-602-1234-906', 'stock' => 4, 'cover' => 'brain_food.jpg'],
            ['title' => 'The Healing Self', 'author' => 'Deepak Chopra', 'publisher' => 'Harmony Books', 'year' => 2018, 'isbn' => '978-602-1234-907', 'stock' => 3, 'cover' => 'the_healing_self.jpg'],
        ];

        foreach ($kesehatanBooks as $book) {
            $book['category_id'] = $categoryIds[8];
            $book['description'] = 'Buku kesehatan tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 10. Agama (ID: 10) - 10 Buku =====
        $agamaBooks = [
            ['title' => 'The Power of Now', 'author' => 'Eckhart Tolle', 'publisher' => 'New World Library', 'year' => 1997, 'isbn' => '978-602-1234-033', 'stock' => 4, 'cover' => 'power_of_now.jpg'],
            ['title' => 'The Purpose Driven Life', 'author' => 'Rick Warren', 'publisher' => 'Zondervan', 'year' => 2002, 'isbn' => '978-602-1234-034', 'stock' => 3, 'cover' => 'purpose_driven_life.jpg'],
            ['title' => 'Muhammad: A Prophet for Our Time', 'author' => 'Karen Armstrong', 'publisher' => 'HarperOne', 'year' => 2006, 'isbn' => '978-602-1234-035', 'stock' => 3, 'cover' => 'muhammad_prophet.jpg'],
            ['title' => 'The Bhagavad Gita', 'author' => 'Swami Puri', 'publisher' => 'Penguin Classics', 'year' => 0, 'isbn' => '978-602-1234-1001', 'stock' => 4, 'cover' => 'the_bhagavad_gita.jpg'],
            ['title' => 'The Quran', 'author' => 'Marmaduke Pickthall', 'publisher' => 'Penguin Classics', 'year' => 0, 'isbn' => '978-602-1234-1002', 'stock' => 5, 'cover' => 'the_quran.jpg'],
            ['title' => 'The Bible', 'author' => 'Jesus Book', 'publisher' => 'Penguin Classics', 'year' => 0, 'isbn' => '978-602-1234-1003', 'stock' => 5, 'cover' => 'the_bible.jpg'],
            ['title' => 'Buddhism', 'author' => 'David N. Snyder', 'publisher' => 'CreateSpace', 'year' => 2015, 'isbn' => '978-602-1234-1004', 'stock' => 3, 'cover' => 'buddhism.jpg'],
            ['title' => 'Hinduism', 'author' => 'Kim Knott', 'publisher' => 'Oxford University Press', 'year' => 1998, 'isbn' => '978-602-1234-1005', 'stock' => 3, 'cover' => 'hinduism.jpg'],
            ['title' => 'Confucianism', 'author' => 'Daniel K. Gardner', 'publisher' => 'Oxford University Press', 'year' => 2014, 'isbn' => '978-602-1234-1006', 'stock' => 3, 'cover' => 'confucianism.jpg'],
            ['title' => 'Taoism', 'author' => 'Eva Wong', 'publisher' => 'Shambhala', 'year' => 1997, 'isbn' => '978-602-1234-1007', 'stock' => 3, 'cover' => 'taoism.jpg'],
        ];

        foreach ($agamaBooks as $book) {
            $book['category_id'] = $categoryIds[9];
            $book['description'] = 'Buku agama tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 11. Filsafat (ID: 11) - 10 Buku =====
        $filsafatBooks = [
            ['title' => 'Plato Republic', 'author' => 'Plato', 'publisher' => 'Penguin Classics', 'year' => 0, 'isbn' => '978-602-1234-1101', 'stock' => 4, 'cover' => 'plato_republic.jpg'],
            ['title' => 'Aristotle Ethics', 'author' => 'Aristotle', 'publisher' => 'Penguin Classics', 'year' => 0, 'isbn' => '978-602-1234-1102', 'stock' => 3, 'cover' => 'aristotle_ethics.jpg'],
            ['title' => 'Nietzsche Beyond Good', 'author' => 'Friedrich Nietzsche', 'publisher' => 'Penguin Classics', 'year' => 1886, 'isbn' => '978-602-1234-1103', 'stock' => 4, 'cover' => 'nietzsche_beyond_good.jpg'],
            ['title' => 'Kant Critique', 'author' => 'Immanuel Kant', 'publisher' => 'Penguin Classics', 'year' => 1781, 'isbn' => '978-602-1234-1104', 'stock' => 3, 'cover' => 'kant_critique.jpg'],
            ['title' => 'Descartes Meditations', 'author' => 'Rene Descartes', 'publisher' => 'Penguin Classics', 'year' => 1641, 'isbn' => '978-602-1234-1105', 'stock' => 3, 'cover' => 'descartes_meditations.jpg'],
            ['title' => 'Rousseau Social Contract', 'author' => 'Jean-Jacques Rousseau', 'publisher' => 'Penguin Classics', 'year' => 1762, 'isbn' => '978-602-1234-1106', 'stock' => 4, 'cover' => 'rousseau_social_contract.jpg'],
            ['title' => 'Locke Essay', 'author' => 'John Locke', 'publisher' => 'Penguin Classics', 'year' => 1689, 'isbn' => '978-602-1234-1107', 'stock' => 3, 'cover' => 'locke_essay.jpg'],
            ['title' => 'Hume Treatise', 'author' => 'David Hume', 'publisher' => 'Penguin Classics', 'year' => 1739, 'isbn' => '978-602-1234-1108', 'stock' => 3, 'cover' => 'hume_treatise.jpg'],
            ['title' => 'Kant Groundwork', 'author' => 'Immanuel Kant', 'publisher' => 'Penguin Classics', 'year' => 1785, 'isbn' => '978-602-1234-1109', 'stock' => 4, 'cover' => 'kant_groundwork.jpg'],
            ['title' => 'Mill Utilitarianism', 'author' => 'John Stuart Mill', 'publisher' => 'Penguin Classics', 'year' => 1861, 'isbn' => '978-602-1234-1110', 'stock' => 3, 'cover' => 'mill_utilitarianism.jpg'],
        ];

        foreach ($filsafatBooks as $book) {
            $book['category_id'] = $categoryIds[10];
            $book['description'] = 'Buku filsafat tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 12. Seni (ID: 12) - 10 Buku =====
        $seniBooks = [
            ['title' => 'The Story of Art', 'author' => 'E.H. Gombrich', 'publisher' => 'Phaidon Press', 'year' => 1950, 'isbn' => '978-602-1234-1201', 'stock' => 4, 'cover' => 'seni_rupa.jpg'],
            ['title' => 'Music as a Mirror of History', 'author' => 'Robert Greenberg', 'publisher' => 'Great Courses', 'year' => 1997, 'isbn' => '978-602-1234-1202', 'stock' => 3, 'cover' => 'musik_klasik.jpg'],
            ['title' => 'Manusia dan Kebudayaan di Indonesia', 'author' => 'Koentjaraningrat', 'publisher' => 'Gramedia', 'year' => 1985, 'isbn' => '978-602-1234-1203', 'stock' => 3, 'cover' => 'seni_budaya.jpg'],
            ['title' => 'TDR', 'author' => 'Richard Schechner', 'publisher' => 'Routledge', 'year' => 1988, 'isbn' => '978-602-1234-1204', 'stock' => 4, 'cover' => 'seni_pertunjukan.jpg'],
            ['title' => 'Film Art', 'author' => 'David Bordwell', 'publisher' => 'McGraw-Hill', 'year' => 1997, 'isbn' => '978-602-1234-1205', 'stock' => 3, 'cover' => 'film_dan_televisi.jpg'],
            ['title' => 'New Book of Photography', 'author' => 'John Hedgecoe', 'publisher' => 'DK Publishing', 'year' => 1992, 'isbn' => '978-602-1234-1206', 'stock' => 4, 'cover' => 'fotografi.jpg'],
            ['title' => "The Non-Designer's Design Book", 'author' => 'Robin Williams', 'publisher' => 'Peachpit Press', 'year' => 1994, 'isbn' => '978-602-1234-1207', 'stock' => 3, 'cover' => 'desain_grafis.jpg'],
            ['title' => 'Music and Imagination', 'author' => 'Aaron Copland', 'publisher' => 'Harvard University Press', 'year' => 1939, 'isbn' => '978-602-1234-1208', 'stock' => 3, 'cover' => 'seni_musik.jpg'],
            ['title' => "Australia's French Impressionist", 'author' => 'John Russell', 'publisher' => 'Harry N. Abrams', 'year' => 1993, 'isbn' => '978-602-1234-1209', 'stock' => 4, 'cover' => 'seni_lukis.jpg'],
            ['title' => 'Moore', 'author' => 'Taschen', 'publisher' => 'Thames & Hudson', 'year' => 1966, 'isbn' => '978-602-1234-1210', 'stock' => 3, 'cover' => 'seni_pahat.jpg'],
        ];

        foreach ($seniBooks as $book) {
            $book['category_id'] = $categoryIds[11];
            $book['description'] = 'Buku seni tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 13. Olahraga (ID: 13) - 10 Buku =====
        $olahragaBooks = [
            ['title' => 'Olahraga Tradisional', 'author' => 'Andi Suwirta', 'publisher' => 'Pustaka Olahraga', 'year' => 2010, 'isbn' => '978-602-1234-1301', 'stock' => 4, 'cover' => 'olahraga_tradisional.jpg'],
            ['title' => 'Sepak Bola', 'author' => 'David Baldacci', 'publisher' => 'Bloomsbury', 'year' => 2006, 'isbn' => '978-602-1234-1302', 'stock' => 5, 'cover' => 'sepak_bola.jpg'],
            ['title' => 'Bulu Tangkis', 'author' => 'Jake Downey', 'publisher' => 'The Crowood Press', 'year' => 2004, 'isbn' => '978-602-1234-1303', 'stock' => 3, 'cover' => 'bulu_tangkis.jpg'],
            ['title' => 'Renang', 'author' => 'Ernest W. Maglischo', 'publisher' => 'Human Kinetics', 'year' => 2003, 'isbn' => '978-602-1234-1304', 'stock' => 4, 'cover' => 'renang.jpg'],
            ['title' => 'Atletik', 'author' => 'Max Jones', 'publisher' => 'A & C Black', 'year' => 2006, 'isbn' => '978-602-1234-1305', 'stock' => 3, 'cover' => 'atletik.jpg'],
            ['title' => 'Senam', 'author' => 'Ryan Holiday', 'publisher' => 'Human Kinetics', 'year' => 2008, 'isbn' => '978-602-1234-1306', 'stock' => 3, 'cover' => 'senam.jpg'],
            ['title' => 'Bela Diri', 'author' => 'Bruce Lee', 'publisher' => 'Tuttle Publishing', 'year' => 1963, 'isbn' => '978-602-1234-1307', 'stock' => 4, 'cover' => 'bela_diri.jpg'],
            ['title' => 'Basket', 'author' => 'John Wooden', 'publisher' => 'Contemporary Books', 'year' => 1988, 'isbn' => '978-602-1234-1308', 'stock' => 4, 'cover' => 'basket.jpg'],
            ['title' => 'Cowboy', 'author' => 'Linda L. Miller', 'publisher' => 'Human Kinetics', 'year' => 2008, 'isbn' => '978-602-1234-1309', 'stock' => 3, 'cover' => 'cowboy.jpg'],
            ['title' => 'Tenis', 'author' => 'Nick Bollettieri', 'publisher' => 'Human Kinetics', 'year' => 2001, 'isbn' => '978-602-1234-1310', 'stock' => 3, 'cover' => 'tenis.jpg'],
        ];

        foreach ($olahragaBooks as $book) {
            $book['category_id'] = $categoryIds[12];
            $book['description'] = 'Buku olahraga tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 14. Politik (ID: 14) - 10 Buku =====
        $politikBooks = [
            ['title' => 'Politik Indonesia', 'author' => 'Herbert Feith', 'publisher' => 'Equinox', 'year' => 1962, 'isbn' => '978-602-1234-1401', 'stock' => 4, 'cover' => 'politik_indonesia.jpg'],
            ['title' => 'Politik Dunia', 'author' => 'John J. Mearsheimer', 'publisher' => 'W.W. Norton', 'year' => 2001, 'isbn' => '978-602-1234-1402', 'stock' => 3, 'cover' => 'politik_dunia.jpg'],
            ['title' => 'Demokrasi', 'author' => 'Robert A. Dahl', 'publisher' => 'Yale University Press', 'year' => 1998, 'isbn' => '978-602-1234-1403', 'stock' => 4, 'cover' => 'demokrasi.jpg'],
            ['title' => 'Politik Global', 'author' => 'Joseph Nye', 'publisher' => 'Little, Brown', 'year' => 2004, 'isbn' => '978-602-1234-1404', 'stock' => 3, 'cover' => 'politik_global.jpg'],
            ['title' => 'Politik Lokal', 'author' => 'Abd. Halim', 'publisher' => 'Yale University Press', 'year' => 1985, 'isbn' => '978-602-1234-1405', 'stock' => 3, 'cover' => 'politik_lokal.jpg'],
            ['title' => 'Politik Ekonomi', 'author' => 'Adam Smith', 'publisher' => 'Penguin Classics', 'year' => 1776, 'isbn' => '978-602-1234-1406', 'stock' => 4, 'cover' => 'politik_ekonomi.jpg'],
            ['title' => 'Politik HAM', 'author' => 'Jack Donnelly', 'publisher' => 'Westview Press', 'year' => 1989, 'isbn' => '978-602-1234-1407', 'stock' => 3, 'cover' => 'politik_ham.jpg'],
            ['title' => 'Politik Pembangunan', 'author' => 'Francis Fukuyama', 'publisher' => 'Free Press', 'year' => 1992, 'isbn' => '978-602-1234-1408', 'stock' => 3, 'cover' => 'politik_pembangunan.jpg'],
            ['title' => 'Politik Internasional', 'author' => 'Hans Morgenthau', 'publisher' => 'University of Chicago Press', 'year' => 1948, 'isbn' => '978-602-1234-1409', 'stock' => 4, 'cover' => 'politik_internasional.jpg'],
            ['title' => 'Politik Perbandingan', 'author' => 'Mohtar M. C. MacAndrews', 'publisher' => 'Little, Brown', 'year' => 1966, 'isbn' => '978-602-1234-1410', 'stock' => 3, 'cover' => 'politik_perbandingan.jpg'],
        ];

        foreach ($politikBooks as $book) {
            $book['category_id'] = $categoryIds[13];
            $book['description'] = 'Buku politik tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ===== 15. Ekonomi (ID: 15) - 10 Buku =====
        $ekonomiBooks = [
            ['title' => 'Ekonomi Mikro', 'author' => 'Gregory Mankiw', 'publisher' => 'Worth Publishers', 'year' => 1998, 'isbn' => '978-602-1234-1501', 'stock' => 4, 'cover' => 'ekonomi_mikro.jpg'],
            ['title' => 'Ekonomi Makro', 'author' => 'Olivier Blanchard', 'publisher' => 'Prentice Hall', 'year' => 2000, 'isbn' => '978-602-1234-1502', 'stock' => 3, 'cover' => 'ekonomi_makro.jpg'],
            ['title' => 'Ekonomi Keuangan', 'author' => 'N. Gregory Mankiw', 'publisher' => 'Worth Publishers', 'year' => 1995, 'isbn' => '978-602-1234-1503', 'stock' => 3, 'cover' => 'ekonomi_keuangan.jpg'],
            ['title' => 'Ekonomi Manajerial', 'author' => 'Michael R. Baye', 'publisher' => 'McGraw-Hill', 'year' => 2000, 'isbn' => '978-602-1234-1504', 'stock' => 4, 'cover' => 'ekonomi_manajerial.jpg'],
            ['title' => 'Ekonomi Pembangunan', 'author' => 'Michael Todaro', 'publisher' => 'Addison-Wesley', 'year' => 1994, 'isbn' => '978-602-1234-1505', 'stock' => 3, 'cover' => 'ekonomi_pembangunan.jpg'],
            ['title' => 'Ekonomi Internasional', 'author' => 'Paul Krugman', 'publisher' => 'Addison-Wesley', 'year' => 1991, 'isbn' => '978-602-1234-1506', 'stock' => 4, 'cover' => 'ekonomi_internasional.jpg'],
            ['title' => 'Ekonomi Industri', 'author' => 'Wihana Kirana Jaya', 'publisher' => 'Harcourt Brace', 'year' => 1970, 'isbn' => '978-602-1234-1507', 'stock' => 3, 'cover' => 'ekonomi_industri.jpg'],
            ['title' => 'Ekonomi Pertanian', 'author' => 'Wahyunita Sitinjak', 'publisher' => 'University of Chicago Press', 'year' => 1962, 'isbn' => '978-602-1234-1508', 'stock' => 3, 'cover' => 'ekonomi_pertanian.jpg'],
            ['title' => 'Ekonomi Lingkungan', 'author' => 'Yunhondri Danhas, Bustari Muchtar', 'publisher' => 'Edward Elgar', 'year' => 1995, 'isbn' => '978-602-1234-1509', 'stock' => 4, 'cover' => 'ekonomi_lingkungan.jpg'],
            ['title' => 'Ekonomi Kreatif', 'author' => 'John Howkins', 'publisher' => 'Penguin', 'year' => 2001, 'isbn' => '978-602-1234-1510', 'stock' => 3, 'cover' => 'ekonomi_kreatif.jpg'],
        ];

        foreach ($ekonomiBooks as $book) {
            $book['category_id'] = $categoryIds[14];
            $book['description'] = 'Buku ekonomi tentang '.$book['title'];
            $book['cover'] = getCoverPath($book['cover']);
            $books[] = $book;
        }

        // ========== SIMPAN DATA BUKU ==========
        foreach ($books as $book) {
            $book['available_stock'] = $book['stock'];
            Book::create($book);
        }
    }
}
