<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SchoolResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'npsn' => $this->npsn,
            'type' => $this->type->value,
            'address' => $this->address,
            'principal_name' => $this->principal_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'letterhead_url' => $this->letterhead_path
                ? Storage::disk('public')->url($this->letterhead_path)
                : null,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
