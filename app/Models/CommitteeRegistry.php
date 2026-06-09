<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeRegistry extends Model
{
    protected $fillable = ['name'];

    public function members() {
        return $this->belongsToMany(CommitteeMember::class, 'committee_member_registry');
    }
}
