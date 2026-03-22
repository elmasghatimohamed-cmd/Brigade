<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Plat;
use Illuminate\Http\Request;

class CategoryController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'description' => 'nullable|string',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean'
        ]);

        $category = Category::create([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->color ?? '#000000',
            'is_active' => $request->is_active ?? true
        ]);

        return response()->json($category, 201);
    }

    public function index()
    {
        $categories = Category::where('is_active', true)->get();
        return response()->json($categories);
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    // Modifier une catégorie
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100|unique:categories,name,' . $id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean'
        ]);

        $category->update([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->color ?? $category->color,
            'is_active' => $request->is_active ?? $category->is_active
        ]);

        return response()->json($category);
    }

    // Supprimer une catégorie
    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée']);
    }

    // Associer des plats à une catégorie
    public function addPlats(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'plats' => 'required|array',
            'plats.*' => 'integer|exists:plats,id',
        ]);

        $category->plats()->syncWithoutDetaching($request->plats);

        return response()->json($category->plats);
    }

    public function getPlats($id)
    {
        $category = Category::findOrFail($id);

        try {
            $plats = $category->plats()->where('is_available', true)->get();
            return response()->json($plats);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Aucun plat trouvé pour cette catégorie',
                'plats' => []
            ], 200);
        }
    }
}