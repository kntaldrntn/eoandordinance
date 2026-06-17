<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Committee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type', // e.g., 'council', 'program', 'ordinance_sponsors'
        'registry_id',
        'co_lead_office_id', // ← ADDED: was missing, causing silent NULL on create()
    ];

    // The people inside this committee
    public function members()
    {
        return $this->belongsToMany(CommitteeMember::class, 'committee_member_assignments')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    // Link to a registry when this committee was created from a CommitteeRegistry
    public function registry()
    {
        return $this->belongsTo(CommitteeRegistry::class, 'registry_id');
    }

    // The Executive Orders this committee is attached to
    public function executiveOrders()
    {
        return $this->belongsToMany(ExecutiveOrder::class, 'eo_committee', 'committee_id', 'eo_id')
                    ->withTimestamps();
    }

    // The Ordinances this committee is attached to
    public function ordinances()
    {
        return $this->belongsToMany(Ordinance::class, 'ordinance_committee', 'committee_id', 'ordinance_id')
                    ->withTimestamps();
    }

        public function coLeadOffice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Department::class, 'co_lead_office_id');
    }
}