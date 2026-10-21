@php($user = auth()->user())
<!DOCTYPE html>
<html lang="id" style="--brand: {{ brand_color() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses ditolak · {{ setting('app_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-5">
    <div class="w-full max-w-md text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
            <x-icon name="lock" class="h-6 w-6" />
        </span>
        <p class="mt-5 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">403 · Akses ditolak</p>
        <h1 class="mt-2 text-xl font-semibold text-slate-900">Anda tidak memiliki hak akses</h1>
        <p class="mt-2 text-xs leading-relaxed text-slate-500">
            Halaman atau aksi ini dibatasi untuk role tertentu. Hubungi administrator jika Anda membutuhkan akses.
            @if ($user)
                Role Anda saat ini: <span class="font-medium text-slate-700">{{ $user->roleLabel() }}</span>.
            @endif
        </p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <x-icon name="home" class="h-4 w-4" /> Kembali ke dashboard
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Keluar</button>
            </form>
        </div>
    </div>
</body>
</html>
