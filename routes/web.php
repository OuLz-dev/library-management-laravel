<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanReturnController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberDashboardController;
use App\Http\Controllers\PenaltyController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// GUEST ROUTES
Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('login'));
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    // Lupa password (link reset dikirim via email/log)
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// AUTH ROUTES
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // ADMIN ROUTES
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Manajemen Buku
        Route::resource('books', BookController::class);

        // Manajemen Member
        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::get('members/create', [MemberController::class, 'create'])->name('members.create');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::put('members/{member}/toggle-status', [MemberController::class, 'toggleStatus'])->name('members.toggle-status');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        // Peminjaman (dulu "Transaksi")
        Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
        Route::get('loans/create', [LoanController::class, 'create'])->name('loans.create');
        Route::post('loans', [LoanController::class, 'store'])->name('loans.store');

        // Pengembalian: satu halaman riwayat + satu form aksi "Kembalikan Buku"
        Route::get('returns', [LoanReturnController::class, 'index'])->name('returns.index');
        Route::get('returns/create', [LoanReturnController::class, 'create'])->name('returns.create');
        Route::post('returns', [LoanReturnController::class, 'store'])->name('returns.store');

        //  DENDA
        Route::get('penalties', [PenaltyController::class, 'index'])->name('penalties.index');
        Route::get('penalties/{penalty}/pay', [PenaltyController::class, 'showPayForm'])->name('penalties.pay.form');
        Route::put('penalties/{penalty}/pay', [PenaltyController::class, 'payPenalty'])->name('penalties.pay');
    });

    // MEMBER ROUTES
    Route::middleware('member')->prefix('member')->name('member.')->group(function () {
        Route::get('/dashboard', [MemberDashboardController::class, 'index'])->name('dashboard');
        Route::get('/books', [BookController::class, 'memberIndex'])->name('books.index');
        Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');
        Route::get('/my-borrows', [MemberDashboardController::class, 'myBorrows'])->name('borrows');
        Route::get('/my-penalties', [MemberDashboardController::class, 'myPenalties'])->name('penalties');
    });

    // Redirect
    Route::get('/dashboard', function () {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('member.dashboard');
    })->name('dashboard');
});
