<?php

namespace App\Models;

use App\Observers\PduProjectObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([PduProjectObserver::class])]
class PduProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pdu_projects';

    protected $fillable = [
        'title',
        'description',
        'code',
        'university_id',
        'created_by',
        'start_date',
        'end_date',
        'planned_completion_date',
        'status',
        'type',
        'progress_percentage',
        'budget_allocated',
        'budget_spent',
        'currency',
        'objectives',
        'stakeholders',
        'metadata',
        'director_id',
        'director_name',
        'director_email',
        'project_manager_id',
        'project_manager_name',
        'project_manager_email',
        'financial_agent_id',
        'financial_agent_name',
        'financial_agent_email',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'planned_completion_date' => 'date',
        'progress_percentage' => 'decimal:2',
        'budget_allocated' => 'decimal:2',
        'budget_spent' => 'decimal:2',
        'objectives' => 'array',
        'stakeholders' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the university that owns the project.
     */
    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    /**
     * Get the user who created the project.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the director of the project.
     */
    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    /**
     * Get the project manager.
     */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    /**
     * Get the financial agent.
     */
    public function financialAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'financial_agent_id');
    }

    /**
     * Get the indicator trackings for the project.
     */
    public function indicatorTrackings(): HasMany
    {
        return $this->hasMany(IndicatorTracking::class);
    }

    /**
     * Get the alerts for the project.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function buildingWorks(): HasMany
    {
        return $this->hasMany(BuildingWork::class)->orderBy('sort_order');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ProjectLot::class)->orderBy('sort_order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('planned_date');
    }

    public function physicalProgresses(): HasMany
    {
        return $this->hasMany(PhysicalProgress::class)->orderBy('measurement_date');
    }

    public function financialProgresses(): HasMany
    {
        return $this->hasMany(FinancialProgress::class)->orderBy('measurement_date');
    }

    /**
     * Get the reports for the project.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get the documents for the project.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(ProjectTeamMember::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the comments for the project.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'commentable_id')->where('commentable_type', self::class);
    }

    /**
     * Scope a query to only include active projects.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['in_progress', 'approved']);
    }

    /**
     * Scope a query to only include completed projects.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get the remaining budget.
     */
    public function getRemainingBudgetAttribute(): float
    {
        return $this->budget_allocated - $this->budget_spent;
    }

    /**
     * Get the budget execution rate.
     */
    public function getBudgetExecutionRateAttribute(): float
    {
        if ($this->budget_allocated == 0) {
            return 0;
        }
        return round(($this->budget_spent / $this->budget_allocated) * 100, 2);
    }

    /**
     * Check if the project is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== 'completed';
    }

    /**
     * Get the planned progress based on elapsed time between start_date and end_date.
     */
    public function getPlannedProgressAttribute(): float
    {
        if (! $this->start_date || ! $this->end_date) {
            return 0;
        }

        $total = $this->start_date->diffInDays($this->end_date);
        if ($total <= 0) {
            return 100;
        }

        $elapsed = $this->start_date->diffInDays(now());
        $planned = ($elapsed / $total) * 100;

        return (float) max(0, min(100, round($planned, 2)));
    }
}