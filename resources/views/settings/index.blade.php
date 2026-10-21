@extends('layouts.app')

@section('title', 'Pengaturan Aplikasi')
@section('heading', 'Pengaturan Aplikasi')
@section('subheading', 'Identitas, preferensi evaluasi, dan integrasi layanan AI')

@section('content')
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="grid gap-5 lg:grid-cols-3">
        @csrf
        @method('PUT')

        <div class="space-y-5 lg:col-span-2">
            <div class="card card-pad">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><x-icon name="tag" class="h-3.5 w-3.5" /></span>
                    <div>
                        <h3 class="section-title">Identitas aplikasi</h3>
                        <p class="muted">Nama dan logo yang tampil di seluruh halaman</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="app_name">Nama aplikasi</label>
                        <input id="app_name" name="app_name" class="input" required maxlength="60" value="{{ old('app_name', $settings['app_name']) }}">
                        @error('app_name')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="company_name">Nama perusahaan</label>
                        <input id="company_name" name="company_name" class="input" maxlength="120" value="{{ old('company_name', $settings['company_name']) }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="app_tagline">Tagline / subjudul</label>
                        <input id="app_tagline" name="app_tagline" class="input" maxlength="120" value="{{ old('app_tagline', $settings['app_tagline']) }}">
                    </div>
                    <div>
                        <label class="label" for="brand_color">Warna brand</label>
                        <div class="flex items-center gap-2">
                            <input id="brand_color" name="brand_color" type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-200 bg-white p-1" value="{{ old('brand_color', $settings['brand_color']) }}">
                            <input class="input font-mono text-xs" value="{{ old('brand_color', $settings['brand_color']) }}" readonly>
                        </div>
                        <p class="hint">Dipakai untuk tombol, grafik, dan aksen antarmuka.</p>
                        @error('brand_color')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <span class="label">Logo aplikasi</span>
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                @if (app_logo_url())
                                    <img src="{{ app_logo_url() }}" alt="Logo" data-logo-preview class="h-full w-full object-contain p-1">
                                @else
                                    <img alt="Pratinjau logo" data-logo-preview class="hidden h-full w-full object-contain p-1">
                                    <span class="text-xs font-bold text-slate-400">{{ app_initials($settings['app_name']) }}</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-[11px] text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-[11px] file:font-medium file:text-slate-700 hover:file:bg-slate-200" data-logo-input>
                                <p class="hint">PNG/JPG/WEBP, maks 2 MB.</p>
                            </div>
                        </div>
                        @if ($settings['logo'])
                            <label class="mt-2 flex items-center gap-2 text-[11px] text-slate-600">
                                <input type="hidden" name="remove_logo" value="0">
                                <input type="checkbox" name="remove_logo" value="1" class="checkbox"> Hapus logo saat ini
                            </label>
                        @endif
                        @error('logo')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><x-icon name="sliders" class="h-3.5 w-3.5" /></span>
                    <div>
                        <h3 class="section-title">Preferensi evaluasi & laporan</h3>
                        <p class="muted">Nilai awal saat membuat evaluasi dan catatan kaki laporan</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="default_method">Metode default</label>
                        <select id="default_method" name="default_method" class="select">
                            <option value="saw" @selected(old('default_method', $settings['default_method']) === 'saw')>SAW — Simple Additive Weighting</option>
                            <option value="topsis" @selected(old('default_method', $settings['default_method']) === 'topsis')>TOPSIS</option>
                        </select>
                        <p class="hint">Masih bisa diganti pada setiap evaluasi.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="report_footer">Catatan kaki laporan</label>
                        <textarea id="report_footer" name="report_footer" rows="2" maxlength="255" class="textarea">{{ old('report_footer', $settings['report_footer']) }}</textarea>
                        <p class="hint">Tampil di bagian bawah laporan PDF, Excel, dan cetak.</p>
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-icon name="sparkles" class="h-3.5 w-3.5" /></span>
                        <div>
                            <h3 class="section-title">Integrasi AI (opsional)</h3>
                            <p class="muted">Untuk narasi eksekutif; penjelasan deterministik tetap berjalan tanpa AI</p>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-[11px] font-medium text-slate-600">
                        <input type="hidden" name="ai_enabled" value="0">
                        <input type="checkbox" name="ai_enabled" value="1" class="checkbox" @checked(old('ai_enabled', $settings['ai_enabled']) === '1')>
                        Aktifkan
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="ai_provider">Penyedia</label>
                        <select id="ai_provider" name="ai_provider" class="select">
                            @foreach (['openai' => 'OpenAI', 'openrouter' => 'OpenRouter', 'groq' => 'Groq', 'custom' => 'OpenAI-compatible (custom)'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('ai_provider', $settings['ai_provider']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="ai_model">Model</label>
                        <input id="ai_model" name="ai_model" class="input" maxlength="80" value="{{ old('ai_model', $settings['ai_model']) }}" placeholder="gpt-4o-mini">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="ai_base_url">Base URL API</label>
                        <input id="ai_base_url" name="ai_base_url" type="url" class="input font-mono text-xs" maxlength="200" value="{{ old('ai_base_url', $settings['ai_base_url']) }}" placeholder="https://api.openai.com/v1">
                        <p class="hint">Harus kompatibel dengan endpoint <code>/chat/completions</code>.</p>
                        @error('ai_base_url')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="ai_api_key">API key {{ $hasApiKey ? '(tersimpan)' : '' }}</label>
                        <input id="ai_api_key" name="ai_api_key" type="password" class="input font-mono text-xs" maxlength="300" autocomplete="new-password"
                               value="{{ old('ai_api_key') }}"
                               placeholder="{{ $hasApiKey ? 'Kosongkan untuk mempertahankan key yang tersimpan' : 'sk-...' }}">
                        <p class="hint">Disimpan terenkripsi di database dan tidak pernah ditampilkan kembali.</p>
                        @if ($hasApiKey)
                            <label class="mt-2 flex items-center gap-2 text-[11px] text-slate-600">
                                <input type="hidden" name="clear_ai_key" value="0">
                                <input type="checkbox" name="clear_ai_key" value="1" class="checkbox"> Hapus API key tersimpan
                            </label>
                        @endif
                        @error('ai_api_key')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-test-ai data-url="{{ route('settings.test-ai') }}">
                        <x-icon name="refresh" class="h-3.5 w-3.5" /> Tes koneksi AI
                    </button>
                    <span class="text-[10px] text-slate-400">Menggunakan konfigurasi yang sudah tersimpan.</span>
                </div>
                <div data-test-ai-result class="mt-3 hidden rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600"></div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">
                    <x-icon name="save" class="h-4 w-4" /> Simpan pengaturan
                </button>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card card-pad lg:sticky lg:top-24">
                <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Pratinjau identitas</p>
                <div class="sidebar mt-3 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        @if (app_logo_url())
                            <img src="{{ app_logo_url() }}" alt="Logo" class="h-10 w-10 rounded-lg bg-white object-contain p-1">
                        @else
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg text-xs font-bold text-white" style="background: {{ $settings['brand_color'] }}">
                                {{ app_initials($settings['app_name']) }}
                            </span>
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">{{ $settings['app_name'] }}</p>
                            <p class="truncate text-[10px] text-slate-400">{{ $settings['app_tagline'] }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <span class="rounded-lg px-2.5 py-1 text-[10px] font-semibold text-white" style="background: {{ $settings['brand_color'] }}">Tombol utama</span>
                        <span class="rounded-lg bg-white/10 px-2.5 py-1 text-[10px] text-white/80">Sekunder</span>
                    </div>
                </div>
                <p class="mt-3 text-[10px] leading-relaxed text-slate-400">
                    Perubahan warna dan nama akan terlihat setelah pengaturan disimpan.
                </p>
            </div>

            <div class="card">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="section-title">Informasi sistem</h3>
                </div>
                <dl class="space-y-2.5 px-5 py-4 text-[11px]">
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-slate-500">Framework</dt>
                        <dd class="font-medium text-slate-700">Laravel {{ app()->version() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-slate-500">PHP</dt>
                        <dd class="font-medium text-slate-700">{{ PHP_VERSION }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-slate-500">Database</dt>
                        <dd class="font-medium text-slate-700">{{ config('database.default') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-slate-500">Logo tersimpan</dt>
                        <dd class="font-medium text-slate-700">{{ $settings['logo'] ? 'Ya' : 'Tidak' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-slate-500">API key</dt>
                        <dd class="font-medium text-slate-700">{{ $hasApiKey ? 'Tersimpan' : 'Belum ada' }}</dd>
                    </div>
                </dl>
                <div class="border-t border-slate-100 px-5 py-3 text-[10px] leading-relaxed text-slate-400">
                    Perubahan pengaturan tercatat pada Riwayat Aktivitas dan dapat di-revert.
                </div>
            </div>

            <div class="card card-pad">
                <h3 class="section-title">Pintasan</h3>
                <div class="mt-3 space-y-2">
                    <a href="{{ route('permissions.index') }}" class="btn btn-secondary btn-sm w-full justify-start">
                        <x-icon name="shield" class="h-3.5 w-3.5" /> Hak akses & pengguna
                    </a>
                    <a href="{{ route('database.index') }}" class="btn btn-secondary btn-sm w-full justify-start">
                        <x-icon name="database" class="h-3.5 w-3.5" /> Backup & impor database
                    </a>
                    <a href="{{ route('activity.index') }}" class="btn btn-secondary btn-sm w-full justify-start">
                        <x-icon name="history" class="h-3.5 w-3.5" /> Riwayat aktivitas
                    </a>
                </div>
            </div>
        </div>
    </form>
@endsection
