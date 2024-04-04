<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryService
{
    public function delete(Category $category): array
    {
        $success = false;
        $error = null;

        try {
            $category->delete();
            $success = true;
        } catch (ModelNotFoundException $e) {
            $error = __('Category not found');
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1451) {
                $error = __('Category is in use, cannot be deleted');
            } else {
                $error = __('Database error:') . ' ' . $e->errorInfo[2];
            }
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
