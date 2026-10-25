<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cek apakah user sudah login
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        // Cek apakah user adalah admin
        if (! Auth::user()->isAdmin()) {
            abort(403, 'Akses ditolak! Hanya untuk Admin.');
        }

        // C6: konsisten dengan MemberMiddleware — sesi akun non-aktif
        // tidak boleh tetap hidup.
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
