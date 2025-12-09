<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPosting extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'department_id',
        'description',
        'requirements',
        'status',
        'posted_at',
        'closed_at',
        'created_by',
    ];

    // Relationship: JobPosting belongs to a Department
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // Relationship: JobPosting belongs to a User (creator)
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relationship: JobPosting has many Applications
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    protected $casts = [
    'posted_at' => 'datetime',
    'closed_at' => 'datetime',
];
}
