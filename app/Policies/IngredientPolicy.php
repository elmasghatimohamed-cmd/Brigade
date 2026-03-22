<?php

namespace App\Policies;

use App\Models\Ingredient;
use App\Models\User;

class IngredientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin_restaurant';
    }

    public function view(User $user, Ingredient $ingredient): bool
    {
        return $user->role === 'admin_restaurant';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin_restaurant';
    }

    public function update(User $user, Ingredient $ingredient): bool
    {
        return $user->role === 'admin_restaurant';
    }

    public function delete(User $user, Ingredient $ingredient): bool
    {
        return $user->role === 'admin_restaurant';
    }
}
