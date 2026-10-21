@php($appName = setting('app_name', 'SuppliRank'))
<!DOCTYPE html>
<html lang="id" style="--brand: {{ brand_color() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ $appName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950">
<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
    <section class="relative hidden flex-col justify-between overflow-hidden p-12 lg:flex" style="background: linear-gradient(150deg, #0f172a 0%, color-mix(in srgb, {{ brand_color() }} 55%, #0f172a) 60%, {{ brand_color() }} 130%)">
        <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-black/20 blur-2xl"></div>

        <div class="relative flex items-center gap-3">
            @if (app_logo_url())
                <img src="{{ app_logo_url() }}" alt="{{ $appName }}" class="h-11 w-11 rounded-xl bg-white object-contain p-1.5">
            @else
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 text-sm font-bold text-white backdrop-blur">
                    {{ app_initials($appName) }}
                </span>
            @endif
            <div>
                <p class="text-sm font-semibold text-white">{{ $appName }}</p>
                <p class="text-[11px] text-white/60">{{ setting('company_name') }}</p>
            </div>
        </div>

        <div class="relative max-w-lg">
            <span class="badge bg-white/10 text-white/80">Multi-Criteria Decision Making</span>
            <h1 class="mt-5 text-4xl font-semibold leading-tight text-white">
                Pilih supplier terbaik,<br>pahami alasannya.
            </h1>
            <p class="mt-4 text-sm leading-relaxed text-white/70">
                Bandingkan supplier dengan metode SAW atau TOPSIS, lalu lihat kontribusi tiap kriteria
                yang membuat sebuah supplier direkomendasikan. Bukan sekadar daftar harga termurah.
            </p>

            <div class="mt-8 grid gap-3 text-xs text-white/75">
                <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                    <x-icon name="sliders" class="h-4 w-4 text-white/70" />
                    Kriteria dan bobot penilaian yang bisa disesuaikan kebutuhan pengadaan.
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                    <x-icon name="sparkles" class="h-4 w-4 text-white/70" />
                    Penjelasan kontribusi, kontrafaktual, dan sensitivitas bobot untuk setiap rekomendasi.
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                    <x-icon name="history" class="h-4 w-4 text-white/70" />
                    Riwayat aktivitas yang dapat di-revert atau dipulihkan kembali.
                </div>
            </div>
        </div>

        <p class="relative text-[10px] text-white/40">
            {{ $appName }} · {{ now()->year }} · Sistem Informasi Rekomendasi Supplier
        </p>
    </section>

    <section class="flex items-center justify-center bg-slate-50 px-5 py-10 sm:px-10">
        <div class="w-full max-w-md">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                @if (app_logo_url())
                    <img src="{{ app_logo_url() }}" alt="{{ $appName }}" class="h-10 w-10 rounded-lg object-contain">
                @else
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold text-white" style="background: {{ brand_color() }}">
                        {{ app_initials($appName) }}
                    </span>
                @endif
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ $appName }}</p>
                    <p class="text-[11px] text-slate-500">{{ setting('company_name') }}</p>
                </div>
            </div>

            <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Masuk ke akun Anda</h2>
            <p class="mt-1.5 text-sm text-slate-500">Gunakan email dan password yang diberikan administrator.</p>

            @if ($errors->any())
                <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input" placeholder="nama@perusahaan.com">
                </div>
                <div>
                    <label class="label" for="password">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="input" placeholder="••••••••">
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="checkbox">
                    Ingat saya di perangkat ini
                </label>
                <button type="submit" class="btn btn-primary w-full">Masuk ke dashboard</button>
            </form>

            <div class="mt-7 rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold text-slate-700">Akun demo</p>
                <div class="mt-3 space-y-2 text-[11px] text-slate-500">
                    <div class="flex items-center justify-between gap-2">
                        <span><span class="font-medium text-slate-700">Admin</span> · kai@example.com</span>
                        <span class="badge badge-brand">250510</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span><span class="font-medium text-slate-700">Analis</span> · chips@example.com</span>
                        <span class="badge badge-slate">250510</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span><span class="font-medium text-slate-700">Viewer</span> · tweek@example.com</span>
                        <span class="badge badge-slate">250510</span>
                    </div>
                </div>
            </div>

            <p class="mt-6 text-center text-[10px] text-slate-400">
                Dengan masuk, aktivitas Anda tercatat pada riwayat sistem untuk audit.
            </p>
        </div>
    </section>
</div>
</body>
</html>
