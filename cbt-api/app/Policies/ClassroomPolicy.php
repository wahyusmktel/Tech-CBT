<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Kurikulum && $user->school_id !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $this->owns($user, $classroom);
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $this->owns($user, $classroom);
    }

    private function owns(User $user, Classroom $classroom): bool
    {
        return $this->viewAny($user) && hash_equals($user->school_id, $classroom->school_id);
    }
}
