<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MemberMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cek apakah user sudah login
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        // Cek apakah user adalah member
        if (! Auth::user()->isMember()) {
            abort(403, 'Akses ditolak! Hanya untuk Member.');
        }

        // C6: akun yang diblokir lewat toggle-status ditandai users.status =
        // inactive, sedangkan sesinya tidak otomatis terputus. Tanpa cek ini,
        // member yang sedang login tetap bisa mengakses area member sampai
        // logout manual.
        if (Auth::user()->status !== 'active') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Akun Anda dinonaktifkan. Hubungi administrator.');
        }

        return $next($request);
    }
}
