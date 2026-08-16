<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin && $user->school_id === null;
    }

    public function view(User $user, School $school): bool
    {
        return $this->viewAny($user) || $this->belongsToCurriculumUser($user, $school);
    }

    public function update(User $user, School $school): bool
    {
        return $this->belongsToCurriculumUser($user, $school);
    }

    public function resetCurriculumPassword(User $user, School $school): bool
    {
        return $this->viewAny($user);
    }

    private function belongsToCurriculumUser(User $user, School $school): bool
    {
        return $user->role === UserRole::Kurikulum
            && $user->school_id !== null
            && hash_equals($user->school_id, $school->id);
    }
}
