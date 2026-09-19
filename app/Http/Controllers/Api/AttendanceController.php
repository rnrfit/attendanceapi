<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends ApiController
{
    /**
     * GET /api/v1/attendance
     *
     * Attendance records for the configured tenant/location, newest first.
     * `date` filters to one day; `startDate`+`endDate` filters to a range;
     * with none of those given, returns the most recent records ("All").
     *
     * Always capped by `limit` (default/max below) — this table already has
     * 250k+ historical rows, so an unfiltered request must never try to
     * return the whole thing.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $limit = $validated['limit'] ?? 200;

        $query = Attendance::query()
            ->with('student')
            ->where('tenantId', $this->tenantId())
            ->where('locationId', $this->locationId());

        if (!empty($validated['date'])) {
            $query->whereDate('sessionDate', $validated['date']);
        } elseif (!empty($validated['startDate']) && !empty($validated['endDate'])) {
            $query->whereBetween(
                'sessionDate',
                [$validated['startDate'] . ' 00:00:00', $validated['endDate'] . ' 23:59:59']
            );
        }

        $records = $query
            ->orderByDesc('sessionDate')
            ->orderByDesc('sessionTime')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->success(AttendanceResource::collection($records));
    }

    /**
     * GET /api/v1/attendance/stats?days=7
     *
     * Today's present/absent/rate against the count of students actually
     * scheduled today (via `batch`, same source as /students/today and
     * /embeddings/today) — not every active student at the location, which
     * would overcount "absent" for anyone without a class today. Plus a
     * day-by-day distinct-student-present trend for the last N days.
     */
    public function stats(Request $request)
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);
        $days = $validated['days'] ?? 7;

        $tenantId = $this->tenantId();
        $locationId = $this->locationId();

        // Same final filter as StudentController::today() — the raw batch
        // table can contain stale rows for students who are no longer
        // active, so scope down to active students same as the roster does.
        $scheduledToday = Batch::scheduledTodayByStudent($tenantId, $locationId);
        $totalActive = Student::query()
            ->forTenantLocation($tenantId, $locationId)
            ->active()
            ->whereIn('id', $scheduledToday->keys())
            ->count();

        $today = Carbon::now()->toDateString();
        $presentToday = Attendance::query()
            ->where('tenantId', $tenantId)
            ->where('locationId', $locationId)
            ->whereDate('sessionDate', $today)
            ->distinct('studentId')
            ->count('studentId');

        $absentToday = max(0, $totalActive - $presentToday);
        $rate = $totalActive > 0 ? round($presentToday / $totalActive * 100, 1) : 0;

        $startDate = Carbon::now()->subDays($days - 1)->toDateString();
        $countsByDate = Attendance::query()
            ->selectRaw('DATE(sessionDate) as d, COUNT(DISTINCT studentId) as c')
            ->where('tenantId', $tenantId)
            ->where('locationId', $locationId)
            ->whereDate('sessionDate', '>=', $startDate)
            ->groupBy('d')
            ->pluck('c', 'd');

        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $trend[] = ['date' => $date, 'count' => (int) ($countsByDate[$date] ?? 0)];
        }

        return $this->success([
            'today' => [
                'total' => $totalActive,
                'present' => $presentToday,
                'absent' => $absentToday,
                'rate' => $rate,
            ],
            'trend' => $trend,
        ]);
    }

    /**
     * POST /api/v1/attendance
     *
     * Marks attendance for a student today. sessionDate is always today
     * (server-side), sessionTime is stored separately. Idempotent per day:
     * calling this again for the same student on the same day returns the
     * existing record instead of creating a duplicate.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'studentId' => ['required', 'integer'],
            'sessionTime' => ['nullable', 'date_format:h:i A'],
            'createdBy' => ['nullable', 'string', 'max:191'],
        ]);

        $tenantId = $this->tenantId();
        $locationId = $this->locationId();

        $student = Student::query()
            ->forTenantLocation($tenantId, $locationId)
            ->active()
            ->find($validated['studentId']);

        if (!$student) {
            return $this->error('Student not found.', 404);
        }

        $today = Carbon::now()->startOfDay();

        $existing = Attendance::query()
            ->where('studentId', $student->id)
            ->where('tenantId', $tenantId)
            ->where('locationId', $locationId)
            ->whereDate('sessionDate', $today->toDateString())
            ->first();

        if ($existing) {
            return $this->success(new AttendanceResource($existing), 'Attendance already marked today.', 200);
        }

        $attendance = Attendance::create([
            'studentId' => $student->id,
            'tenantId' => $tenantId,
            'locationId' => $locationId,
            'sessionDate' => $today,
            'sessionTime' => $validated['sessionTime'] ?? Carbon::now()->format('h:i A'),
            'createdBy' => $validated['createdBy'] ?? 'mobile-app',
        ]);

        return $this->success(new AttendanceResource($attendance), 'Attendance marked.', 201);
    }
}
