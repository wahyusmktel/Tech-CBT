<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Kurikulum && $user->school_id !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Exam $exam): bool
    {
        return $this->viewAny($user) && hash_equals($user->school_id, $exam->school_id);
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $this->update($user, $exam);
    }

    public function generateCredentials(User $user, Exam $exam): bool
    {
        return $this->update($user, $exam);
    }
}
