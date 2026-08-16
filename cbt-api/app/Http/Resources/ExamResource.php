<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'access_code' => $this->access_code, 'subject' => new SubjectResource($this->whenLoaded('subject')), 'question_bank' => $this->whenLoaded('questionBank', fn () => $this->questionBank ? ['id' => $this->questionBank->id, 'title' => $this->questionBank->title, 'validated_at' => $this->questionBank->validated_at?->toISOString()] : null), 'start_at' => $this->start_at->toISOString(), 'duration_minutes' => $this->duration_minutes, 'status' => $this->status->value, 'credentials_generated_at' => $this->credentials_generated_at?->toISOString(), 'credentials_count' => $this->whenCounted('credentials'), 'rooms' => $this->whenLoaded('roomAssignments', fn () => $this->roomAssignments->map(fn ($assignment) => ['id' => $assignment->room->id, 'name' => $assignment->room->name]))];
    }
}
