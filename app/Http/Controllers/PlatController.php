<?php

namespace App\Http\Controllers;

use App\Models\Plat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Auth\Access\AuthorizationException;

class PlatController extends Controller
{
    public function store(Request $request)
    {
        $this->authorize('create', Plat::class);

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_available' => 'boolean',
            'category_id' => 'required|integer|exists:categories,id',
            'ingredient_ids' => 'array',
            'ingredient_ids.*' => 'integer|exists:ingredients,id',
        ]);

        $data = $request->only('name', 'description', 'price', 'is_available', 'category_id');
        $data['is_available'] = $request->get('is_available', true);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('plats', 'public');
            $data['image'] = $imagePath;
        }

        $plat = Plat::create($data);

        if ($request->has('ingredient_ids')) {
            $plat->ingredients()->sync($request->ingredient_ids);
        }

        return response()->json($plat->load('category', 'ingredients'), 201);
    }

    public function index()
    {
        $plats = Plat::with('category', 'ingredients')
            ->get()
            ->map(function ($plat) {
                $plat->recommendation_score = $this->calculateRecommendationScore($plat);
                return $plat;
            });
        
        return response()->json($plats);
    }

    public function show($id)
    {
        $plat = Plat::with('category', 'ingredients')->findOrFail($id);
        $plat->recommendation_score = $this->calculateRecommendationScore($plat);
        $plat->recommendation_details = $this->getRecommendationDetails($plat);
        
        return response()->json($plat);
    }

    public function update(Request $request, $id)
    {
        $plat = Plat::findOrFail($id);
        $this->authorize('update', $plat);

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_available' => 'boolean',
            'category_id' => 'required|integer|exists:categories,id',
            'ingredient_ids' => 'array',
            'ingredient_ids.*' => 'integer|exists:ingredients,id',
        ]);

        $data = $request->only('name', 'description', 'price', 'is_available', 'category_id');
        if ($request->has('is_available')) {
            $data['is_available'] = $request->get('is_available');
        }

        if ($request->hasFile('image')) {
            if ($plat->image) {
                Storage::disk('public')->delete($plat->image);
            }
            $imagePath = $request->file('image')->store('plats', 'public');
            $data['image'] = $imagePath;
        }

        $plat->update($data);

        if ($request->has('ingredient_ids')) {
            $plat->ingredients()->sync($request->ingredient_ids);
        }

        return response()->json($plat->load('category', 'ingredients'));
    }

    public function destroy($id)
    {
        $plat = Plat::findOrFail($id);
        $this->authorize('delete', $plat);

        if ($plat->image) {
            Storage::disk('public')->delete($plat->image);
        }

        $plat->ingredients()->detach();
        $plat->delete();

        return response()->json(['message' => 'Plat supprimé']);
    }

    private function calculateRecommendationScore($plat)
    {
        $score = 0;
        
        // Base score for availability
        if ($plat->is_available) {
            $score += 40;
        }
        
        // Price factor (lower price gets higher score)
        if ($plat->price > 0) {
            $score += max(0, 30 - ($plat->price / 2));
        }
        
        // Category popularity (you can customize this logic)
        if ($plat->category) {
            $score += 20;
        }
        
        // Ingredient variety
        if ($plat->ingredients && $plat->ingredients->count() > 0) {
            $score += min(10, $plat->ingredients->count() * 2);
        }
        
        return min(100, max(0, $score));
    }

    private function getRecommendationDetails($plat)
    {
        return [
            'availability_score' => $plat->is_available ? 40 : 0,
            'price_score' => max(0, 30 - ($plat->price / 2)),
            'category_score' => $plat->category ? 20 : 0,
            'ingredient_score' => $plat->ingredients ? min(10, $plat->ingredients->count() * 2) : 0,
            'total_score' => $this->calculateRecommendationScore($plat)
        ];
    }

}