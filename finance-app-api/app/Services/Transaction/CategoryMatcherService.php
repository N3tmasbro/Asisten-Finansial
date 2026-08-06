<?php

namespace App\Services\Transaction;

use App\Models\Category;

class CategoryMatcherService
{
    /**
     * Match a category name from AI to an actual Category model.
     * Falls back to "Lainnya" if no match found.
     */
    public function match(string $categoryName, int $userId, string $type = 'expense'): Category
    {
        // Try exact match (user custom categories first)
        $category = Category::where('name', $categoryName)
            ->where('type', $type)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereNull('user_id');
            })
            ->orderByRaw('user_id IS NULL ASC') // Prefer user custom categories
            ->first();

        if ($category) {
            return $category;
        }

        // Try fuzzy match (case-insensitive, partial)
        $category = Category::where('type', $type)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereNull('user_id');
            })
            ->where('name', 'LIKE', '%' . $categoryName . '%')
            ->first();

        if ($category) {
            return $category;
        }

        // Fallback to "Lainnya"
        return Category::where('name', 'Lainnya')
            ->where('type', $type)
            ->whereNull('user_id')
            ->where('is_default', true)
            ->firstOrFail();
    }

    /**
     * Get all category names for a user (for AI prompt injection).
     */
    public function getCategoryNamesForUser(int $userId, ?string $type = null): array
    {
        $query = Category::forUser($userId);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('sort_order')->pluck('name')->toArray();
    }
}
