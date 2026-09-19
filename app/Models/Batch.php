<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $table = 'batch';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'tenantId',
        'locationId',
        'programId',
        'courseId',
        'paymentCycle',
        'amount',
        'studentId',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
        'coachId',
        'isActive',
        'createdBy',
        'updatedBy',
    ];

    protected $casts = [
        'isActive' => 'boolean',
    ];

    /** Day-of-week column names, in PHP `date('l')` order. */
    public const DAY_COLUMNS = [
        'Sunday' => 'sunday',
        'Monday' => 'monday',
        'Tuesday' => 'tuesday',
        'Wednesday' => 'wednesday',
        'Thursday' => 'thursday',
        'Friday' => 'friday',
        'Saturday' => 'saturday',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'studentId');
    }

    /**
     * The Laravel column name for the given `date('l')` day name (e.g. "Monday").
     */
    public static function dayColumn(string $dayName): string
    {
        return self::DAY_COLUMNS[$dayName] ?? strtolower($dayName);
    }

    /**
     * studentId => sessionTime for every active batch scheduled on today's
     * weekday, for the given tenant/location. If a student has more than one
     * matching batch row, the last one wins (arbitrary but deterministic).
     */
    public static function scheduledTodayByStudent(int $tenantId, int $locationId): \Illuminate\Support\Collection
    {
        $dayColumn = self::dayColumn(now()->format('l'));

        return self::query()
            ->where('tenantId', $tenantId)
            ->where('locationId', $locationId)
            ->where('isActive', true)
            ->whereNotNull($dayColumn)
            ->where($dayColumn, '!=', '')
            ->pluck($dayColumn, 'studentId');
    }
}
