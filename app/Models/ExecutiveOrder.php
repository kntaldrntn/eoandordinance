<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExecutiveOrder extends Model
{
    protected $guarded = [];

    // Link to Status
    public function status()
    {
        return $this->belongsTo(Status::class); 
    }

    // Link to Departments (The Tagging)
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'eo_department')
                    ->withPivot('role') 
                    ->withTimestamps();
    }
}
