<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFaceEmbedding extends Model
{
    protected $table = 'student_face_embeddings';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'studentId',
        'tenantId',
        'locationId',
        'embedding',
        'sampleLabel',
        'createdBy',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'studentId');
    }

    public function setEmbeddingAttribute($value): void
    {
        $this->attributes['embedding'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getEmbeddingAttribute($value): array
    {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
