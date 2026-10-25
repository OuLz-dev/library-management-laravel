@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4><i class="fas fa-sign-in-alt me-2"></i> Login Perpustakaan The Origin</h4>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                               value="{{ old('email') }}" required autofocus>
                        @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="mb-3 form-check d-flex justify-content-between align-items-center">
                        <div>
                            <input type="checkbox" name="remember" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">Remember Me</label>
                        </div>
                        <a href="{{ route('password.request') }}" class="small">Lupa password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
                </form>
                @if(session('status'))
                    <div class="alert alert-success alert-dismissible fade show mt-3">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                <hr>
                <p class="text-center mb-0">Belum punya akun? <a href="{{ route('register') }}">Daftar sebagai Member</a></p>
                <div class="bg-light p-3 rounded-3 mt-3 text-center small">
                    <p class="mb-1 fw-semibold">Akun Demo:</p>
                    <p class="mb-0">Admin: admin@gmail.com / password123</p>
                    <p class="mb-0">Member: member@gmail.com / password123</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection