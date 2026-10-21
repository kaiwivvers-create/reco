<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\RolePermission;
use App\Models\Supplier;
use App\Support\ActivityLogger;
use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('type'), fn ($q) => $q->where('model_type', $request->string('type')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($inner) => $inner->where('label', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('user_name', 'like', $term));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $actions = [];
        $diffs = [];
        $states = [];
        $restorable = [];
        $revertible = [];

        foreach ($logs as $log) {
            $class = $log->model_type ? ActivityLogger::modelClass($log->model_type) : null;
            $model = $this->findModel($class, $log->model_id);

            $isTrashed = $model && method_exists($model, 'trashed') && $model->trashed();
            $canRevertSpecial = in_array($log->model_type, ['setting', 'permission'], true)
                && $log->action === 'updated'
                && $log->old_values
                && ! $log->is_reverted;

            $actions[$log->id] = [];
            if ($canRevertSpecial || ($log->action === 'updated' && $log->old_values && $model && ! $isTrashed && ! $log->is_reverted)) {
                $actions[$log->id][] = 'revert';
            }
            if ($log->action === 'deleted' && $isTrashed) {
                $actions[$log->id][] = 'restore';
                $actions[$log->id][] = 'force_delete';
            }
            if (in_array('restore', $actions[$log->id], true)) {
                $restorable[$log->id] = true;
            }
            if (in_array('revert', $actions[$log->id], true)) {
                $revertible[$log->id] = true;
            }

            $diffs[$log->id] = $this->diff($log);

            $states[$log->id] = match (true) {
                $log->is_reverted => 'reverted',
                $log->action === 'deleted' && ! $model => 'force_deleted',
                $log->action === 'deleted' && $isTrashed => 'trashed',
                $log->action === 'deleted' => 'restored',
                $log->action === 'restored' => 'restored',
                $log->action === 'force_deleted' => 'force_deleted',
                default => null,
            };
        }

        $stats = [
            'total' => ActivityLog::count(),
            'today' => ActivityLog::whereDate('created_at', today())->count(),
            'deleted' => ActivityLog::where('action', 'deleted')->count(),
            'reverted' => ActivityLog::where('action', 'reverted')->count(),
        ];

        $types = ActivityLog::select('model_type')->distinct()->pluck('model_type')->filter()->values();

        return view('activity.index', compact('logs', 'actions', 'diffs', 'states', 'stats', 'types', 'restorable', 'revertible'));
    }

    public function revert(ActivityLog $activity)
    {
        if ($activity->action !== 'updated' || ! $activity->old_values) {
            return back()->with('error', 'Hanya riwayat perubahan data yang dapat dikembalikan.');
        }

        if ($activity->model_type === 'setting') {
            return $this->revertSettings($activity);
        }

        if ($activity->model_type === 'permission') {
            return $this->revertPermissions($activity);
        }

        $class = ActivityLogger::modelClass((string) $activity->model_type);
        if (! $class) {
            return back()->with('error', 'Jenis data ini tidak mendukung revert.');
        }

        $model = $this->findModel($class, $activity->model_id);
        if (! $model) {
            return back()->with('error', 'Data asal sudah tidak tersedia, revert tidak dapat dilakukan.');
        }
        if (method_exists($model, 'trashed') && $model->trashed()) {
            return back()->with('error', 'Data sedang berstatus terhapus. Pulihkan datanya terlebih dahulu sebelum melakukan revert.');
        }

        $old = $activity->old_values;
        $payload = collect($old)->only($model->getFillable())->except(['password'])->all();

        try {
            DB::transaction(function () use ($model, $payload, $old) {
                $model->update($payload);

                if ($model instanceof Supplier && isset($old['scores']) && is_array($old['scores'])) {
                    foreach ($old['scores'] as $criterionId => $value) {
                        $model->scores()->updateOrCreate(
                            ['criterion_id' => (int) $criterionId],
                            ['value' => (float) $value]
                        );
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Revert gagal: '.$e->getMessage());
        }

        $new = ActivityLogger::snapshot($model);
        $activity->update(['is_reverted' => true]);

        ActivityLogger::log(
            'reverted',
            $activity->model_type,
            $activity->model_id,
            $activity->label,
            $old,
            $new,
            'Revert perubahan dari riwayat #'.$activity->id
        );

        return back()->with('success', 'Perubahan berhasil dikembalikan ke kondisi sebelumnya.');
    }

    public function restore(ActivityLog $activity)
    {
        if ($activity->action !== 'deleted') {
            return back()->with('error', 'Hanya data yang dihapus yang dapat dipulihkan.');
        }

        $class = ActivityLogger::modelClass((string) $activity->model_type);
        if (! $class) {
            return back()->with('error', 'Jenis data ini tidak mendukung pemulihan.');
        }

        $model = $this->findModel($class, $activity->model_id);
        if (! $model || ! (method_exists($model, 'trashed') && $model->trashed())) {
            return back()->with('error', 'Data tidak ditemukan atau sudah dipulihkan.');
        }

        $model->restore();

        ActivityLogger::log(
            'restored',
            $activity->model_type,
            $activity->model_id,
            $activity->label,
            null,
            ActivityLogger::snapshot($model),
            'Data dipulihkan dari Riwayat Aktivitas'
        );

        return back()->with('success', 'Data "'.$activity->label.'" berhasil dipulihkan.');
    }

    public function forceDelete(ActivityLog $activity)
    {
        if ($activity->action !== 'deleted') {
            return back()->with('error', 'Hanya data yang dihapus yang dapat dihapus permanen.');
        }

        $class = ActivityLogger::modelClass((string) $activity->model_type);
        if (! $class) {
            return back()->with('error', 'Jenis data ini tidak mendukung hapus permanen.');
        }

        $model = $this->findModel($class, $activity->model_id);
        if (! $model || ! (method_exists($model, 'trashed') && $model->trashed())) {
            return back()->with('error', 'Data tidak ditemukan atau sudah dihapus permanen.');
        }

        $snapshot = ActivityLogger::snapshot($model);
        if ($model instanceof Supplier) {
            $snapshot['scores'] = $model->scores()->pluck('value', 'criterion_id')
                ->map(fn ($value) => (float) $value)->all();
        }

        $label = $activity->label;
        $model->forceDelete();

        ActivityLogger::log(
            'force_deleted',
            $activity->model_type,
            $activity->model_id,
            $label,
            $snapshot,
            null,
            'Data dihapus permanen. Salinan data tetap tercatat pada log ini.'
        );

        return back()->with('success', 'Data "'.$label.'" dihapus permanen dan tidak dapat dipulihkan.');
    }

    protected function revertSettings(ActivityLog $activity): \Illuminate\Http\RedirectResponse
    {
        $old = $activity->old_values ?? [];
        $before = $this->maskSettings(Settings::all());

        foreach (Settings::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $old)) {
                continue;
            }

            if ($key === 'ai_api_key') {
                continue;
            }

            if ($key === 'logo') {
                $logo = (string) $old[$key];
                Settings::set('logo', $logo !== '' && Storage::disk('public')->exists($logo) ? $logo : '');
                continue;
            }

            Settings::set($key, $old[$key]);
        }

        Settings::flush();
        $activity->update(['is_reverted' => true]);

        ActivityLogger::log(
            'reverted',
            'setting',
            null,
            'Pengaturan Aplikasi',
            $before,
            $this->maskSettings(Settings::all()),
            'Revert perubahan pengaturan dari riwayat #'.$activity->id
        );

        return back()->with('success', 'Pengaturan berhasil dikembalikan ke kondisi sebelumnya. API key tidak diubah karena bersifat rahasia.');
    }

    protected function revertPermissions(ActivityLog $activity): \Illuminate\Http\RedirectResponse
    {
        $old = $activity->old_values ?? [];
        $before = RolePermission::all()->mapWithKeys(fn ($row) => [
            $row->role.'.'.$row->module => ['view' => (bool) $row->can_view, 'manage' => (bool) $row->can_manage],
        ])->all();

        DB::transaction(function () use ($old) {
            foreach ($old as $key => $values) {
                if (! is_array($values) || ! str_contains($key, '.')) {
                    continue;
                }

                [$role, $module] = explode('.', $key, 2);
                $manage = (bool) ($values['manage'] ?? false);

                RolePermission::updateOrCreate(
                    ['role' => $role, 'module' => $module],
                    [
                        'can_view' => $manage || (bool) ($values['view'] ?? false),
                        'can_manage' => $manage,
                    ]
                );
            }
        });

        Permissions::flush();
        $activity->update(['is_reverted' => true]);

        $after = RolePermission::all()->mapWithKeys(fn ($row) => [
            $row->role.'.'.$row->module => ['view' => (bool) $row->can_view, 'manage' => (bool) $row->can_manage],
        ])->all();

        ActivityLogger::log(
            'reverted',
            'permission',
            null,
            'Matriks Hak Akses',
            $before,
            $after,
            'Revert perubahan hak akses dari riwayat #'.$activity->id
        );

        return back()->with('success', 'Hak akses berhasil dikembalikan ke kondisi sebelumnya.');
    }

    protected function maskSettings(array $settings): array
    {
        if (filled($settings['ai_api_key'] ?? null)) {
            $settings['ai_api_key'] = '***disembunyikan***';
        }

        return $settings;
    }

    protected function findModel(?string $class, ?int $id)
    {
        if (! $class || ! $id) {
            return null;
        }

        return in_array(SoftDeletes::class, class_uses_recursive($class), true)
            ? $class::withTrashed()->find($id)
            : $class::find($id);
    }

    protected function diff(ActivityLog $log): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $ignore = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'];
        $rows = [];

        foreach ($keys as $key) {
            if (in_array($key, $ignore, true)) {
                continue;
            }

            $before = $old[$key] ?? null;
            $after = array_key_exists($key, $new) ? $new[$key] : null;

            if ($before == $after) {
                continue;
            }

            $rows[] = [
                'field' => $key,
                'before' => $this->formatValue($key, $before),
                'after' => $this->formatValue($key, $after),
            ];
        }

        return $rows;
    }

    protected function formatValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($key === 'scores' && is_array($value)) {
            $parts = [];
            foreach ($value as $criterionId => $score) {
                $parts[] = 'Kriteria #'.$criterionId.': '.fmt_num((float) $score);
            }

            return implode(', ', $parts);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        return (string) $value;
    }
}
