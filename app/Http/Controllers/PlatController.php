<?php

namespace App\Http\Controllers;

use App\Models\Plat;
use Illuminate\Http\Request;

class PlatController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'categories' => 'sometimes|array',
            'categories.*' => 'integer|exists:categories,id',
        ]);

        $plat = Plat::create($request->only('name', 'description', 'price'));

        if ($request->has('categories')) {
            $plat->categories()->sync($request->categories);
        }

        return response()->json($plat->load('categories'), 201);
    }

    public function index()
    {
        return response()->json(Plat::with('categories')->get());
    }

    public function show($id)
    {
        $plat = Plat::with('categories')->findOrFail($id);
        return response()->json($plat);
    }

    public function update(Request $request, $id)
    {
        $plat = Plat::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'categories' => 'sometimes|array',
            'categories.*' => 'integer|exists:categories,id',
        ]);

        $plat->update($request->only('name', 'description', 'price'));

        if ($request->has('categories')) {
            $plat->categories()->sync($request->categories);
        }

        return response()->json($plat->load('categories'));
    }

    public function destroy($id)
    {
        $plat = Plat::findOrFail($id);
        $plat->categories()->detach();
        $plat->delete();

        return response()->json(['message' => 'Plat supprimé']);
    }

}