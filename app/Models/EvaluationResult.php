<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationResult extends Model
{
    protected $fillable = [
        'evaluation_id',
        'supplier_id',
        'supplier_name',
        'rank',
        'score',
        'detail',
    ];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'score' => 'float',
            'rank' => 'integer',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
