<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentFaceEmbeddingResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'studentId' => $this->studentId,
            'studentName' => $this->whenLoaded('student', function () {
                return $this->student->full_name;
            }),
            'sampleLabel' => $this->sampleLabel,
            'embedding' => $this->embedding,
            'createdAt' => optional($this->createdAt)->toIso8601String(),
        ];
    }
}
