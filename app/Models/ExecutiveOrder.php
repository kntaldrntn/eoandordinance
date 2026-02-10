<?php

namespace App\Models;

use App\Traits\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ExecutiveOrder extends Model
{
    use RecordsActivity;
    protected $guarded = [];
    
    // 1. Force these attributes to be included in JSON
    protected $appends = ['file_url', 'public_timeline'];

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

    // 2. Accessor for File URL
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
    
    // 3. Updated Timeline Logic with Backfill + Smart Links
    public function getPublicTimelineAttribute()
    {
        $timeline = collect();

        // A. The "Genesis" Event (Backfill)
        $originDate = $this->date_issued ?? $this->created_at;
        
        $timeline->push([
            'date' => $originDate, 
            'date_display' => \Carbon\Carbon::parse($originDate)->format('M d, Y'),
            'time' => '', 
            'action' => 'Record Published',
            'details' => [['text' => 'Original issuance date.']],
            'file_url' => null,
        ]);

        // B. Audit Logs (Filtered)
        $audits = $this->audits()
            ->where('action', '!=', 'Created') 
            ->latest()
            ->get()
            ->map(function ($audit) {
                
                // File Updates
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

                // Status Updates
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

        // C. AMENDMENTS (Smart Links)
        $amendments = $this->amendments->map(function ($child) {
            $childDate = $child->date_issued ?? $child->created_at;
            
            return [
                'date' => $childDate, 
                'date_display' => \Carbon\Carbon::parse($childDate)->format('M d, Y'),
                'time' => '',
                'action' => 'Amended by ' . $child->eo_number, 
                'details' => [
                    [
                        'text' => $child->title,
                        'is_bold' => true
                    ]
                ],
                'file_url' => $child->file_url, // Uses the accessor
                'file_name' => "Download " . $child->eo_number
            ];
        });

        // D. Merge & Sort
        return $timeline
            ->concat($audits)
            ->concat($amendments)
            ->sortByDesc('date')
            ->values();
    }
}