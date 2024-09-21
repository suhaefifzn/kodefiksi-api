<?php

namespace App\Http\Controllers\Articles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

// Models - tables
use App\Models\Article;
use App\Models\ArticleImage;

// Custom rules
use App\Rules\MinWordsRule;
use App\Rules\TextToBooleanRule;

class ArticleController extends Controller
{
    private $cacheOneArticleKey;
    private $cacheDraftListArticlesKey;
    private $cachePublishListArticlesKey;

    public function __construct() {
        $this->cacheOneArticleKey = parent::getKeyDashboardCacheOneArticle();
        $this->cacheDraftListArticlesKey = parent::getKeyDashboardDraftArticles();
        $this->cachePublishListArticlesKey = parent::getKeyDashboardPublishedArticles();
    }

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
                Redis::flushall();
                return $this->successfulResponseJSON('Article has been successfully deleted');
            }

            DB::rollBack();
            return $this->failedResponseJSON('Article failed to delete');
        }

        return $this->failedResponseJSON('Article not found', 404);
    }

    public function addArticle(Request $request) {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|min:10|max:255',
            'slug' => 'nullable|string|exists:articles,slug',
            'img_thumbnail' => 'required|file|mimes:png,jpg|max:2048',
            'is_draft' => ['required', 'string', new TextToBooleanRule()],
            'body' => ['required', 'string', new MinWordsRule(350)]
        ]);

        $requestData = self::setRequestData($request);

        return self::store($requestData, $request->file('img_thumbnail'));
    }

    public function editArticle(Request $request, Article $article) {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|min:10|max:255',
            'img_thumbnail' => 'required|file|mimes:png,jpg|max:2048',
            'is_draft' => ['required', 'string', new TextToBooleanRule()],
            'body' => ['required', 'string', new MinWordsRule(350)]
        ]);

        $requestData = self::setRequestData($request);

        if (auth()->user()->id === $article->user_id) {
            return self::update(
                $requestData, $article->id, $article->img_thumbnail, $request->file('img_thumbnail')
            );
        }

        return $this->failedResponseJSON('Sorry, the action failed because you are not the owner', 403);
    }

    public function generateSlug(Request $request) {
        $request->validate([
            'title' => 'required|string|min:10|max:255',
        ]);

        $slug = Str::slug($request->title, '-');
        $originalSlug = $slug;
        $count = 1;

        while(Article::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $this->successfulResponseJSON(null, [
            'slug' => $slug
        ]);
    }

    /**
     * This function is used to add images within
     * the body of an article. Each time an image successfully added,
     * it must provide a response containing the image's path
     * because, on the front end, a list will be created for each
     * successfully uploaded image (in jquery data table)
     */
    public function addImage(Request $request) {
        $request->validate([
            'image' => 'required|file|mimes:png,jpg|max:2048'
        ]);
        $image = $request->file('image');
        $imageName = $image->hashName();
        $image->storeAs('public/images/articles', $imageName);
        $responseData = [
            'image' => config('app.url') . '/storage/images/articles/' . $imageName
        ];

        DB::beginTransaction();
        $create = ArticleImage::create([
            'user_id' => auth()->user()->id,
            'path' => 'storage/images/articles/' . $imageName
        ]);

        if ($create) {
            DB::commit();
            return $this->successfulResponseJSON(null, $responseData);
        }

        DB::rollBack();
        return $this->failedResponseJSON('Image failed to upload');
    }

    public function getAllBodyImages() {
        $articleImages = ArticleImage::where('user_id', auth()->user()->id)
            ->orderBy('created_at', 'DESC')
            ->get(['path']);

        return $this->successfulResponseJSON(null, [
            'article_images' => $articleImages
        ]);
    }

    private function setRequestData(mixed $request) {
        $isDraft = filter_var($request->is_draft, FILTER_VALIDATE_BOOLEAN);
        $requestData = $request->all();
        $requestData['category_id'] = (int) $request->category_id;
        $requestData['is_draft'] = $isDraft;
        $requestData['excerpt'] = Str::excerpt(strip_tags($request->body));
        unset($requestData['img_thumbnail']);
        unset($requestData['_method']);
        return $requestData;
    }

    private function update(mixed $requestData, int $articleId, string $pathOldImgThumbnail, $newImgThumbnail) {
        DB::beginTransaction();
        unset($requestData['slug']);
        $pathImageThumbnail = self::storeImageThumbnail($newImgThumbnail, $pathOldImgThumbnail);
        $requestData['img_thumbnail'] = $pathImageThumbnail;
        $update = Article::where('id', $articleId)
            ->update($requestData);

        if ($update) {
            DB::commit();
            Redis::flushall();
            return $this->successfulResponseJSON('Article has been successfully updated');
        }

        DB::rollBack();
        return $this->failedResponseJSON('Article failed to update');
    }

    private function store(mixed $requestData, $newImgThumbnail) {
        DB::beginTransaction();
        $requestData['user_id'] = auth()->user()->id;
        $pathImageThumbnail = self::storeImageThumbnail($newImgThumbnail);
        $requestData['img_thumbnail'] = $pathImageThumbnail;
        $create = Article::create($requestData);

        if ($create) {
            DB::commit();
            Redis::flushall();
            return $this->successfulResponseJSON('Article has been successfully created');
        }

        DB::rollBack();
        return $this->failedResponseJSON('Article failed to create');
    }

    private function storeImageThumbnail($newImgThumbnail, string $pathOldImgThumbnail = null) {
        if (!is_null($pathOldImgThumbnail)) {
            $explodedPath = explode('/', $pathOldImgThumbnail);
            unset($explodedPath[0]);
            Storage::delete('public/'. (implode('/', array_values($explodedPath))));
        }

        $image = $newImgThumbnail;
        $imageName = $image->hashName();
        $image->storeAs('public/images/articles', $imageName);

        return 'storage/images/articles/' . $imageName;
    }
}
