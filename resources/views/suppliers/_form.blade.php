@php($editing = $supplier->exists)

<form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="grid gap-5 lg:grid-cols-5">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="lg:col-span-3">
        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="section-title">Informasi supplier</h3>
                    <p class="muted">Identitas dan kontak yang dapat dihubungi</p>
                </div>
                @if ($editing)
                    <span class="badge {{ $supplier->is_active ? 'badge-green' : 'badge-slate' }}">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                @endif
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="code">Kode supplier</label>
                    <input id="code" name="code" value="{{ old('code', $supplier->code) }}" class="input" required maxlength="30">
                    @error('code')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="name">Nama supplier</label>
                    <input id="name" name="name" value="{{ old('name', $supplier->name) }}" class="input" required maxlength="120" placeholder="Contoh: PT Sinar Office Nusantara">
                    @error('name')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="contact_person">Nama kontak</label>
                    <input id="contact_person" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}" class="input" maxlength="100" placeholder="Nama PIC">
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $supplier->email) }}" class="input" maxlength="120" placeholder="sales@supplier.co.id">
                    @error('email')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="phone">Telepon</label>
                    <input id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" class="input" maxlength="50" placeholder="021-1234567">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 pb-2 text-xs text-slate-600">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $supplier->is_active))>
                        Supplier aktif dan dapat dipilih pada evaluasi
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="address">Alamat</label>
                    <textarea id="address" name="address" class="textarea" rows="2" maxlength="500">{{ old('address', $supplier->address) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="notes">Catatan</label>
                    <textarea id="notes" name="notes" class="textarea" rows="3" maxlength="1000" placeholder="Informasi tambahan seperti masa kontrak, sertifikasi, atau hasil negosiasi.">{{ old('notes', $supplier->notes) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="card card-pad">
            <h3 class="section-title">Nilai kriteria</h3>
            <p class="muted">Masukkan nilai mentah sesuai satuan masing-masing kriteria.</p>

            <div class="mt-5 space-y-4">
                @forelse ($criteria as $criterion)
                    @php($value = old('scores.'.$criterion->id, $supplier->scoreFor($criterion->id)))
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <label class="label mb-0" for="score-{{ $criterion->id }}">{{ $criterion->name }}</label>
                            <div class="flex items-center gap-1">
                                <span class="badge {{ $criterion->isCost() ? 'badge-amber' : 'badge-green' }}">{{ $criterion->isCost() ? 'cost' : 'benefit' }}</span>
                                <span class="badge badge-slate">{{ fmt_num($criterion->weight, 1) }}%</span>
                            </div>
                        </div>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input id="score-{{ $criterion->id }}" name="scores[{{ $criterion->id }}]" type="number" step="any" min="0"
                                   value="{{ $value }}" class="input" placeholder="0">
                            <span class="w-20 shrink-0 text-[11px] text-slate-500">{{ $criterion->unit ?: 'nilai' }}</span>
                        </div>
                        <p class="hint">{{ $criterion->isCost() ? 'Semakin kecil semakin baik.' : 'Semakin besar semakin baik.' }} {{ $criterion->description }}</p>
                        @error('scores.'.$criterion->id)<p class="hint text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @empty
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[11px] text-amber-800">
                        Belum ada kriteria aktif. Tambahkan kriteria terlebih dahulu.
                    </div>
                @endforelse
            </div>

            <div class="mt-5 rounded-lg bg-slate-50 px-3 py-2.5 text-[11px] leading-relaxed text-slate-500">
                Nilai akan dinormalisasi otomatis saat evaluasi. Untuk kriteria <span class="font-medium text-slate-700">cost</span>
                seperti harga, nilai lebih rendah menghasilkan performa lebih tinggi.
            </div>
        </div>

        <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <a href="{{ $editing ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">
                <x-icon name="save" class="h-4 w-4" /> {{ $editing ? 'Simpan perubahan' : 'Tambah supplier' }}
            </button>
        </div>
    </div>
</form>
