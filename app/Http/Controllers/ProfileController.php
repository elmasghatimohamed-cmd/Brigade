<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'dietary_tags' => $user->dietary_tags ?? [],
            'role' => $user->role
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'dietary_tags' => 'array|nullable',
            'dietary_tags.*' => 'in:vegan,no_sugar,no_cholesterol,gluten_free,no_lactose'
        ]);

        $user = $request->user();
        $user->dietary_tags = $request->dietary_tags ?? [];
        $user->save();

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'dietary_tags' => $user->dietary_tags
        ]);
    }
}
