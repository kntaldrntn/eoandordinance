<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ImplementingRuleandRegulation extends Model
{
    use HasFactory;

    protected $table = 'implementing_rules_and_regulations';
    protected $guarded = [];
    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }

    public function executiveOrder()
    {
        return $this->belongsTo(ExecutiveOrder::class);
    }
    
    public function leadOffice()
    {
        return $this->belongsTo(Department::class, 'lead_office_id');
    }
    public function ordinance()
    {
        return $this->belongsTo(Ordinance::class, 'ordinance_id');
    }
}
