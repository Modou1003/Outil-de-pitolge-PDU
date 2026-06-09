<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'code',
        'category',
        'subcategory',
        'type',
        'unit',
        'unit_symbol',
        'target_value',
        'minimum_value',
        'maximum_value',
        'frequency',
        'calculation_method',
        'data_sources',
        'metadata',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'target_value' => 'decimal:4',
        'minimum_value' => 'decimal:4',
        'maximum_value' => 'decimal:4',
        'calculation_method' => 'array',
        'data_sources' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the indicator trackings for the indicator.
     */
    public function indicatorTrackings(): HasMany
    {
        return $this->hasMany(IndicatorTracking::class);
    }

    /**
     * Scope a query to only include active indicators.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the display name with unit.
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->name;
        if ($this->unit_symbol) {
            $name .= " ({$this->unit_symbol})";
        }
        return $name;
    }

    /**
     * Get the latest tracking value for a specific project.
     */
    public function getLatestValueForProject(int $projectId): ?float
    {
        return $this->indicatorTrackings()
            ->where('pdu_project_id', $projectId)
            ->latest('measurement_date')
            ->value('actual_value');
    }

    /**
     * Get the target achievement percentage for a specific project.
     */
    public function getTargetAchievementForProject(int $projectId): ?float
    {
        $latestValue = $this->getLatestValueForProject($projectId);

        if (!$latestValue || !$this->target_value) {
            return null;
        }

        return round(($latestValue / $this->target_value) * 100, 2);
    }
}