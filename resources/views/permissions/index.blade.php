@extends('layouts.app')

@section('title', 'Hak Akses & Pengguna')
@section('heading', 'Hak Akses & Pengguna')
@section('subheading', 'Tentukan halaman yang dapat dilihat atau dikelola setiap role')

@section('content')
    <form method="POST" action="{{ route('permissions.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 class="section-title">Matriks hak akses</h3>
                    <p class="muted">Centang <span class="font-medium text-slate-600">Lihat</span> untuk akses halaman, dan <span class="font-medium text-slate-600">Kelola</span> untuk tambah/ubah/hapus.</p>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <x-icon name="save" class="h-3.5 w-3.5" /> Simpan hak akses
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="min-w-[220px]">Halaman / Modul</th>
                            @foreach ($roles as $role => $roleLabel)
                                <th class="text-center">
                                    <span class="block text-[10px] normal-case tracking-normal text-slate-500">{{ $roleLabel }}</span>
                                    <span class="mt-0.5 inline-flex gap-3 text-[9px] font-normal normal-case tracking-normal text-slate-400">
                                        <span>Lihat</span><span>Kelola</span>
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($modules as $module => $meta)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-50 text-slate-500">
                                            <x-icon :name="$meta['icon']" class="h-3.5 w-3.5" />
                                        </span>
                                        <div>
                                            <p class="text-xs font-medium text-slate-800">{{ $meta['label'] }}</p>
                                            <p class="text-[9px] text-slate-400">{{ $meta['group'] }} · {{ $module }}</p>
                                        </div>
                                    </div>
                                </td>
                                @foreach ($roles as $role => $roleLabel)
                                    @php($entry = $matrix[$role][$module] ?? ['view' => false, 'manage' => false])
                                    <td>
                                        <div class="flex items-center justify-center gap-4">
                                            <input type="checkbox"
                                                   name="permissions[{{ $role }}][{{ $module }}][view]"
                                                   value="1"
                                                   class="checkbox"
                                                   @checked($entry['view'])
                                                   @disabled($role === 'admin' || $module === 'dashboard')>
                                            <input type="checkbox"
                                                   name="permissions[{{ $role }}][{{ $module }}][manage]"
                                                   value="1"
                                                   class="checkbox"
                                                   @checked($entry['manage'])
                                                   @disabled($role === 'admin' || ($meta['locked'] ?? false))>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 px-5 py-3 text-[10px] leading-relaxed text-slate-400">
                Administrator selalu memiliki akses penuh. Role <span class="font-medium text-slate-600">Analis</span> dan
                <span class="font-medium text-slate-600">Viewer</span> mengikuti pengaturan di atas. Hak kelola otomatis
                memberikan hak lihat.
            </div>
        </div>
    </form>

    <div class="mt-6 grid gap-5 xl:grid-cols-3">
        <div class="xl:col-span-1">
            <div class="card card-pad">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><x-icon name="plus" class="h-3.5 w-3.5" /></span>
                    <div>
                        <h3 class="section-title">Tambah pengguna</h3>
                        <p class="muted">Buat akun dan tentukan role</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('permissions.users.store') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="label" for="new_name">Nama</label>
                        <input id="new_name" name="name" class="input" required maxlength="100" value="{{ old('name') }}">
                    </div>
                    <div>
                        <label class="label" for="new_email">Email</label>
                        <input id="new_email" name="email" type="email" class="input" required maxlength="120" value="{{ old('email') }}">
                    </div>
                    <div>
                        <label class="label" for="new_role">Role</label>
                        <select id="new_role" name="role" class="select">
                            @foreach ($roles as $role => $label)
                                <option value="{{ $role }}" @selected(old('role') === $role)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="new_password">Password</label>
                        <input id="new_password" name="password" type="password" class="input" required minlength="8" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="label" for="new_password_confirmation">Ulangi password</label>
                        <input id="new_password_confirmation" name="password_confirmation" type="password" class="input" required minlength="8" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-full">
                        <x-icon name="plus" class="h-4 w-4" /> Tambah pengguna
                    </button>
                </form>
            </div>

            <div class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-[11px] leading-relaxed text-slate-500">
                <span class="font-medium text-slate-700">Catatan.</span>
                Akun yang dinonaktifkan tidak dapat login, tetapi tetap tersimpan. Minimal satu administrator aktif harus ada.
            </div>
        </div>

        <div class="space-y-4 xl:col-span-2">
            @foreach ($users as $user)
                <div class="card card-pad">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white" style="background: {{ $user->is_active ? brand_color() : '#94a3b8' }}">
                                {{ app_initials($user->name) }}
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                    @if ($user->id === auth()->id())
                                        <span class="badge badge-slate">Anda</span>
                                    @endif
                                </div>
                                <p class="truncate text-[11px] text-slate-500">{{ $user->email }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <span class="badge badge-brand">{{ $user->roleLabel() }}</span>
                                    <span class="badge {{ $user->is_active ? 'badge-green' : 'badge-red' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                    <span class="badge badge-slate">{{ $user->evaluations_count }} evaluasi</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <details class="group relative">
                                <summary class="btn btn-secondary btn-xs cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                                    <x-icon name="edit" class="h-3 w-3" /> Edit
                                </summary>
                                <div class="absolute right-4 z-30 mt-2 w-[min(22rem,90vw)] rounded-xl border border-slate-200 bg-white p-4 shadow-xl">
                                    <form method="POST" action="{{ route('permissions.users.update', $user) }}" class="space-y-3">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="label">Nama</label>
                                            <input name="name" class="input" required maxlength="100" value="{{ $user->name }}">
                                        </div>
                                        <div>
                                            <label class="label">Email</label>
                                            <input name="email" type="email" class="input" required maxlength="120" value="{{ $user->email }}">
                                        </div>
                                        <div>
                                            <label class="label">Role</label>
                                            <select name="role" class="select">
                                                @foreach ($roles as $role => $label)
                                                    <option value="{{ $role }}" @selected($user->role === $role)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="label">Password baru</label>
                                            <input name="password" type="password" class="input" minlength="8" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah">
                                        </div>
                                        <div>
                                            <label class="label">Ulangi password</label>
                                            <input name="password_confirmation" type="password" class="input" minlength="8" autocomplete="new-password">
                                        </div>
                                        <label class="flex items-center gap-2 text-[11px] text-slate-600">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" class="checkbox" @checked($user->is_active)>
                                            Akun aktif
                                        </label>
                                        <button type="submit" class="btn btn-primary btn-sm w-full">Simpan perubahan</button>
                                    </form>
                                </div>
                            </details>

                            @if ($user->id !== auth()->id())
                                <form method="POST" action="{{ route('permissions.users.destroy', $user) }}"
                                      data-confirm="Hapus pengguna {{ $user->name }}? Tindakan ini permanen.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn text-rose-500 hover:bg-rose-50 hover:text-rose-600" title="Hapus pengguna">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($users->isEmpty())
                <div class="card p-8 text-center text-xs text-slate-400">Belum ada pengguna.</div>
            @endif
        </div>
    </div>

    <div class="mt-5 flex justify-end">
        <p class="text-[10px] text-slate-400">Perubahan hak akses dan pengguna tercatat di Riwayat Aktivitas.</p>
    </div>
@endsection
