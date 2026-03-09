<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Plat;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // Créer une catégorie
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        $category = Category::create(['name' => $request->name]);

        return response()->json($category, 201);
    }

    // Lister toutes les catégories
    public function index()
    {
        $categories = Category::with('plats')->get();
        return response()->json($categories);
    }

    // Afficher une catégorie spécifique
    public function show($id)
    {
        $category = Category::with('plats')->findOrFail($id);
        return response()->json($category);
    }

    // Modifier une catégorie
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
        ]);

        $category->update(['name' => $request->name]);

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
}