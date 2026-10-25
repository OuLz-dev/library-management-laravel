<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_create_a_member(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/members', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'secret123',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 1',
        ]);

        $response->assertRedirect('/admin/members');

        // Pasca-merge: member = baris User ber-role 'member' (tabel members
        // sudah tidak ada) dengan kode member yang diisi otomatis.
        $member = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame('member', $member->role);
        $this->assertSame('active', $member->status);
        $this->assertNotEmpty($member->member_code);
    }

    public function test_admin_cannot_create_member_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.com']);

        $response = $this->actingAs($this->admin)->post('/admin/members', [
            'name' => 'Duplikat',
            'email' => 'dupe@example.com',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_member_list_is_paginated_by_10(): void
    {
        User::factory()->count(14)->create(); // default factory = role member

        $this->actingAs($this->admin)
            ->get('/admin/members')
            ->assertOk()
            ->assertViewHas('members', fn ($members) => $members->count() === 10 && $members->total() === 14);
    }

    // ---------- TOGGLE STATUS ----------

    public function test_toggling_member_deactivates_the_member_row(): void
    {
        // Pasca-merge: satu kolom users.status menentukan keaktifan member.
        $member = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->admin)
            ->put('/admin/members/'.$member->id.'/toggle-status');

        $response->assertRedirect('/admin/members');

        $this->assertSame('inactive', $member->refresh()->status);
        $this->assertFalse($member->canBorrow());
    }

    public function test_toggling_again_reactivates_member(): void
    {
        $member = User::factory()->blocked()->create();

        $this->actingAs($this->admin)
            ->put('/admin/members/'.$member->id.'/toggle-status');

        $this->assertSame('active', $member->refresh()->status);
    }

    // ---------- REGISTRASI ----------

    public function test_registration_creates_member_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'Anggota Baru',
            'email' => 'anggota@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/member/dashboard');

        $member = User::where('email', 'anggota@example.com')->firstOrFail();

        $this->assertSame('member', $member->role);
        $this->assertSame('active', $member->status);
    }

    public function test_member_codes_are_unique_across_registrations(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/register', [
                'name' => "Anggota $i",
                'email' => "anggota{$i}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            // Middleware 'guest' menolak registrasi berikutnya selama masih login.
            $this->post('/logout');
        }

        $codes = User::where('role', 'member')->pluck('member_code');

        $this->assertCount(5, $codes);
        $this->assertCount(5, $codes->unique());
    }

    // ---------- ATURAN PINJAM ----------

    public function test_blocked_member_cannot_borrow_but_active_one_can(): void
    {
        $this->assertFalse(User::factory()->blocked()->create()->canBorrow());
        $this->assertTrue(User::factory()->create()->canBorrow());
    }

    public function test_member_with_three_active_borrows_cannot_borrow_more(): void
    {
        $member = User::factory()->create();
        Loan::factory()->count(3)->for($member)->create();

        $this->assertSame(3, $member->getActiveBorrowsCount());
        $this->assertFalse($member->canBorrow());
    }

    // ---------- C2: GENERATOR TUNGGAL member_code ----------

    public function test_admin_creation_and_registration_do_not_collide(): void
    {
        // Bug lama: registrasi memakai User::count() sementara admin memakai
        // Member::count()+1 — keduanya bisa menghasilkan kode yang sama.
        $this->post('/register', [
            'name' => 'Anggota Registrasi',
            'email' => 'registrasi@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $this->post('/logout');

        $this->actingAs($this->admin)->post('/admin/members', [
            'name' => 'Anggota Admin',
            'email' => 'dibuatadmin@example.com',
            'password' => 'secret123',
        ]);

        $codes = User::where('role', 'member')->pluck('member_code');

        $this->assertCount(2, $codes);
        $this->assertCount(2, $codes->unique());
    }

    public function test_member_code_stays_unique_after_delete_and_recreate(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();

        // Hapus member yang paling awal, lalu buat member baru.
        $first->delete();

        $fourth = User::factory()->create();

        $codes = User::where('role', 'member')->pluck('member_code');

        $this->assertCount(3, $codes);
        $this->assertCount(3, $codes->unique(), 'kode member tidak boleh tabrakan');
        $this->assertNotContains($fourth->member_code, [$second->member_code, $third->member_code]);
    }

    public function test_explicit_member_code_is_not_overwritten_by_hook(): void
    {
        $member = User::factory()->create(['member_code' => 'MBR-CUSTOM']);

        $this->assertSame('MBR-CUSTOM', $member->fresh()->member_code);
    }

    public function test_generated_member_code_format(): void
    {
        $member = User::factory()->create();

        $this->assertMatchesRegularExpression('/^MBR-\d{5}$/', $member->member_code);
    }

    // ---------- C6: SESI AKUN NON-AKTIF ----------

    public function test_blocked_member_session_is_terminated_on_next_request(): void
    {
        $member = User::factory()->create();

        // Pastikan sesi aktif dulu.
        $this->actingAs($member)->get('/member/dashboard')->assertOk();

        // Admin memblokir member (users.status -> inactive).
        $member->update(['status' => 'inactive']);

        // Request berikutnya dengan sesi lama harus ditendang ke login.
        $this->actingAs($member->fresh())->get('/member/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_blocked_admin_session_is_terminated_on_next_request(): void
    {
        $admin = User::factory()->admin()->create(['status' => 'active']);

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $admin->update(['status' => 'inactive']);

        $this->actingAs($admin->fresh())->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
