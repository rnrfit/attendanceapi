<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\StudentFaceEmbeddingResource;
use App\Models\Batch;
use App\Models\Student;
use App\Models\StudentFaceEmbedding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * POST /api/v1/students/{studentId}/embeddings/replace
     *
     * Replace all of a student's face embeddings with a new set, in one
     * transaction. Re-enrolling a student must not leave the previous samples
     * matchable — they may be of a different person's face.
     */
    public function replace(Request $request, int $studentId)
    {
        $student = Student::query()
            ->forTenantLocation($this->tenantId(), $this->locationId())
            ->active()
            ->find($studentId);

        if (!$student) {
            return $this->error('Student not found.', 404);
        }

        $validated = $request->validate([
            'samples' => ['required', 'array', 'min:1', 'max:10'],
            'samples.*.embedding' => ['required', 'array', 'min:1'],
            'samples.*.embedding.*' => ['numeric'],
            'samples.*.sampleLabel' => ['nullable', 'string', 'max:20'],
            'createdBy' => ['nullable', 'string', 'max:191'],
        ]);

        $tenantId = $this->tenantId();
        $locationId = $this->locationId();
        $createdBy = $validated['createdBy'] ?? 'mobile-app';

        $embeddings = DB::transaction(function () use ($student, $validated, $tenantId, $locationId, $createdBy) {
            StudentFaceEmbedding::query()
                ->where('studentId', $student->id)
                ->where('tenantId', $tenantId)
                ->where('locationId', $locationId)
                ->delete();

            return collect($validated['samples'])->map(function ($sample) use ($student, $tenantId, $locationId, $createdBy) {
                return StudentFaceEmbedding::create([
                    'studentId' => $student->id,
                    'tenantId' => $tenantId,
                    'locationId' => $locationId,
                    'embedding' => $sample['embedding'],
                    'sampleLabel' => $sample['sampleLabel'] ?? null,
                    'createdBy' => $createdBy,
                ]);
            });
        });

        return $this->success(StudentFaceEmbeddingResource::collection($embeddings), 'Embeddings replaced.');
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

    /**
     * GET /api/v1/embeddings
     *
     * Embeddings for every active student at the tenant/location (not just
     * today's roster), grouped by studentId in the same shape as
     * /embeddings/today — lets the scanner name an enrolled student even on a
     * day their batch isn't scheduled. `sampleLabelPrefix` optionally limits
     * it to samples from one enrollment pipeline version.
     */
    public function all(Request $request)
    {
        $validated = $request->validate([
            'sampleLabelPrefix' => ['nullable', 'string', 'max:20'],
        ]);

        $query = StudentFaceEmbedding::query()
            ->with('student')
            ->where('tenantId', $this->tenantId())
            ->where('locationId', $this->locationId())
            ->whereHas('student', function ($student) {
                $student->active();
            });

        if (isset($validated['sampleLabelPrefix'])) {
            $query->where('sampleLabel', 'like', addcslashes($validated['sampleLabelPrefix'], '%_\\') . '%');
        }

        $embeddings = $query
            ->orderBy('studentId')
            ->get()
            ->groupBy('studentId')
            ->map(function ($group) {
                return StudentFaceEmbeddingResource::collection($group->values());
            });

        return $this->success($embeddings);
    }

    /**
     * POST /api/v1/embeddings/match
     *
     * The active student whose stored embedding is most similar (cosine) to
     * the given one, across the whole tenant/location — not just today's
     * roster. Lets the app refuse to register a face that's already saved
     * for a different student. `data` is null when nothing is stored.
     */
    public function match(Request $request)
    {
        $validated = $request->validate([
            'embedding' => ['required', 'array', 'min:1'],
            'embedding.*' => ['numeric'],
            'excludeStudentId' => ['nullable', 'integer'],
            'sampleLabelPrefix' => ['nullable', 'string', 'max:20'],
        ]);

        $query = StudentFaceEmbedding::query()
            ->select('id', 'studentId', 'embedding')
            ->where('tenantId', $this->tenantId())
            ->where('locationId', $this->locationId())
            ->whereHas('student', function ($student) {
                $student->active();
            });

        if (isset($validated['excludeStudentId'])) {
            $query->where('studentId', '!=', $validated['excludeStudentId']);
        }
        if (isset($validated['sampleLabelPrefix'])) {
            $query->where('sampleLabel', 'like', addcslashes($validated['sampleLabelPrefix'], '%_\\') . '%');
        }

        $probe = array_map('floatval', $validated['embedding']);
        $bestStudentId = null;
        $bestScore = -1.0;
        foreach ($query->get() as $stored) {
            $score = self::cosineSimilarity($probe, $stored->embedding);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestStudentId = $stored->studentId;
            }
        }

        if ($bestStudentId === null) {
            return $this->success(null);
        }

        return $this->success([
            'studentId' => $bestStudentId,
            'studentName' => optional(Student::find($bestStudentId))->full_name,
            'score' => round($bestScore, 4),
        ]);
    }

    private static function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        foreach ($a as $i => $value) {
            $other = (float) $b[$i];
            $dot += $value * $other;
            $normA += $value * $value;
            $normB += $other * $other;
        }

        if ($normA < 1e-12 || $normB < 1e-12) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
