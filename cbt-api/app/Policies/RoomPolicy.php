<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Kurikulum && $user->school_id !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Room $room): bool
    {
        return $this->owns($user, $room);
    }

    public function delete(User $user, Room $room): bool
    {
        return $this->owns($user, $room);
    }

    public function mapStudents(User $user, Room $room): bool
    {
        return $this->owns($user, $room);
    }

    public function viewCredentials(User $user, Room $room): bool
    {
        return $this->owns($user, $room);
    }

    private function owns(User $user, Room $room): bool
    {
        return $this->viewAny($user) && hash_equals($user->school_id, $room->school_id);
    }
}
