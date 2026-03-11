<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppraisalScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'appraisal_id',
        'appraisal_criteria_id',
        'score',
        'comments',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    // Relationships
    public function appraisal()
    {
        return $this->belongsTo(Appraisal::class);
    }

    public function criteria()
    {
        return $this->belongsTo(AppraisalCriteria::class, 'appraisal_criteria_id');
    }
}
