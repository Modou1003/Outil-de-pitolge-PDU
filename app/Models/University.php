<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class University extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'acronym',
        'location',
        'latitude',
        'longitude',
        'region',
        'address',
        'phone',
        'email',
        'website',
        'description',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /**
     * Get the projects for the university.
     */
    public function pduProjects(): HasMany
    {
        return $this->hasMany(PduProject::class);
    }

    /**
     * Get the reports for the university.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Scope a query to only include active universities.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the university's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->acronym ? "{$this->name} ({$this->acronym})" : $this->name;
    }
}