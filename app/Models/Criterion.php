<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Criterion extends Model
{
    use SoftDeletes;

    protected $table = 'criteria';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'weight',
        'unit',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scores(): HasMany
    {
        return $this->hasMany(SupplierScore::class, 'criterion_id');
    }

    public function isCost(): bool
    {
        return $this->type === 'cost';
    }

    public function typeLabel(): string
    {
        return $this->isCost() ? 'Cost (semakin kecil semakin baik)' : 'Benefit (semakin besar semakin baik)';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public static function nextCode(): string
    {
        $last = static::withTrashed()->orderByDesc('id')->first();
        $number = $last ? ((int) preg_replace('/\D/', '', $last->code) + 1) : 1;

        return 'C'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }
}
