<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'school' => $this->whenLoaded('school', fn (): array => [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'npsn' => $this->school->npsn,
                'type' => $this->school->type->value,
            ]),
        ];
    }
}
