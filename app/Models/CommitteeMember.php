<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommitteeMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'pmis_id',
        'name',
        'position',
        'agency'
    ];

    // A person can be assigned to multiple committees across different documents
    public function committees()
    {
        return $this->belongsToMany(Committee::class, 'committee_member_assignments')
                    ->withPivot('role') // 🚀 This fetches whether they are 'Chairman', 'Member', etc.
                    ->withTimestamps();
    }
}