<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;
    
    protected $table = 'profiles';
    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'date_of_birth',
        'place_of_birth',
        'is_pwd',
        'gender',
        'civil_status',
        'spouse_name',
        'spouse_date_of_birth',
        'spouse_contact_number',
        'father_name',
        'father_date_of_birth',
        'mother_name',
        'mother_date_of_birth',
        'phone',
        'mobile',
        'email',
        'current_address',
        'permanent_address',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'emergency_contact_address',
        'sss_number',
        'tin_number',
        'philhealth_number',
        'pagibig_number',
        'profile_picture_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }
}
