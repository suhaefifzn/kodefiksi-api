<?php

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Models - tables
use App\Models\Category;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    // * Admin
    public function addCategory(Request $request) {
        $request->validate([
            'name' => 'required|string|unique:categories,name'
        ]);

        DB::beginTransaction();
        $create = Category::create([
            'name' => $request->name
        ]);

        if ($create) {
            DB::commit();
            return $this->successfulResponseJSON('New category has been successfully created');
        }

        DB::rollBack();
        return $this->failedResponseJSON('New category failed to update');
    }

    public function updateCategory(Request $request, Category $category) {
        $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('categories', 'name')->ignore($category->id)
            ],
        ]);

        DB::beginTransaction();
        $update = Category::where('id', $category->id)
            ->update([
                'name' => $request->name
            ]);

        if ($update) {
            DB::commit();
            return $this->successfulResponseJSON('Category has been successfully updated');
        }

        DB::rollBack();
        return $this->failedResponseJSON('Category failed to update');
    }

    public function deleteCategory(Category $category) {
        if ($category->articles->count() > 0) {
            return $this->failedResponseJSON(
                'Failed to delete the category. The category is used by several articles', 400
            );
        }

        DB::beginTransaction();
        $delete = Category::where('id', $category->id)->delete();

        if ($delete) {
            DB::commit();
            return $this->successfulResponseJSON('Category has been successfully deleted');
        }

        DB::rollBack();
        return $this->failedResponseJSON('Category failed to delete');
    }

    // * All Roles
    public function getCategories() {
        $categories = Category::select('name', 'slug')->get();
        return $this->successfulResponseJSON(null, [
            'categories' => $categories
        ]);
    }

    public function getOneCategory(Category $category) {
        /**
         * If the logged-in user is an admin,
         * retrieve the categories and their list of articles.
         *
         * If the logged-in user is a member,
         * retrieve the total number of articles in the category
         * and the list of their own articles along with the total.
         */
        $selectedUserColumns = 'user:id,name,username,email';
        $selectedArticleColumns = ['user_id', 'title', 'slug', 'is_draft'];
        $query = $category->articles()->with($selectedUserColumns);

        if (auth()->user()->is_admin) {
            $articles = $query->get($selectedArticleColumns);
        } else {
            $articles = $query->where('use_id', auth()->user()->id)
                ->get($selectedArticleColumns);
        }

        $category['articles_count'] = $articles->count();
        $category['articles'] = $articles;

        return $this->successfulResponseJSON(null, $category);
    }
}
