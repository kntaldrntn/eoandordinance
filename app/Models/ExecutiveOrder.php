<?php

namespace App\Models;

use App\Traits\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ExecutiveOrder extends Model
{
    use RecordsActivity;
    protected $guarded = [];
    
    protected $appends = ['file_url', 'public_timeline'];

    // 1. ADDED: Cast is_active to boolean
    protected $casts = [
        'is_active' => 'boolean',
        'date_issued' => 'datetime', // Good practice to cast dates too
    ];

    // 2. ADDED: Scope for easy filtering (e.g., ExecutiveOrder::active()->get())
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

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
    
    public function getPublicTimelineAttribute()
    {
        $timeline = collect();

        // A. The "Genesis" Event
        $originDate = $this->date_issued ?? $this->created_at;
        
        $timeline->push([
            'date' => $originDate, 
            'date_display' => \Carbon\Carbon::parse($originDate)->format('M d, Y'),
            'time' => '', 
            'action' => 'Record Published',
            'details' => [['text' => 'Original issuance date.']],
            'file_url' => null,
        ]);

        // B. Audit Logs (Updated to include is_active changes)
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
                        'details' => [['text' => 'Legal status changed (e.g. to Amended/Repealed).']],
                        'file_url' => null,
                    ];
                }

                // 3. ADDED: Active/Inactive Toggle History
                if (isset($audit->new_values['is_active'])) {
                    $isActive = $audit->new_values['is_active'];
                    return [
                        'date' => $audit->created_at,
                        'date_display' => $audit->created_at->format('M d, Y'),
                        'time' => $audit->created_at->format('h:i A'),
                        'action' => $isActive ? 'Marked as Active' : 'Marked as Inactive',
                        'details' => [['text' => $isActive ? 'Record is now effective.' : 'Record is no longer in effect.']],
                        'file_url' => null,
                    ];
                }

                return null;
            })
            ->filter();

        // C. AMENDMENTS
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
                'file_url' => $child->file_url, 
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