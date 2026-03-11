<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppraisalCriteria extends Model
{
    use HasFactory;

    protected $table = 'appraisal_criteria';

    protected $fillable = [
        'appraisal_cycle_id',
        'name',
        'description',
        'weight',
        'max_score',
        'sort_order',
    ];

    protected $casts = [
        'weight' => 'integer',
        'max_score' => 'integer',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function cycle()
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function scores()
    {
        return $this->hasMany(AppraisalScore::class);
    }
}
