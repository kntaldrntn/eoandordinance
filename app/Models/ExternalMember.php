<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'position',
        'organization',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Scope to easily grab only active external members for your dropdowns later
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}