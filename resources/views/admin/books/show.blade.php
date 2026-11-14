@extends('layouts.app')

@section('title', $book->title)

@section('content')
<div class="card">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Detail Buku</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 text-center">
                @if($book->cover && Storage::disk('public')->exists($book->cover))
                    <img src="{{ asset('storage/' . $book->cover) }}" 
                         alt="{{ $book->title }}" 
                         class="img-fluid rounded shadow" 
                         style="max-height: 400px; object-fit: contain;">
                @else
                    <div class="bg-secondary text-white p-5 rounded shadow" style="min-height: 300px; display: flex; align-items: center; justify-content: center;">
                        <div>
                            <i class="fas fa-book fa-5x mb-3"></i>
                            <p class="mb-0">Tidak ada cover</p>
                        </div>
                    </div>
                @endif
            </div>
            <div class="col-md-8">
                <h3>{{ $book->title }}</h3>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="150">ISBN</th>
                                <td>{{ $book->isbn }}</td>
                            </tr>
                            <tr>
                                <th>Kategori</th>
                                <td>{{ $book->category->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Penulis</th>
                                <td>{{ $book->author ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Penerbit</th>
                                <td>{{ $book->publisher ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="150">Tahun Terbit</th>
                                <td>{{ $book->year ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Total Stok</th>
                                <td>{{ $book->stock }}</td>
                            </tr>
                            <tr>
                                <th>Stok Tersedia</th>
                                <td>
                                    @if($book->available_stock > 0)
                                        <span class="badge bg-success">{{ $book->available_stock }}</span>
                                    @else
                                        <span class="badge bg-danger">Habis</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if($book->available_stock > 0)
                                        <span class="badge bg-success">Tersedia</span>
                                    @else
                                        <span class="badge bg-danger">Tidak Tersedia</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                @if($book->description)
                    <div class="mt-3">
                        <h6>Deskripsi:</h6>
                        <p class="text-justify">{{ $book->description }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="card-footer">
        <a href="{{ route('admin.books.edit', $book) }}" class="btn btn-warning">
            <i class="fas fa-edit me-2"></i> Edit
        </a>
        <a href="{{ route('admin.books.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Kembali
        </a>
    </div>
</div>
@endsection