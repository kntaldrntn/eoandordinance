<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CityEmployee extends Model
{
    use HasFactory;

    protected $primaryKey = 'pmis_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pmis_id',
        'full_name',
        'position',
        'dept_id',
        'state'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'dept_id');
    }
}