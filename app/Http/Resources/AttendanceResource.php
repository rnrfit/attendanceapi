<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'sessionDate' => optional($this->sessionDate)->toDateString(),
            'sessionTime' => $this->sessionTime,
            'createdBy' => $this->createdBy,
            'createdAt' => optional($this->createdAt)->toIso8601String(),
        ];
    }
}
