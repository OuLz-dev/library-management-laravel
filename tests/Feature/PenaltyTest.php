<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenaltyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function unpaidPenalty(): Penalty
    {
        $loan = Loan::factory()->returnedLate(3)->create();

        // Pin nilainya supaya perhitungan total bisa diprediksi (3 × 2000 = 6000).
        return Penalty::factory()->create([
            'loan_id' => $loan->getKey(),
            'member_id' => $loan->member_id,
            'days_late' => 3,
            'fine_amount' => 6000,
        ]);
    }

    public function test_admin_can_mark_penalty_as_paid(): void
    {
        $penalty = $this->unpaidPenalty();

        $response = $this->actingAs($this->admin)
            ->put('/admin/penalties/'.$penalty->getKey().'/pay');

        $response->assertRedirect('/admin/penalties');

        $penalty->refresh();
        $this->assertSame('paid', $penalty->status);
        $this->assertNotNull($penalty->paid_date);
    }

    public function test_paying_an_already_paid_penalty_is_rejected(): void
    {
        $penalty = Penalty::factory()->paid()->create();

        $response = $this->actingAs($this->admin)
            ->put('/admin/penalties/'.$penalty->getKey().'/pay');

        $response->assertSessionHas('error');
        $this->assertSame('paid', $penalty->refresh()->status);
    }

    public function test_pay_form_is_rendered_for_unpaid_penalty(): void
    {
        $penalty = $this->unpaidPenalty();

        $this->actingAs($this->admin)
            ->get('/admin/penalties/'.$penalty->getKey().'/pay')
            ->assertOk()
            ->assertViewIs('admin.penalties.pay');
    }

    public function test_pay_form_redirects_when_penalty_already_paid(): void
    {
        $penalty = Penalty::factory()->paid()->create();

        $this->actingAs($this->admin)
            ->get('/admin/penalties/'.$penalty->getKey().'/pay')
            ->assertRedirect('/admin/penalties');
    }

    public function test_penalty_index_shows_paid_and_unpaid_totals(): void
    {
        $this->unpaidPenalty(); // 3 hari × 2000 = 6000
        Penalty::factory()->count(2)->paid()->create([
            'days_late' => 2,
            'fine_amount' => 4000,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/penalties')
            ->assertOk()
            ->assertViewHas('totalUnpaid', 6000)
            ->assertViewHas('totalPaid', 8000);
    }

    public function test_member_only_sees_their_own_penalties(): void
    {
        // Pasca-merge: denda merujuk langsung ke users (member_id).
        $memberUser = User::factory()->create();

        $myLate = Loan::factory()->for($memberUser)->returnedLate(2)->create();
        $mine = Penalty::factory()->create([
            'loan_id' => $myLate->getKey(),
            'member_id' => $myLate->member_id,
        ]);
        $other = $this->unpaidPenalty();

        $response = $this->actingAs($memberUser)->get('/member/my-penalties');

        $response->assertOk()->assertViewHas('penalties', function ($penalties) use ($mine, $other) {
            $ids = $penalties->pluck('penalties_id');

            return $ids->contains($mine->getKey()) && ! $ids->contains($other->getKey());
        });
    }

    public function test_member_dashboard_shows_unpaid_fine_total(): void
    {
        $loan = Loan::factory()->returnedLate(3)->create();
        Penalty::factory()->create([
            'loan_id' => $loan->getKey(),
            'member_id' => $loan->member_id,
            'days_late' => 3,
            'fine_amount' => 6000,
        ]);

        $this->actingAs($loan->user)
            ->get('/member/dashboard')
            ->assertOk()
            ->assertViewHas('unpaidPenalty', 6000);
    }

    public function test_admin_penalty_index_shows_running_fine_without_writing_penalty(): void
    {
        // Denda berjalan dihitung real-time dari pinjaman yang lewat jatuh tempo
        // (3 hari × Rp 2.000 = Rp 6.000) tanpa membuat record di tabel penalties.
        $loan = Loan::factory()->overdue()->create();

        $this->actingAs($this->admin)
            ->get('/admin/penalties')
            ->assertOk()
            ->assertViewHas('totalUnpaid', 0)
            ->assertViewHas('totalRunning', 6000)
            ->assertViewHas('runningLoans', fn ($loans) => $loans->count() === 1
                && $loans->first()->getKey() === $loan->getKey())
            ->assertSee('Denda Berjalan')
            ->assertSee($loan->loan_code);

        $this->assertDatabaseCount('penalties', 0);
    }

    public function test_admin_penalty_index_hides_running_fine_when_filtering_paid(): void
    {
        // Filter "Lunas" hanya relevan untuk denda tercatat, jadi daftar denda
        // berjalan tidak ditampilkan (kartu statistik global tetap ada).
        $loan = Loan::factory()->overdue()->create();

        $this->actingAs($this->admin)
            ->get('/admin/penalties?status=paid')
            ->assertOk()
            ->assertViewHas('runningLoans', fn ($loans) => $loans->isEmpty())
            ->assertDontSee($loan->loan_code);
    }

    public function test_admin_penalty_index_search_applies_to_running_fines(): void
    {
        $mine = Loan::factory()->overdue()->create();
        $other = Loan::factory()->overdue()->create();

        $this->actingAs($this->admin)
            ->get('/admin/penalties?search='.$mine->loan_code)
            ->assertOk()
            ->assertViewHas('runningLoans', fn ($loans) => $loans->pluck('loans_id')->all() === [$mine->getKey()])
            ->assertSee($mine->loan_code)
            ->assertDontSee($other->loan_code);
    }

    public function test_member_penalty_page_shows_only_own_running_fine(): void
    {
        $member = User::factory()->create();
        $mine = Loan::factory()->for($member)->overdue()->create();
        $other = Loan::factory()->overdue()->create();

        $response = $this->actingAs($member)->get('/member/my-penalties');

        $response->assertOk()
            ->assertViewHas('totalRunning', 6000)
            ->assertViewHas('runningLoans', fn ($loans) => $loans->count() === 1
                && $loans->first()->getKey() === $mine->getKey())
            ->assertSee($mine->book->title)
            ->assertDontSee($other->book->title);
    }

    public function test_member_dashboard_includes_running_fine(): void
    {
        $member = User::factory()->create();
        Loan::factory()->for($member)->overdue()->create();

        $this->actingAs($member)
            ->get('/member/dashboard')
            ->assertOk()
            ->assertViewHas('runningPenalty', 6000)
            ->assertViewHas('unpaidPenalty', 0);
    }

    public function test_penalty_index_groups_all_books_under_one_transaction_row(): void
    {
        $member = User::factory()->create();
        $code = Loan::generateCode();

        $penalties = collect();
        $titles = [];

        for ($i = 0; $i < 3; $i++) {
            $loan = Loan::factory()->for($member)->forCode($code)->returnedLate(2)->create();
            $penalties->push(Penalty::factory()->create([
                'loan_id' => $loan->getKey(),
                'member_id' => $loan->member_id,
                'days_late' => 2,
                'fine_amount' => 4000,
            ]));
            $titles[] = $loan->book->title;
        }

        $response = $this->actingAs($this->admin)->get('/admin/penalties');

        // 3 record denda, tetapi hanya 1 baris transaksi.
        $response->assertOk()
            ->assertViewHas('penalties', fn ($paginator) => $paginator->total() === 1)
            ->assertViewHas('transactions', fn ($transactions) => $transactions->get($code)?->count() === 3)
            ->assertSee($code);

        foreach ($titles as $title) {
            $response->assertSee($title);
        }

        // Pembayaran tetap per buku: satu tautan Bayar per record denda.
        foreach ($penalties as $penalty) {
            $response->assertSee(route('admin.penalties.pay.form', $penalty));
        }
    }

    public function test_penalty_index_shows_partially_paid_transaction_status(): void
    {
        $member = User::factory()->create();
        $code = Loan::generateCode();

        $paidLoan = Loan::factory()->for($member)->forCode($code)->returnedLate(2)->create();
        Penalty::factory()->paid()->create([
            'loan_id' => $paidLoan->getKey(),
            'member_id' => $paidLoan->member_id,
            'days_late' => 2,
            'fine_amount' => 4000,
        ]);

        $unpaidLoan = Loan::factory()->for($member)->forCode($code)->returnedLate(1)->create();
        Penalty::factory()->create([
            'loan_id' => $unpaidLoan->getKey(),
            'member_id' => $unpaidLoan->member_id,
            'days_late' => 1,
            'fine_amount' => 2000,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/penalties')
            ->assertOk()
            ->assertSee($code)
            ->assertSee('Sebagian dibayar')
            ->assertSee('Total: Rp 6.000');
    }

    public function test_penalty_index_unpaid_filter_keeps_transactions_with_any_unpaid_book(): void
    {
        // Transaksi A: semua buku sudah lunas.
        $paidCode = Loan::generateCode();
        $paidLoan = Loan::factory()->forCode($paidCode)->returnedLate(1)->create();
        Penalty::factory()->paid()->create([
            'loan_id' => $paidLoan->getKey(),
            'member_id' => $paidLoan->member_id,
        ]);

        // Transaksi B: satu buku lunas, satu buku belum (tetap harus muncul).
        $mixedCode = Loan::generateCode();
        $mixedPaid = Loan::factory()->forCode($mixedCode)->returnedLate(1)->create();
        Penalty::factory()->paid()->create([
            'loan_id' => $mixedPaid->getKey(),
            'member_id' => $mixedPaid->member_id,
        ]);
        $mixedUnpaid = Loan::factory()->forCode($mixedCode)->returnedLate(1)->create();
        Penalty::factory()->create([
            'loan_id' => $mixedUnpaid->getKey(),
            'member_id' => $mixedUnpaid->member_id,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/penalties?status=unpaid')
            ->assertOk()
            ->assertViewHas('penalties', fn ($paginator) => $paginator->total() === 1)
            ->assertSee($mixedCode)
            ->assertDontSee($paidCode);
    }

    public function test_penalty_index_paginates_ten_transactions_per_page(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $loan = Loan::factory()->returnedLate(1)->create();
            Penalty::factory()->create([
                'loan_id' => $loan->getKey(),
                'member_id' => $loan->member_id,
                'days_late' => 1,
                'fine_amount' => 2000,
            ]);
        }

        $this->actingAs($this->admin)
            ->get('/admin/penalties')
            ->assertOk()
            ->assertViewHas('penalties', fn ($paginator) => $paginator->total() === 11
                && $paginator->count() === 10
                && $paginator->lastPage() === 2);

        $this->actingAs($this->admin)
            ->get('/admin/penalties?page=2')
            ->assertOk()
            ->assertViewHas('penalties', fn ($paginator) => $paginator->count() === 1);
    }

    public function test_unpaid_filter_with_running_fine_shows_scoped_empty_state(): void
    {
        // Tidak ada record denda, tetapi ada buku overdue: pesan kosong harus
        // jelas menyebut "denda tercatat" dan mengarahkan ke denda berjalan.
        $loan = Loan::factory()->overdue()->create();

        $this->actingAs($this->admin)
            ->get('/admin/penalties?status=unpaid')
            ->assertOk()
            ->assertViewHas('penalties', fn ($paginator) => $paginator->total() === 0)
            ->assertSee('Tidak ada denda tercatat yang belum dibayar.')
            ->assertSee('transaksi dengan denda berjalan')
            ->assertSee($loan->loan_code)
            ->assertSee('Lihat Denda Berjalan')
            ->assertDontSee('Tidak ada denda yang ditemukan');
    }

    public function test_paid_filter_shows_scoped_empty_state_without_running_section(): void
    {
        $loan = Loan::factory()->overdue()->create();

        $this->actingAs($this->admin)
            ->get('/admin/penalties?status=paid')
            ->assertOk()
            ->assertViewHas('penalties', fn ($paginator) => $paginator->total() === 0)
            ->assertSee('Belum ada denda tercatat yang lunas.')
            ->assertDontSee('transaksi dengan denda berjalan')
            ->assertDontSee($loan->loan_code);
    }
}
