<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'nisn' => $this->nisn, 'name' => $this->name, 'classroom' => new ClassroomResource($this->whenLoaded('classroom')), 'created_at' => $this->created_at?->toISOString()];
    }
}
