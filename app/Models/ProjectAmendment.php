<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAmendment extends Model
{
    protected $fillable = [
        'pdu_project_id',
        'number',
        'object',
        'signed_date',
        'amount',
        'observations',
        'recorded_by',
    ];

    protected $casts = [
        'signed_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(PduProject::class, 'pdu_project_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
