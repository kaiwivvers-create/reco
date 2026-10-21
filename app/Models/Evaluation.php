<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evaluation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'method',
        'notes',
        'criteria_snapshot',
        'supplier_ids',
        'explanation',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'criteria_snapshot' => 'array',
            'supplier_ids' => 'array',
            'explanation' => 'array',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(EvaluationResult::class)->orderBy('rank');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function methodLabel(): string
    {
        return strtoupper($this->method);
    }

    public function winner(): ?EvaluationResult
    {
        return $this->results->firstWhere('rank', 1);
    }
}
