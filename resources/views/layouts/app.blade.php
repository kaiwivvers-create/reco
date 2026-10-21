@php
    $appName = setting('app_name', 'SuppliRank');
    $logo = app_logo_url();
    $navGroups = collect(\App\Support\Permissions::MODULES)
        ->map(fn ($meta, $module) => array_merge($meta, ['module' => $module]))
        ->filter(fn ($meta) => can_view($meta['module']))
        ->groupBy('group');
    $routeFor = fn ($module) => $module === 'dashboard' ? 'dashboard' : $module.'.index';
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="id" style="--brand: {{ brand_color() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ $appName }}</title>
    @if ($logo)
        <link rel="icon" href="{{ $logo }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<div>
    <aside id="sidebar" class="sidebar fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $appName }}" class="h-9 w-9 rounded-lg bg-white object-contain p-1">
                @else
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-white" style="background: {{ brand_color() }}">
                        {{ app_initials($appName) }}
                    </span>
                @endif
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-white">{{ $appName }}</span>
                    <span class="block truncate text-[10px] text-slate-400">MCDM · Explainable AI</span>
                </span>
            </a>
            <button type="button" class="ml-auto text-slate-400 hover:text-white lg:hidden" data-sidebar-toggle aria-label="Tutup menu">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </div>

        <nav class="scrollbar-thin min-h-0 flex-1 space-y-5 overflow-y-auto px-3 py-5">
            @foreach ($navGroups as $group => $items)
                <div>
                    <div class="nav-group-label">{{ $group }}</div>
                    <div class="space-y-1">
                        @foreach ($items as $item)
                            @php($route = $routeFor($item['module']))
                            <a href="{{ route($route) }}"
                               class="nav-link {{ request()->routeIs($item['module'] === 'dashboard' ? 'dashboard' : $item['module'].'*') ? 'active' : '' }}">
                                <x-icon :name="$item['icon']" />
                                <span>{{ $item['label'] }}</span>
                                @if ($item['module'] === 'evaluations')
                                    <x-icon name="arrow-right" class="ml-auto !h-3.5 !w-3.5" />
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="shrink-0 border-t border-white/10 p-4">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-white">
                    {{ app_initials($user?->name ?? 'User') }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold text-white">{{ $user?->name }}</p>
                    <p class="truncate text-[10px] text-slate-400">{{ $user?->roleLabel() }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-400 transition hover:text-white" title="Keluar">
                        <x-icon name="logout" class="h-4 w-4" />
                    </button>
                </form>
            </div>
            <p class="mt-3 text-[10px] leading-relaxed text-slate-500">
                {{ setting('company_name') }}
            </p>
        </div>
    </aside>

    <div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"></div>

    <div class="lg:pl-72">
        <header class="no-print sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-[1440px] items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button type="button" class="icon-btn lg:hidden" data-sidebar-toggle aria-label="Buka menu">
                    <x-icon name="menu" class="h-5 w-5" />
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-sm font-semibold text-slate-900 sm:text-base">@yield('heading', 'Dashboard')</h1>
                    <p class="hidden truncate text-[11px] text-slate-500 sm:block">@yield('subheading', setting('app_tagline'))</p>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    @yield('actions')
                    <details class="group relative">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-slate-200 bg-white py-1 pl-1 pr-2 transition hover:border-slate-300 [&::-webkit-details-marker]:hidden">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-bold text-white" style="background: {{ brand_color() }}">
                                {{ app_initials($user?->name ?? 'User') }}
                            </span>
                            <span class="hidden text-left sm:block">
                                <span class="block text-[11px] font-semibold leading-tight text-slate-800">{{ \Illuminate\Support\Str::limit($user?->name, 18) }}</span>
                                <span class="block text-[9px] leading-tight text-slate-500">{{ $user?->roleLabel() }}</span>
                            </span>
                            <x-icon name="chevron-down" class="h-3.5 w-3.5 text-slate-400" />
                        </summary>
                        <div class="absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="text-xs font-semibold text-slate-800">{{ $user?->name }}</p>
                                <p class="truncate text-[11px] text-slate-500">{{ $user?->email }}</p>
                                <span class="badge badge-brand mt-2">{{ $user?->roleLabel() }}</span>
                            </div>
                            @if (can_manage('settings'))
                                <a href="{{ route('settings.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs text-slate-600 hover:bg-slate-50">
                                    <x-icon name="cog" class="h-4 w-4" /> Pengaturan aplikasi
                                </a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-xs text-rose-600 hover:bg-rose-50">
                                    <x-icon name="logout" class="h-4 w-4" /> Keluar
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div data-flash class="fade-in mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <x-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                    <div class="flex-1">{{ session('success') }}</div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600/70 hover:text-emerald-800"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
            @endif

            @if (session('warning'))
                <div data-flash class="fade-in mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                    <div class="flex-1">{{ session('warning') }}</div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-amber-600/70 hover:text-amber-800"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
            @endif

            @if (session('error'))
                <div data-flash class="fade-in mb-5 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                    <div class="flex-1">{{ session('error') }}</div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-rose-600/70 hover:text-rose-800"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="fade-in mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <div class="flex items-start gap-3">
                        <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                        <div>
                            <p class="font-semibold">Ada data yang perlu diperiksa:</p>
                            <ul class="mt-1 list-inside list-disc space-y-0.5 text-[13px]">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            @yield('content')

            <footer class="mt-10 flex flex-col items-center justify-between gap-2 border-t border-slate-200/70 py-4 text-[11px] text-slate-400 sm:flex-row">
                <span>{{ $appName }} · {{ setting('company_name') }}</span>
                <span>MCDM (SAW/TOPSIS) &amp; Explainable AI · {{ now()->year }}</span>
            </footer>
        </main>
    </div>
</div>
</body>
</html>
