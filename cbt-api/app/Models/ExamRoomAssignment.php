<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamRoomAssignment extends Model
{
    use HasUuids;

    protected $fillable = ['school_id', 'exam_id', 'room_id'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
