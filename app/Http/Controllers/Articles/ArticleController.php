<?php

namespace App\Http\Controllers\Articles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

// Models - tables
use App\Models\Article;

class ArticleController extends Controller
{
    protected $cacheOneArticleKey = 'dashboard:article:';
    protected $cacheDraftListArticlesKey = 'dashboard:articles:draft';
    protected $cachePublishListArticlesKey = 'dashboard:articles:publish';

    public function getArticles(Request $request) {
        $isDraft = $request->query('is_draft');
        $selectedArticleColumns = ['title', 'slug', 'is_draft', 'category_id'];
        $query = Article::where('user_id', auth()->user()->id)
            ->with('category:id,name')
            ->orderBy('id', 'DESC');

        if ($isDraft) {
            $validatedIsDraft = filter_var($isDraft, FILTER_VALIDATE_BOOLEAN);
            $query = $query->where('is_draft', $validatedIsDraft);

            // save to cache
            $cacheKey = $validatedIsDraft
                ? $this->cacheDraftListArticlesKey
                : $this->cachePublishListArticlesKey;

            if (Redis::exists($cacheKey)) {
                $articles = Redis::get($cacheKey);
                $articles = json_decode($articles);
            } else {
                $articles = $query->get($selectedArticleColumns);
                Redis::set($cacheKey, json_encode($articles->toArray()));
            }

            return $this->successfulResponseJSON(null, [
                'articles_count' => count($articles),
                'articles' => $articles
            ]);
        }

        return $this->failedResponseJSON('The is_draft query value was not found', 404);
    }

    public function getOneArticle(Article $article) {
        if (auth()->user()->is_admin or ($article->user_id === auth()->user()->id)) {
            if (Redis::exists($this->cacheOneArticleKey . $article->id)) {
                // get from cache
                $article = Redis::get($this->cacheOneArticleKey . $article->id);
                $article = json_decode($article);
            } else {
                $article = Article::with(['category', 'user:id,name,username,email'])
                    ->find($article->id);

                // save to cache
                Redis::set(
                    ($this->cacheOneArticleKey . $article->id), json_encode($article)
                );
            }
            return $this->successfulResponseJSON(null, $article);
        }

        return $this->failedResponseJSON('Article not found', 404);
    }

    public function deleteArticle(Article $article) {
        if (auth()->user()->is_admin or ($article->user_id === auth()->user()->id)) {
            DB::beginTransaction();
            $delete = Article::where('id', $article->id)->delete();

            if ($delete) {
                DB::commit();

                // delete from cache
                Redis::del($this->cacheOneArticleKey . $article->id);
                Redis::del($this->cacheDraftListArticlesKey);
                Redis::del($this->cachePublishListArticlesKey);
                return $this->successfulResponseJSON('Article has been successfully deleted');
            }

            DB::rollBack();
            return $this->failedResponseJSON('Article failed to delete');
        }

        return $this->failedResponseJSON('Article not found', 404);
    }

    public function addArticle(Request $request) {
        /**
         * Tambah article baru, jika memiliki banyak image selain img_thumb
         * maka simpan img tersebut ke table terpisah dan relasikan dengan article yang dibuat
         * cukup simpan path imgnya saja
         *
         * Kemudian hapus cache draft dan publish list
         * tergantung pada nilai is_draft yang dikirimkan
         */
    }
}
