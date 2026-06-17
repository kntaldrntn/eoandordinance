<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdinanceCode extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'description'];

    public function ordinances()
    {
        return $this->hasMany(Ordinance::class);
    }
}
