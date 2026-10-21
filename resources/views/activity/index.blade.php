@extends('layouts.app')

@section('title', 'Riwayat Aktivitas')
@section('heading', 'Riwayat Aktivitas')
@section('subheading', 'Jejak perubahan data, revert, pemulihan, dan hapus permanen')

@section('content')
    @php
        $activityColors = [
            'emerald' => 'green', 'amber' => 'amber', 'rose' => 'red', 'sky' => 'blue',
            'slate' => 'slate', 'violet' => 'violet', 'indigo' => 'blue', 'cyan' => 'cyan',
        ];
        $actionIcons = [
            'created' => 'plus', 'updated' => 'edit', 'deleted' => 'trash', 'restored' => 'restore',
            'force_deleted' => 'trash', 'imported' => 'upload', 'exported' => 'download',
            'reverted' => 'refresh', 'login' => 'user',
        ];
    @endphp

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-pad">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Total aktivitas</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">{{ $stats['total'] }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Hari ini</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">{{ $stats['today'] }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Penghapusan</p>
            <p class="mt-1 text-xl font-semibold text-rose-600">{{ $stats['deleted'] }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Revert / pemulihan</p>
            <p class="mt-1 text-xl font-semibold text-emerald-600">{{ $stats['reverted'] }}</p>
        </div>
    </div>

    <div class="mt-5 card overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
            <form method="GET" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="relative flex-1 sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari data, pengguna, deskripsi…" class="input pl-9">
                </div>
                <select name="action" class="select sm:w-44">
                    <option value="">Semua aksi</option>
                    @foreach (\App\Models\ActivityLog::ACTION_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="type" class="select sm:w-40">
                    <option value="">Semua jenis data</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Terapkan</button>
                @if (request()->filled('q') || request()->filled('action') || request()->filled('type'))
                    <a href="{{ route('activity.index') }}" class="btn btn-ghost btn-sm">Reset</a>
                @endif
            </form>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($logs as $log)
                @php
                    $color = $activityColors[\App\Models\ActivityLog::ACTION_COLORS[$log->action] ?? 'slate'] ?? 'slate';
                    $state = $states[$log->id] ?? null;
                @endphp
                <div class="px-4 py-4 sm:px-5">
                    <div class="flex flex-wrap items-start gap-3">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-500">
                            <x-icon name="{{ $actionIcons[$log->action] ?? 'info' }}" class="h-4 w-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge badge-{{ $color }}">{{ $log->actionLabel() }}</span>
                                <span class="text-[13px] font-semibold text-slate-800">{{ $log->label ?? $log->modelLabel() }}</span>
                                <span class="text-[10px] text-slate-400">{{ $log->modelLabel() }}{{ $log->model_id ? ' #'.$log->model_id : '' }}</span>
                                @if ($state === 'reverted')
                                    <span class="badge badge-cyan">sudah di-revert</span>
                                @elseif ($state === 'restored')
                                    <span class="badge badge-green">sudah dipulihkan</span>
                                @elseif ($state === 'force_deleted')
                                    <span class="badge badge-slate">hapus permanen</span>
                                @elseif ($state === 'trashed')
                                    <span class="badge badge-red">terhapus</span>
                                @endif
                            </div>

                            @if ($log->description)
                                <p class="mt-1 text-[11px] leading-relaxed text-slate-500">{{ $log->description }}</p>
                            @endif

                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-slate-400">
                                <span class="inline-flex items-center gap-1"><x-icon name="user" class="h-3 w-3" /> {{ $log->user_name ?? 'Sistem' }}</span>
                                <span class="inline-flex items-center gap-1"><x-icon name="clock" class="h-3 w-3" /> {{ fmt_date($log->created_at) }}</span>
                                <span>{{ $log->created_at?->diffForHumans() }}</span>
                                @if ($log->ip_address)
                                    <span>IP {{ $log->ip_address }}</span>
                                @endif
                            </div>

                            @if (! empty($diffs[$log->id]))
                                <details class="mt-2">
                                    <summary class="inline-flex cursor-pointer items-center gap-1 text-[10px] font-medium text-[var(--brand)]">
                                        <x-icon name="eye" class="h-3 w-3" /> Lihat {{ count($diffs[$log->id]) }} data berubah
                                    </summary>
                                    <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200">
                                        <table class="table text-[10px]">
                                            <thead>
                                                <tr>
                                                    <th class="!py-2">Field</th>
                                                    <th class="!py-2">Sebelum</th>
                                                    <th class="!py-2">Sesudah</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($diffs[$log->id] as $diff)
                                                    <tr>
                                                        <td class="!py-2 font-medium text-slate-600">{{ $diff['field'] }}</td>
                                                        <td class="!py-2 break-all text-rose-600">{{ $diff['before'] }}</td>
                                                        <td class="!py-2 break-all text-emerald-700">{{ $diff['after'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            @endif
                        </div>

                        @if (can_manage('activity') && ! empty($actions[$log->id]))
                            <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                @if (in_array('revert', $actions[$log->id], true))
                                    <form method="POST" action="{{ route('activity.revert', $log) }}"
                                          data-confirm="Kembalikan data ke kondisi sebelum perubahan ini?">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-xs">
                                            <x-icon name="restore" class="h-3 w-3" /> Revert
                                        </button>
                                    </form>
                                @endif
                                @if (in_array('restore', $actions[$log->id], true))
                                    <form method="POST" action="{{ route('activity.restore', $log) }}"
                                          data-confirm="Pulihkan data {{ $log->label }}? Data akan aktif kembali.">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-xs">
                                            <x-icon name="restore" class="h-3 w-3" /> Pulihkan
                                        </button>
                                    </form>
                                @endif
                                @if (in_array('force_delete', $actions[$log->id], true))
                                    <form method="POST" action="{{ route('activity.force-delete', $log) }}"
                                          data-confirm="HAPUS PERMANEN {{ $log->label }}? Tindakan ini tidak dapat dibatalkan.">
                                        @csrf
                                        <button type="submit" class="btn btn-danger-soft btn-xs">
                                            <x-icon name="trash" class="h-3 w-3" /> Hapus permanen
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center px-5 py-14 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <x-icon name="history" class="h-5 w-5" />
                    </span>
                    <p class="mt-3 text-sm font-medium text-slate-700">Belum ada aktivitas</p>
                    <p class="mt-1 text-xs text-slate-500">Setiap perubahan data akan tercatat di sini.</p>
                </div>
            @endforelse
        </div>

        @if ($logs->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $logs->links() }}</div>
        @endif
    </div>

    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[11px] leading-relaxed text-amber-800">
        <span class="font-semibold">Tentang revert &amp; hapus permanen.</span>
        Revert mengembalikan data ke nilai sebelum perubahan. Hapus permanen hanya tersedia untuk data yang sudah
        dihapus dan tidak dapat dibatalkan. Salinan data terakhir tetap tercatat pada log, namun tidak dapat
        dipulihkan otomatis setelah dihapus permanen.
    </div>
@endsection
