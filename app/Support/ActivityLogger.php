<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public static function log(
        string $action,
        ?string $modelType = null,
        ?int $modelId = null,
        ?string $label = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
    ): ActivityLog {
        $user = Auth::user();

        return ActivityLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistem',
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'label' => $label,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }

    public static function snapshot(Model $model, array $except = []): array
    {
        $except = array_merge(['created_at', 'updated_at', 'deleted_at'], $except);

        return collect($model->getAttributes())
            ->except($except)
            ->all();
    }

    public static function modelClass(string $type): ?string
    {
        return match ($type) {
            'supplier' => \App\Models\Supplier::class,
            'criterion' => \App\Models\Criterion::class,
            'evaluation' => \App\Models\Evaluation::class,
            'user' => \App\Models\User::class,
            default => null,
        };
    }
}
