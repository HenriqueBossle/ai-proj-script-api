<?php

namespace App\Policies;

use App\Models\Script;
use App\Models\User;

class ScriptPolicy
{
    /**
     * Create a new policy instance.
     */
    
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Script $script): bool
    {
        return $user->id === $script->user_id;

    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Script $script): bool
    {
        return $user->id === $script->user_id;

    }

    public function delete(User $user, Script $script): bool
    {
        return $user->id === $script->user_id;
    }
}
