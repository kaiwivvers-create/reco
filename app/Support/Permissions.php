<?php

namespace App\Support;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Auth;

class Permissions
{
    /**
     * Modules available in the sidebar, in display order.
     */
    public const MODULES = [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'home', 'group' => 'Utama', 'locked' => true],
        'suppliers' => ['label' => 'Data Supplier', 'icon' => 'building', 'group' => 'Data Master'],
        'criteria' => ['label' => 'Kriteria & Bobot', 'icon' => 'sliders', 'group' => 'Data Master'],
        'evaluations' => ['label' => 'Evaluasi & Ranking', 'icon' => 'chart', 'group' => 'Analisis'],
        'reports' => ['label' => 'Laporan', 'icon' => 'file', 'group' => 'Analisis'],
        'activity' => ['label' => 'Riwayat Aktivitas', 'icon' => 'history', 'group' => 'Sistem'],
        'settings' => ['label' => 'Pengaturan Aplikasi', 'icon' => 'cog', 'group' => 'Sistem'],
        'permissions' => ['label' => 'Hak Akses & Pengguna', 'icon' => 'shield', 'group' => 'Sistem'],
        'database' => ['label' => 'Database', 'icon' => 'database', 'group' => 'Sistem'],
    ];

    protected static ?array $cache = null;

    public static function matrix(): array
    {
        if (static::$cache === null) {
            static::$cache = RolePermission::query()
                ->get()
                ->mapWithKeys(fn ($row) => [$row->role.'.'.$row->module => [
                    'view' => (bool) $row->can_view,
                    'manage' => (bool) $row->can_manage,
                ]])
                ->all();
        }

        return static::$cache;
    }

    public static function flush(): void
    {
        static::$cache = null;
    }

    public static function allows(?string $role, string $module, string $ability = 'view'): bool
    {
        if (! $role) {
            return false;
        }

        if ($role === 'admin') {
            return true;
        }

        if ($module === 'dashboard') {
            return true;
        }

        $entry = static::matrix()[$role.'.'.$module] ?? null;

        if (! $entry) {
            return false;
        }

        return $ability === 'manage' ? $entry['manage'] : $entry['view'];
    }

    public static function current(string $module, string $ability = 'view'): bool
    {
        return static::allows(Auth::user()?->role, $module, $ability);
    }

    public static function viewableModules(?string $role): array
    {
        return collect(array_keys(self::MODULES))
            ->filter(fn ($module) => static::allows($role, $module))
            ->values()
            ->all();
    }
}
