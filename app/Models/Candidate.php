<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'source',
        'status',
        'resume_path',
        'cover_letter',
        'linkedin_url',
        'portfolio_url',
        'applied_at',
    ];

    // Relationships
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

public function position()
{
    return $this->belongsTo(Position::class);
}

public function interviews()
{
    return $this->hasMany(Interview::class);
}

public function statusHistory()
{
    return $this->hasMany(ApplicationStatusHistory::class);
}

public function notes()
{
    return $this->hasMany(Note::class);
}

}
