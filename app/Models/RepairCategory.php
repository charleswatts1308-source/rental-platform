<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairCategory extends Model
{
    /** @use HasFactory<\Database\Factories\RepairCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'description',
        'sort_order',
        'active',
        'requires_description',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
            'requires_description' => 'boolean',
        ];
    }

    public function cases(): HasMany
    {
        return $this->hasMany(RepairCase::class, 'category_key', 'key');
    }
}
