<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Kurikulum && $user->school_id !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Student $student): bool
    {
        return $this->owns($user, $student);
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->owns($user, $student);
    }

    public function import(User $user): bool
    {
        return $this->viewAny($user);
    }

    private function owns(User $user, Student $student): bool
    {
        return $this->viewAny($user) && hash_equals($user->school_id, $student->school_id);
    }
}
