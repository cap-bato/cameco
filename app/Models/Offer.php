<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'title',
        'details',
        'salary',
        'valid_until',
        'created_by',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
