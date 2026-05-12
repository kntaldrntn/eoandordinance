<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'name',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function executiveOrders()
    {
        return $this->belongsToMany(ExecutiveOrder::class, 'eo_department')
                    ->withPivot('role') // Important: Lets us grab the 'lead' vs 'support' info
                    ->withTimestamps();
    }
    public function ordinances()
    {
        // Explicitly using your custom pivot table name!
        return $this->belongsToMany(Ordinance::class, 'ordinance_department')->withPivot('role');
    }
}
