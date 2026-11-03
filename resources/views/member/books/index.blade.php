@extends('layouts.app')

@section('title', 'Katalog Buku')

@section('content')
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-book me-2"></i> Katalog Buku Perpustakaan</h5>
    </div>
    <div class="card-body">
        <!-- FORM SEARCH & FILTER -->
        <form method="GET" action="{{ route('member.books.index') }}" class="mb-4">
            <div class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Cari judul atau penulis..."
                               value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-control">
                        <option value="">-- Semua Kategori --</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->getKey() }}" {{ request('category') == $category->getKey() ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="availability" class="form-control">
                        <option value="">-- Semua Stok --</option>
                        <option value="available" {{ request('availability') == 'available' ? 'selected' : '' }}>Tersedia</option>
                        <option value="out" {{ request('availability') == 'out' ? 'selected' : '' }}>Habis</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    @if(request('search') || request('category') || request('availability'))
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            <span class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Menampilkan <strong>{{ $books->total() }}</strong> buku
                            </span>
                            @if(request('search'))
                                <span class="badge bg-info">Pencarian: "{{ request('search') }}"</span>
                            @endif
                            @if(request('category') && $categories->firstWhere('id', request('category')))
                                <span class="badge bg-info">Kategori: "{{ $categories->firstWhere('id', request('category'))->name }}"</span>
                            @endif
                            @if(request('availability') == 'available')
                                <span class="badge bg-success">Stok: Tersedia</span>
                            @elseif(request('availability') == 'out')
                                <span class="badge bg-danger">Stok: Habis</span>
                            @endif
                            <a href="{{ route('member.books.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times me-1"></i> Reset Filter
                            </a>
                        </div>
                    @else
                        <span class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Menampilkan <strong>{{ $books->total() }}</strong> buku di katalog
                        </span>
                    @endif
                </div>
            </div>
        </form>

        @if($books->count() > 0)
            <div class="row">
                @foreach($books as $book)
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <!-- Cover -->
                        <div style="height: 220px; overflow: hidden; background: #f8f9fa;">
                            @if($book->cover && Storage::disk('public')->exists($book->cover))
                                <img src="{{ asset('storage/' . $book->cover) }}" 
                                     alt="{{ $book->title }}" 
                                     class="card-img-top" 
                                     style="width: 100%; height: 220px; object-fit: cover;">
                            @else
                                <div class="d-flex align-items-center justify-content-center" 
                                     style="height: 220px; background: #e9ecef;">
                                    <div class="text-center text-muted">
                                        <i class="fas fa-book fa-4x"></i>
                                        <p class="mb-0 small">Tidak ada cover</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                        
                        <div class="card-body">
                            <h6 class="card-title">{{ Str::limit($book->title, 40) }}</h6>
                            <p class="text-muted small mb-1">
                                <i class="fas fa-user me-1"></i> {{ $book->author ?? 'Tidak diketahui' }}
                            </p>
                            <p class="text-muted small mb-2">
                                <i class="fas fa-tag me-1"></i> {{ $book->category->name ?? 'Tanpa Kategori' }}
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-{{ $book->available_stock > 0 ? 'success' : 'danger' }}">
                                    {{ $book->available_stock > 0 ? 'Tersedia' : 'Habis' }}
                                </span>
                                <small class="text-muted">Stok: {{ $book->available_stock }}</small>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <a href="{{ route('member.books.show', $book) }}" class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-info-circle me-1"></i> Detail
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            
            <!-- menampilkan total buku  -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <strong>{{ $books->firstItem() }}</strong> - <strong>{{ $books->lastItem() }}</strong>
                    dari <strong>{{ $books->total() }}</strong> buku di katalog
                </div>
                <div>
                    {{ $books->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <p class="text-muted">Belum ada buku di katalog</p>
                @if(request('search') || request('category') || request('availability'))
                    <a href="{{ route('member.books.index') }}" class="btn btn-primary">
                        <i class="fas fa-undo me-1"></i> Lihat Semua Buku
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection