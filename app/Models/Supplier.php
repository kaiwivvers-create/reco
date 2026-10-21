<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SupplierScore::class);
    }

    public function scoreFor(int $criterionId): ?float
    {
        $score = $this->scores->firstWhere('criterion_id', $criterionId);

        return $score ? (float) $score->value : null;
    }

    public static function nextCode(): string
    {
        $last = static::withTrashed()->orderByDesc('id')->first();
        $number = $last ? ((int) preg_replace('/\D/', '', $last->code) + 1) : 1;

        return 'SUP-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
