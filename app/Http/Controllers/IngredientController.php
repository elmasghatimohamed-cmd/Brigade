<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;

class IngredientController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Ingredient::class);
        
        $ingredients = Ingredient::orderBy('name')->get();
        return response()->json($ingredients);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ingredient::class);

        $request->validate([
            'name' => 'required|string|max:255|unique:ingredients,name',
            'tags' => 'array',
            'tags.*' => 'in:contains_meat,contains_sugar,contains_cholesterol,contains_gluten,contains_lactose',
        ]);

        $ingredient = Ingredient::create($request->only('name', 'tags'));

        return response()->json($ingredient, 201);
    }

    public function show($id)
    {
        $ingredient = Ingredient::findOrFail($id);
        $this->authorize('view', $ingredient);
        
        return response()->json($ingredient);
    }

    public function update(Request $request, $id)
    {
        $ingredient = Ingredient::findOrFail($id);
        $this->authorize('update', $ingredient);

        $request->validate([
            'name' => 'required|string|max:255|unique:ingredients,name,' . $id,
            'tags' => 'array',
            'tags.*' => 'in:contains_meat,contains_sugar,contains_cholesterol,contains_gluten,contains_lactose',
        ]);

        $ingredient->update($request->only('name', 'tags'));

        return response()->json($ingredient);
    }

    public function destroy($id)
    {
        $ingredient = Ingredient::findOrFail($id);
        $this->authorize('delete', $ingredient);

        // Check if ingredient is used by any plates
        if ($ingredient->plats()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete ingredient: it is used by one or more plates'
            ], 422);
        }

        $ingredient->delete();

        return response()->json(['message' => 'Ingredient deleted successfully']);
    }
}
