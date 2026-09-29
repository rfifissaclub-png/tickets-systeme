<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'is_linked',
        'parent_tenant_id',
        'meta',
    ];

    protected $casts = [
        'is_linked' => 'boolean',
        'meta' => 'array',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }
}
