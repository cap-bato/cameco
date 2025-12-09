<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'job_posting_id',
        'status',
        'score',
        'applied_at',
    ];

    // Relationships
    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    public function statusHistory()
{
    return $this->hasMany(ApplicationStatusHistory::class)->orderBy('created_at');
}

public function offers()
{
    return $this->hasMany(Offer::class);
}

public function notes()
{
    return $this->hasMany(ApplicationNote::class)->orderBy('created_at', 'desc');
}

}
