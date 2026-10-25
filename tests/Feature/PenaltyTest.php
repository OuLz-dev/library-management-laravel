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
}
