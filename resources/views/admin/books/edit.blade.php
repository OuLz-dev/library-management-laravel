@extends('layouts.app')

@section('title', 'Edit Buku')

@section('content')
<div class="card">
    <div class="card-header bg-warning text-white">
        <h5 class="mb-0"><i class="fas fa-edit me-2"></i> Edit Buku</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.books.update', $book) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Judul Buku <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" 
                               value="{{ old('title', $book->title) }}" required>
                        @error('title')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">ISBN <span class="text-danger">*</span></label>
                            <input type="text" name="isbn" class="form-control @error('isbn') is-invalid @enderror" 
                                   value="{{ old('isbn', $book->isbn) }}" required>
                            @error('isbn')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Stok <span class="text-danger">*</span></label>
                            <input type="number" name="stock" class="form-control @error('stock') is-invalid @enderror" 
                                   value="{{ old('stock', $book->stock) }}" min="1" required>
                            @error('stock')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="text-muted">Stok tersedia saat ini: {{ $book->available_stock }}</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Penulis</label>
                            <input type="text" name="author" class="form-control" value="{{ old('author', $book->author) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Penerbit</label>
                            <input type="text" name="publisher" class="form-control" value="{{ old('publisher', $book->publisher) }}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Tahun Terbit</label>
                            <input type="number" name="year" class="form-control" value="{{ old('year', $book->year) }}" 
                                   min="1900" max="{{ date('Y') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Kategori</label>
                            <select name="category_id" class="form-control">
                                <option value="">Pilih Kategori</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->getKey() }}" {{ old('category_id', $book->category_id) == $category->getKey() ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description', $book->description) }}</textarea>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-image me-2"></i> Cover Buku</h6>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <div id="coverPreview" class="border rounded p-3" 
                                     style="min-height: 250px; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                                    @if($book->cover && Storage::disk('public')->exists($book->cover))
                                        <img src="{{ asset('storage/' . $book->cover) }}" 
                                             alt="{{ $book->title }}" 
                                             style="max-height: 250px; object-fit: contain;" 
                                             class="img-fluid rounded">
                                    @else
                                        <div>
                                            <i class="fas fa-book fa-4x text-muted"></i>
                                            <p class="text-muted mt-2">Tidak ada cover</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="cover" class="form-label fw-semibold">Ganti Cover</label>
                                <input type="file" name="cover" id="cover" 
                                       class="form-control @error('cover') is-invalid @enderror" 
                                       accept="image/*">
                                <small class="text-muted">Format: JPG, PNG, GIF (Max 2MB). Kosongkan jika tidak ingin mengubah.</small>
                                @error('cover')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            @if($book->cover)
                                <small class="text-muted">Cover saat ini: {{ basename($book->cover) }}</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Update Buku
                </button>
                <a href="{{ route('admin.books.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('cover').addEventListener('change', function(e) {
        const preview = document.getElementById('coverPreview');
        const file = e.target.files[0];
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                preview.innerHTML = `<img src="${event.target.result}" 
                                          class="img-fluid rounded" 
                                          style="max-height: 250px; object-fit: contain;">`;
            }
            reader.readAsDataURL(file);
        }
    });
</script>
@endpush
@endsection