<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ExecutiveOrder extends Model
{
    protected $guarded = [];
    protected $appends = ['file_url'];

    public function status()
    {
        return $this->belongsTo(Status::class); 
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'eo_department')
                    ->withPivot('role') 
                    ->withTimestamps();
    }

    public function getFileUrlAttribute()
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }

    public function parentEO()
    {
        return $this->belongsTo(ExecutiveOrder::class, 'amends_eo_id');
    }
    
    public function amendments()
    {
        return $this->hasMany(ExecutiveOrder::class, 'amends_eo_id');
    }
    public function implementingRules()
    {
        return $this->hasMany(ImplementingRuleandRegulation::class);
    }
}
