<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommitteeRegistry extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public function members() {
        return $this->belongsToMany(CommitteeMember::class, 'committee_member_registry');
    }
}
