<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Ordinance extends Model
{
    protected $guarded = [];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'ordinance_department')
                    ->withPivot('role') // role = 'sponsor', 'implementing', etc.
                    ->withTimestamps();
    }

    public function parentOrdinance()
    {
        return $this->belongsTo(Ordinance::class, 'amends_ordinance_id');
    }

    public function amendments()
    {
        return $this->hasMany(Ordinance::class, 'amends_ordinance_id');
    }
}