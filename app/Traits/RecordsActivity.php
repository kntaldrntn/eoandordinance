<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait RecordsActivity
{
    // Boot the trait automatically when the model is used
    protected static function bootRecordsActivity()
    {
        // Hook into the 'created' event
        static::created(function ($model) {
            $model->recordAudit('Created', null, $model->toArray());
        });

        // Hook into the 'updated' event
        static::updated(function ($model) {
            // Only record if something actually changed
            if (count($model->getDirty()) > 0) {
                $model->recordAudit(
                    'Updated', 
                    $model->getOriginal(), // Old data
                    $model->getChanges()   // New changes only
                );
            }
        });

        // Hook into the 'deleted' event
        static::deleted(function ($model) {
            $model->recordAudit('Deleted', $model->toArray(), null);
        });
    }

    protected function recordAudit($action, $old, $new)
    {
        // Don't record audits for automated tasks or guests (optional)
        if (!Auth::check()) return;

        AuditLog::create([
            'user_id'        => Auth::id(),
            'action'         => $action,
            'auditable_type' => get_class($this), // e.g., App\Models\Ordinance
            'auditable_id'   => $this->id,
            'old_values'     => $old, 
            'new_values'     => $new,
            'ip_address'     => request()->ip(),
        ]);
    }

    // Helper relationship to fetch logs for this item
    public function audits()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }
}