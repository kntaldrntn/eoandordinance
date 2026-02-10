<?php

namespace App\Models;

use App\Traits\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Ordinance extends Model
{
    use RecordsActivity;
    protected $guarded = [];

    // 1. Force attributes
    protected $appends = ['file_url', 'public_timeline'];

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
                    ->withPivot('role')
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
    public function implementingRules()
    {
        return $this->hasMany(ImplementingRuleandRegulation::class, 'ordinance_id');
    }

    // 2. Updated Timeline Logic
    public function getPublicTimelineAttribute()
    {
        $timeline = collect();

        // A. Genesis
        $originDate = $this->date_enacted ?? $this->created_at;
        
        $timeline->push([
            'date' => $originDate,
            'date_display' => \Carbon\Carbon::parse($originDate)->format('M d, Y'),
            'time' => '',
            'action' => 'Record Published',
            'details' => [['text' => 'Original issuance date.']],
            'file_url' => null,
        ]);

        // B. Audits
        $audits = $this->audits()
            ->where('action', '!=', 'Created')
            ->latest()
            ->get()
            ->map(function ($audit) {
                if (isset($audit->new_values['file_path'])) {
                    return [
                        'date' => $audit->created_at,
                        'date_display' => $audit->created_at->format('M d, Y'),
                        'time' => $audit->created_at->format('h:i A'),
                        'action' => 'Document Updated',
                        'details' => [['text' => 'A new PDF version was uploaded.']],
                        'file_url' => null, 
                    ];
                }
                if (isset($audit->new_values['status_id'])) {
                    return [
                        'date' => $audit->created_at,
                        'date_display' => $audit->created_at->format('M d, Y'),
                        'time' => $audit->created_at->format('h:i A'),
                        'action' => 'Status Updated',
                        'details' => [['text' => 'Status changed (e.g. to Amended/Repealed).']],
                        'file_url' => null,
                    ];
                }
                return null;
            })
            ->filter();

        // C. Amendments
        $amendments = $this->amendments->map(function ($child) {
            $childDate = $child->date_enacted ?? $child->created_at;
            
            return [
                'date' => $childDate, 
                'date_display' => \Carbon\Carbon::parse($childDate)->format('M d, Y'),
                'time' => '',
                'action' => 'Amended by ' . $child->ordinance_number, 
                'details' => [
                    [
                        'text' => $child->title,
                        'is_bold' => true
                    ]
                ],
                'file_url' => $child->file_url,
                'file_name' => "Download Record"
            ];
        });

        // D. Merge
        return $timeline
            ->concat($audits)
            ->concat($amendments)
            ->sortByDesc('date')
            ->values();
    }
}