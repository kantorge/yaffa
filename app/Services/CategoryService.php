<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CategoryService
{
    public function delete(Category $category): array
    {
        if ($category->children()->exists() || $category->transactionItem()->exists()) {
            return [
                'success' => false,
                'error' => __('Category is in use, cannot be deleted'),
            ];
        }

        $success = false;
        $error = null;

        try {
            $success = (bool) $category->delete();

            if (! $success) {
                $error = __('Category could not be deleted');
            }
        } catch (Throwable $e) {
            report($e);
            $error = __('Database error:') . ' ' . $e->getMessage();
        }

        return [
            'success' => $success,
            'error' => $error,
        ];
    }

    public function getChildCategories(Request $request)
    {
        $categories = collect();

        if ($request->missing('categories')) {
            return $categories;
        }

        $requestedCategories = Auth::user()
            ->categories()
            ->whereIn('id', $request->get('categories'))
            ->get();

        $requestedCategories->each(function ($category) use (&$categories) {
            if ($category->parent_id === null) {
                $children = Auth::user()
                    ->categories()
                    ->where('parent_id', '=', $category->id)
                    ->get();
                $categories = $categories->concat($children);
            }

            $categories->push($category);
        });

        return $categories->unique('id');
    }
}
