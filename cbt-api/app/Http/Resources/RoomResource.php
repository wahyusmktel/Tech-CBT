<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $observer = $this->relationLoaded('observers') ? $this->observers->first() : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'students_count' => $this->whenCounted('assignments'),
            'observer' => $observer ? ['id' => $observer->id, 'name' => $observer->name, 'username' => $observer->username] : null,
        ];
    }
}
