@extends('layouts.app')

@section('title', 'Manajemen Buku')

@section('content')
<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-book me-2"></i> Daftar Buku</h5>
        <a href="{{ route('admin.books.create') }}" class="btn btn-light btn-sm">
            <i class="fas fa-plus me-1"></i> Tambah Buku
        </a>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- FORM SEARCH & FILTER -->
        <form method="GET" action="{{ route('admin.books.index') }}" class="mb-4">
            <div class="row g-2">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Cari judul buku..."
                               value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="">-- Semua Kategori --</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->getKey() }}" {{ request('category') == $category->getKey() ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
            </div>
            <div class="mt-2">
                @if(request('search') || request('category'))
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
                        <a href="{{ route('admin.books.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times me-1"></i> Reset Filter
                        </a>
                    </div>
                @else
                    <span class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Menampilkan <strong>{{ $books->total() }}</strong> buku
                    </span>
                @endif
            </div>
        </form>

        @if($books->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Cover</th>
                            <th>Judul Buku</th>
                            <th>Penulis</th>
                            <th>Kategori</th>
                            <th>Stok</th>
                            <th>Tersedia</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($books as $index => $book)
                        <tr>
                            <td>{{ $books->firstItem() + $index }}</td>
                            <td>
                                @if($book->cover && Storage::disk('public')->exists($book->cover))
                                    <img src="{{ asset('storage/' . $book->cover) }}" 
                                         alt="{{ $book->title }}" 
                                         width="60" 
                                         height="80" 
                                         style="object-fit: cover; border-radius: 5px;">
                                @else
                                    <div class="bg-secondary text-white text-center rounded" 
                                         style="width: 60px; height: 80px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-book fa-2x"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <strong>{{ Str::limit($book->title, 40) }}</strong>
                                @if($book->year)
                                    <br><small class="text-muted">{{ $book->year }}</small>
                                @endif
                            </td>
                            <td>{{ $book->author ?? '-' }}</td>
                            <td>
                                <span class="badge bg-info">{{ $book->category->name ?? 'Tanpa Kategori' }}</span>
                            </td>
                            <td>{{ $book->stock }}</td>
                            <td>
                                @if($book->available_stock > 0)
                                    <span class="badge bg-success">{{ $book->available_stock }}</span>
                                @else
                                    <span class="badge bg-danger">Habis</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.books.show', $book) }}" 
                                       class="btn btn-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.books.edit', $book) }}" 
                                       class="btn btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('admin.books.destroy', $book) }}" 
                                          method="POST" 
                                          class="d-inline"
                                          onsubmit="return confirm('Yakin ingin menghapus buku ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Info jumlah buku + Pagination -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                <div class="text-muted">
                    <i class="fas fa-info-circle me-1"></i> 
                    Menampilkan <strong>{{ $books->firstItem() }}</strong>-<strong>{{ $books->lastItem() }}</strong> dari <strong>{{ $books->total() }}</strong> buku
                </div>
                <nav aria-label="Navigasi halaman buku">
                    {{ $books->links() }}
                </nav>
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <p class="text-muted">Belum ada data buku. <a href="{{ route('admin.books.create') }}">Tambah buku sekarang</a></p>
            </div>
        @endif
    </div>
</div>
@endsection