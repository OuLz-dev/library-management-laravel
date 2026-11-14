<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        // Pasca-merge: member = User ber-role 'member' (tanpa tabel members).
        $query = User::where('role', 'member');

        // Filter berdasarkan status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search berdasarkan kode member, nama, email, no. telepon
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('member_code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $members = $query->latest()->paginate(10);

        // Menambahkan query string ke pagination
        $members->appends(request()->query());

        return view('admin.members.index', compact('members'));
    }

    public function create()
    {
        return view('admin.members.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'phone' => 'nullable|string|max:15',
            'address' => 'nullable|string',
        ]);

        // Kode member dihasilkan otomatis oleh User::booted() (C2)
        // agar tidak tabrakan dengan formula berbeda di tempat lain.
        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'member',
            'status' => 'active',
            'phone' => $request->phone,
            'address' => $request->address,
            'join_date' => now(),
        ]);

        return redirect()->route('admin.members.index')->with('success', 'Member berhasil ditambahkan');
    }

    public function toggleStatus(User $member)
    {
        // Pasca-merge: satu kolom status untuk semuanya —
        //   users.status = active   -> member aktif
        //   users.status = inactive -> member diblokir
        // (users.status inilah yang dicegah MemberMiddleware/AdminMiddleware.)
        $newStatus = $member->status === 'active' ? 'inactive' : 'active';
        $member->update(['status' => $newStatus]);

        $message = $newStatus === 'active' ? 'Member diaktifkan' : 'Member diblokir';

        return redirect()->route('admin.members.index')->with('success', $message);
    }

    public function destroy(User $member)
    {
        // Mengikuti kebijakan BookController::destroy: FK loans.member_id
        // dan penalties.member_id memakai ON DELETE CASCADE, jadi hapus member
        // akan menghapus riwayat transaksi & denda diam-diam. Tolak selama ada
        // peminjaman aktif maupun riwayat apa pun.
        if ($member->loans()->where('status', 'borrowed')->exists()) {
            return redirect()->route('admin.members.index')
                ->with('error', 'Member masih meminjam buku dan tidak bisa dihapus');
        }

        if ($member->loans()->exists()) {
            return redirect()->route('admin.members.index')
                ->with('error', 'Member memiliki riwayat peminjaman dan tidak bisa dihapus');
        }

        $member->delete();

        return redirect()->route('admin.members.index')->with('success', 'Member berhasil dihapus');
    }
}
