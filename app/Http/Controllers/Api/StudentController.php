<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\StudentResource;
use App\Models\Batch;
use App\Models\Student;

class StudentController extends ApiController
{
    /**
     * GET /api/v1/students
     *
     * All active students for the configured tenant/location (not scoped to
     * today's schedule) — used to populate a full student picker/search.
     */
    public function all()
    {
        $students = Student::query()
            ->forTenantLocation($this->tenantId(), $this->locationId())
            ->active()
            ->orderBy('firstName')
            ->get();

        return $this->success(StudentResource::collection($students));
    }

    /**
     * GET /api/v1/students/today
     *
     * Active students whose batch has a session scheduled today (today's
     * day-of-week column in `batch` is non-empty), for the configured
     * tenant/location.
     */
    public function today()
    {
        $tenantId = $this->tenantId();
        $locationId = $this->locationId();

        $scheduled = Batch::scheduledTodayByStudent($tenantId, $locationId);

        if ($scheduled->isEmpty()) {
            return $this->success([]);
        }

        $students = Student::query()
            ->forTenantLocation($tenantId, $locationId)
            ->active()
            ->whereIn('id', $scheduled->keys())
            ->orderBy('firstName')
            ->get()
            ->map(function (Student $student) use ($scheduled) {
                $student->sessionTime = $scheduled[$student->id] ?? null;
                return $student;
            });

        return $this->success(StudentResource::collection($students));
    }
}
