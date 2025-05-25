<?php

namespace App\Http\Controllers\Articles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Models\ArticleType;

class ArticleTypeController extends Controller
{
    public function getAll() {
        $articleTypes = ArticleType::select('id', 'name')->get();

        return $this->successfulResponseJSON(null, [
            'article_types' => $articleTypes
        ]);
    }
}
