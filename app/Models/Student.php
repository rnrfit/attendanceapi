<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'student';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'customer_id',
        'tenantId',
        'locationId',
        'userId',
        'registrationNo',
        'firstName',
        'middleName',
        'lastName',
        'status',
        'notes',
        'deactivateDate',
        'deactivateReason',
        'createdBy',
        'updatedBy',
    ];

    protected $casts = [
        'deletedAt' => 'datetime',
    ];

    /**
     * Batches this student is enrolled in.
     */
    public function batches()
    {
        return $this->hasMany(Batch::class, 'studentId');
    }

    /**
     * Stored face embeddings for this student.
     */
    public function faceEmbeddings()
    {
        return $this->hasMany(StudentFaceEmbedding::class, 'studentId');
    }

    /**
     * Active, non-deleted students only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE')->whereNull('deletedAt');
    }

    public function scopeForTenantLocation($query, int $tenantId, int $locationId)
    {
        return $query->where('tenantId', $tenantId)->where('locationId', $locationId);
    }

    public function getFullNameAttribute(): string
    {
        return trim(collect([$this->firstName, $this->middleName, $this->lastName])
            ->filter()
            ->implode(' '));
    }
}
