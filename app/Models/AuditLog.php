<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = [];

    // Cast JSON columns back to arrays automatically
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // Connects to Parent (EO or Ordinance)
    public function auditable()
    {
        return $this->morphTo();
    }
}