@extends('layouts.guest')

@section('title', 'Lupa Password')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4><i class="fas fa-key me-2"></i> Lupa Password</h4>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Masukkan email akun Anda. Kami akan mengirimkan link untuk mereset password.
                </p>

                @if(session('status'))
                    <div class="alert alert-success alert-dismissible fade show">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" required autofocus>
                        @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Kirim Link Reset</button>
                </form>
                <hr>
                <p class="text-center mb-0"><a href="{{ route('login') }}">Kembali ke Login</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
