<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Plat;
use App\Models\Category;
use App\Models\Ingredient;
use App\Policies\PlatPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\IngredientPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Plat::class => PlatPolicy::class,
        Category::class => CategoryPolicy::class,
        Ingredient::class => IngredientPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
