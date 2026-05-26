<?php

namespace App\Models;

use App\Services\AuditLogger;
use App\Support\ThaiDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /**
     * audit_logs has created_at only — immutable, no updated_at.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'table_affected',
        'record_id',
        'old_values',
        'new_values',
        'category',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOrderedForAudit(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Build the JSON payload shown in the detail modal.
     *
     * - Extracts 'context' key from new_values → exposes as top-level 'context'
     * - Strips 'context' from the displayed new_values so the diff stays clean
     * - Applies sensitive-field masking
     * - Reports which fields were masked in masked_fields[]
     */
    public function toDetailPayload(): array
    {
        $newValues = $this->new_values ?? [];
        $context   = $newValues[AuditLogger::CONTEXT_KEY] ?? null;
        unset($newValues[AuditLogger::CONTEXT_KEY]);

        [$cleanOld, $maskedOld] = AuditLogger::sanitizeWithReport($this->old_values);
        [$cleanNew, $maskedNew] = AuditLogger::sanitizeWithReport($newValues);
        $maskedFields = array_values(array_unique(array_merge($maskedOld, $maskedNew)));

        return [
            'id'            => $this->id,
            'action'        => $this->action,
            'category'      => $this->category,
            'description'   => $this->description,
            'actor'         => $this->user ? [
                'id'       => $this->user->id,
                'name'     => $this->user->name,
                'username' => $this->user->username,
            ] : null,
            'auditable'     => [
                'table' => $this->table_affected,
                'id'    => $this->record_id,
            ],
            'old_values'    => $cleanOld,
            'new_values'    => $cleanNew,
            'metadata'      => [],   // callers store bulk info via new_values; extracted by caller if needed
            'context'       => $context,
            'masked_fields' => $maskedFields,
            'created_at'    => $this->created_at
                ->format('Y-m-d H:i:s'),
            'display_created_at' => ThaiDate::formatDateTimeThai($this->created_at),
        ];
    }
}
