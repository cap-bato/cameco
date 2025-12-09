<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
use HasFactory;

    protected $fillable = [
        'application_id',
        'job_title',
        'scheduled_date',
        'scheduled_time',
        'duration_minutes',
        'location_type',
        'status',
        'score',
        'interviewer_name',
        'recommendation',
        'feedback',
        'cancellation_reason',
        'cancelled_at',
    ];
// Interview.php
protected $casts = [
    'scheduled_date' => 'date',
    'scheduled_time' => 'string', // treat TIME column as string
    'cancelled_at' => 'datetime',
];


    // Relationships
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}

