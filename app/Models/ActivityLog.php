<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'model_type',
        'model_id',
        'label',
        'old_values',
        'new_values',
        'description',
        'ip_address',
        'is_reverted',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'is_reverted' => 'boolean',
        ];
    }

    public const ACTION_LABELS = [
        'created' => 'Menambah data',
        'updated' => 'Mengubah data',
        'deleted' => 'Menghapus data',
        'restored' => 'Memulihkan data',
        'force_deleted' => 'Hapus permanen',
        'imported' => 'Impor data',
        'exported' => 'Ekspor data',
        'reverted' => 'Revert perubahan',
        'login' => 'Login',
    ];

    public const ACTION_COLORS = [
        'created' => 'emerald',
        'updated' => 'amber',
        'deleted' => 'rose',
        'restored' => 'sky',
        'force_deleted' => 'slate',
        'imported' => 'violet',
        'exported' => 'indigo',
        'reverted' => 'cyan',
        'login' => 'slate',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? ucfirst($this->action);
    }

    public function modelLabel(): string
    {
        return match ($this->model_type) {
            'supplier' => 'Supplier',
            'criterion' => 'Kriteria',
            'evaluation' => 'Evaluasi',
            'setting' => 'Pengaturan',
            'user' => 'Pengguna',
            'permission' => 'Hak Akses',
            'database' => 'Database',
            default => ucfirst((string) $this->model_type),
        };
    }
}
