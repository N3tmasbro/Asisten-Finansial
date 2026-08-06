<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List all categories available to the user (defaults + custom).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $categories = Category::forUser($user->id)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Create a custom category for the user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'type' => 'required|in:expense,income',
            'icon' => 'nullable|string|max:10',
        ]);

        $user = $request->user();

        // Check for duplicate name
        $exists = Category::where('user_id', $user->id)
            ->where('name', $validated['name'])
            ->where('type', $validated['type'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Kategori dengan nama ini sudah ada.',
            ], 422);
        }

        $category = Category::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'icon' => $validated['icon'] ?? '📌',
            'is_default' => false,
            'sort_order' => 50,
        ]);

        return response()->json([
            'message' => 'Kategori berhasil ditambahkan.',
            'category' => $category,
        ], 201);
    }

    /**
     * Update a custom category.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $category = Category::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:50',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'sometimes|integer',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'category' => $category,
        ]);
    }

    /**
     * Delete a custom category (cannot delete defaults).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $category = Category::where('user_id', $user->id)->findOrFail($id);

        if ($category->is_default) {
            return response()->json([
                'message' => 'Kategori default tidak bisa dihapus.',
            ], 403);
        }

        // Check if category has transactions
        if ($category->transactions()->exists()) {
            return response()->json([
                'message' => 'Kategori ini memiliki transaksi. Pindahkan transaksi ke kategori lain terlebih dahulu.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }
}
