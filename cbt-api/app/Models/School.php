<?php

namespace App\Models;

use App\Enums\SchoolType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'npsn',
        'type',
        'address',
        'principal_name',
        'phone',
        'email',
        'letterhead_path',
    ];

    protected function casts(): array
    {
        return [
            'type' => SchoolType::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
