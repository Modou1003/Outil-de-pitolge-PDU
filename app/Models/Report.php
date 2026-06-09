<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'period',
        'start_date',
        'end_date',
        'university_id',
        'pdu_project_id',
        'created_by',
        'approved_by',
        'approved_at',
        'status',
        'data',
        'summary',
        'recommendations',
        'attachments',
        'metadata',
        'is_public',
        'published_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'data' => 'array',
        'summary' => 'array',
        'recommendations' => 'array',
        'attachments' => 'array',
        'metadata' => 'array',
        'is_public' => 'boolean',
    ];

    /**
     * Get the university that owns the report.
     */
    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    /**
     * Get the project that owns the report.
     */
    public function pduProject(): BelongsTo
    {
        return $this->belongsTo(PduProject::class);
    }

    /**
     * Get the user who created the report.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved the report.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the documents for the report.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'documentable_id')->where('documentable_type', self::class);
    }

    /**
     * Get the comments for the report.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'commentable_id')->where('commentable_type', self::class);
    }

    /**
     * Scope a query to only include published reports.
     */
    public function scopePublished($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope a query to only include approved reports.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by period.
     */
    public function scopeByPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Check if the report is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== 'approved';
    }

    /**
     * Get the report period as a formatted string.
     */
    public function getFormattedPeriodAttribute(): string
    {
        if ($this->start_date && $this->end_date) {
            return $this->start_date->format('M Y') . ' - ' . $this->end_date->format('M Y');
        }

        return $this->period ?? 'N/A';
    }
}