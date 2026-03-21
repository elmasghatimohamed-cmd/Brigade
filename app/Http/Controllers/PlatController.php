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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'categories' => 'sometimes|array',
            'categories.*' => 'integer|exists:categories,id',
        ]);

        $data = $request->only('name', 'description', 'price');

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('plats', 'public');
            $data['image'] = $imagePath;
        }

        $plat = Plat::create($data);

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
        $this->authorize('update', $plat);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'categories' => 'sometimes|array',
            'categories.*' => 'integer|exists:categories,id',
        ]);

        $data = $request->only('name', 'description', 'price');

        if ($request->hasFile('image')) {
            if ($plat->image) {
                Storage::disk('public')->delete($plat->image);
            }
            $imagePath = $request->file('image')->store('plats', 'public');
            $data['image'] = $imagePath;
        }

        $plat->update($data);

        if ($request->has('categories')) {
            $plat->categories()->sync($request->categories);
        }

        return response()->json($plat->load('categories'));
    }

    public function destroy($id)
    {
        $plat = Plat::findOrFail($id);
        $this->authorize('delete', $plat);

        if ($plat->image) {
            Storage::disk('public')->delete($plat->image);
        }

        $plat->categories()->detach();
        $plat->delete();

        return response()->json(['message' => 'Plat supprimé']);
    }

}