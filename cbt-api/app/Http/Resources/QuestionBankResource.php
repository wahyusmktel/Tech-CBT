<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionBankResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'subject' => new SubjectResource($this->whenLoaded('subject')), 'questions_count' => $this->whenCounted('questions'), 'validated_at' => $this->validated_at?->toISOString(), 'validated_by' => $this->whenLoaded('validator', fn () => $this->validator?->name), 'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn ($question) => ['id' => $question->id, 'number' => $question->number, 'text' => $question->text, 'choices' => $question->choices->map(fn ($choice) => ['id' => $choice->id, 'label' => $choice->label, 'text' => $choice->text, 'is_correct' => $choice->is_correct])]))];
    }
}
