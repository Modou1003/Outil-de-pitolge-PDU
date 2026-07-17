<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPayment extends Model
{
    protected $fillable = [
        'pdu_project_id',
        'number',
        'period',
        'payment_date',
        'gross_amount',
        'startup_advance_recovery',
        'supply_advance_recovery',
        'net_paid',
        'is_paid',
        'observations',
        'recorded_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'gross_amount' => 'decimal:2',
        'startup_advance_recovery' => 'decimal:2',
        'supply_advance_recovery' => 'decimal:2',
        'net_paid' => 'decimal:2',
        'is_paid' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PduProject::class, 'pdu_project_id');
    }
}
