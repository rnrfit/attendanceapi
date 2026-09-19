<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\StudentFaceEmbeddingResource;
use App\Models\Batch;
use App\Models\Student;
use App\Models\StudentFaceEmbedding;
use Illuminate\Http\Request;

class StudentEmbeddingController extends ApiController
{
    /**
     * POST /api/v1/students/{studentId}/embeddings
     *
     * Store a face embedding generated on-device for a student.
     */
    public function store(Request $request, int $studentId)
    {
        $student = Student::query()
            ->forTenantLocation($this->tenantId(), $this->locationId())
            ->active()
            ->find($studentId);

        if (!$student) {
            return $this->error('Student not found.', 404);
        }

        $validated = $request->validate([
            'embedding' => ['required', 'array', 'min:1'],
            'embedding.*' => ['numeric'],
            'sampleLabel' => ['nullable', 'string', 'max:20'],
            'createdBy' => ['nullable', 'string', 'max:191'],
        ]);

        $embedding = StudentFaceEmbedding::create([
            'studentId' => $student->id,
            'tenantId' => $this->tenantId(),
            'locationId' => $this->locationId(),
            'embedding' => $validated['embedding'],
            'sampleLabel' => $validated['sampleLabel'] ?? null,
            'createdBy' => $validated['createdBy'] ?? 'mobile-app',
        ]);

        return $this->success(new StudentFaceEmbeddingResource($embedding), 'Embedding stored.', 201);
    }

    /**
     * GET /api/v1/students/{studentId}/embeddings
     *
     * All stored embeddings for one student.
     */
    public function index(int $studentId)
    {
        $student = Student::query()
            ->forTenantLocation($this->tenantId(), $this->locationId())
            ->find($studentId);

        if (!$student) {
            return $this->error('Student not found.', 404);
        }

        $embeddings = $student->faceEmbeddings()->orderBy('createdAt')->get();

        return $this->success(StudentFaceEmbeddingResource::collection($embeddings));
    }

    /**
     * GET /api/v1/embeddings/today
     *
     * All embeddings for students on today's roster, grouped by studentId —
     * lets the mobile app sync everything needed for on-device matching in
     * one call.
     */
    public function today()
    {
        $tenantId = $this->tenantId();
        $locationId = $this->locationId();

        $scheduled = Batch::scheduledTodayByStudent($tenantId, $locationId);

        if ($scheduled->isEmpty()) {
            return $this->success([]);
        }

        $embeddings = StudentFaceEmbedding::query()
            ->with('student')
            ->where('tenantId', $tenantId)
            ->where('locationId', $locationId)
            ->whereIn('studentId', $scheduled->keys())
            ->orderBy('studentId')
            ->get()
            ->groupBy('studentId')
            ->map(function ($group) {
                return StudentFaceEmbeddingResource::collection($group->values());
            });

        return $this->success($embeddings);
    }
}
