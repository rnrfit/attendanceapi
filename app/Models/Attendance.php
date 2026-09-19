<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = [
        'studentId',
        'tenantId',
        'locationId',
        'sessionDate',
        'sessionTime',
        'createdBy',
    ];

    protected $casts = [
        'sessionDate' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'studentId');
    }
}
