<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdu_project_id',
        'type',
        'severity',
        'title',
        'message',
        'context',
        'is_resolved',
        'resolved_by',
        'resolved_at',
        'resolution_note',
        'detected_at',
    ];

    protected $casts = [
        'context' => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'detected_at' => 'datetime',
    ];

    public const TYPES = [
        'delay' => 'Retard',
        'budget_overrun' => 'Dépassement budgétaire',
        'progress_gap' => 'Écart d\'avancement',
        'milestone_missed' => 'Jalon manqué',
        'no_update' => 'Absence de mise à jour',
    ];

    public const SEVERITIES = [
        'info' => 'Information',
        'warning' => 'Attention',
        'critical' => 'Critique',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PduProject::class, 'pdu_project_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AlertComment::class)->latest();
    }

    public function scopeOpen($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeResolved($query)
    {
        return $query->where('is_resolved', true);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getSeverityLabelAttribute(): string
    {
        return self::SEVERITIES[$this->severity] ?? $this->severity;
    }
}
