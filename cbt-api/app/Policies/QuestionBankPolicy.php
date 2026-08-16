<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QuestionBank;
use App\Models\User;

class QuestionBankPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Kurikulum && $user->school_id !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, QuestionBank $bank): bool
    {
        return $this->viewAny($user) && hash_equals($user->school_id, $bank->school_id);
    }

    public function update(User $user, QuestionBank $bank): bool
    {
        return $this->view($user, $bank);
    }

    public function delete(User $user, QuestionBank $bank): bool
    {
        return $this->view($user, $bank);
    }

    public function import(User $user, QuestionBank $bank): bool
    {
        return $this->view($user, $bank);
    }

    public function validate(User $user, QuestionBank $bank): bool
    {
        return $this->view($user, $bank);
    }
}
