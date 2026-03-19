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
        'middle_name',
        'email',
        'phone',
        'address',
        'birthdate',
        'gender',
        'department_id',
        'position_id',
        'resume_path',
        'source',
        'status',
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

public function notes()
{
    return $this->hasMany(Note::class);
}

}
