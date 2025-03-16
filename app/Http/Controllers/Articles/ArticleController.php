<?php

namespace App\Http\Controllers\Articles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

// Models - tables
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Category;
use App\Models\Language;

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
        $this->cacheDraftListArticlesKey = parent::getKeyDashboardDraftArticles(auth()->user()->id);
        $this->cachePublishListArticlesKey = parent::getKeyDashboardPublishedArticles(auth()->user()->id);
    }

    public function getArticles(Request $request) {
        $isDraft = $request->query('is_draft');
        $selectedArticleColumns = ['title', 'slug', 'is_draft', 'created_at', 'updated_at', 'category_id', 'user_id'];
        $query = Article::where('user_id', auth()->user()->id)
            ->with(['category:id,name', 'user:id,username'])
            ->orderBy('updated_at', 'DESC');

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
            'lang_id' => 'required|exists:languages,id',
            'title' => 'required|string|min:10|max:255',
            'slug' => 'nullable|string|unique:articles,slug',
            'img_thumbnail' => 'required|file|mimes:png,jpg,webp|max:2048',
            'is_draft' => ['required', 'string', new TextToBooleanRule()],
            'excerpt' => 'required|string|min:140|max:200',
            'body' => ['required', 'string', new MinWordsRule(350)]
        ]);

        $requestData = self::setRequestData($request);

        return self::store($requestData, $request->file('img_thumbnail'));
    }

    public function editArticle(Request $request, Article $article) {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'lang_id' => 'required|exists:languages,id',
            'title' => 'required|string|min:10|max:255',
            'img_thumbnail' => 'nullable|file|mimes:png,jpg,webp|max:2048',
            'is_draft' => ['required', 'string', new TextToBooleanRule()],
            'excerpt' => 'required|string|min:140|max:200',
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
            'image' => 'required|file|mimes:png,jpg,webp|max:2048'
        ]);
        $image = $request->file('image');
        $imageName = $image->hashName();
        $destinationPath = realpath(__DIR__ . '/../../../../../../public_html/api.kodefiksi.com/images/articles/');

        if ($destinationPath === false) {
            $destinationPath = __DIR__ . '/../../../../../../public_html/api.kodefiksi.com/images/articles/';
        }

        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        $image->move($destinationPath, $imageName);

        $responseData = [
            'image' => config('app.url') . '/images/articles/' . $imageName
        ];

        DB::beginTransaction();
        $create = ArticleImage::create([
            'user_id' => auth()->user()->id,
            'path' => 'images/articles/' . $imageName
        ]);

        if ($create) {
            DB::commit();
            return $this->successfulResponseJSON('Image has been successfully uploaded', $responseData);
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

    public function getStats() {
        $categoriesStats = Category::select('id', 'name', 'slug')
            ->withCount(['articles' => function ($query) {
                $query->where('user_id', auth()->user()->id);
            }])->get();
        $lastArticle = Article::select('id', 'title', 'slug', 'is_draft', 'excerpt', 'created_at', 'updated_at')
            ->where('user_id', auth()->user()->id)
            ->orderBy('created_at', 'DESC')
            ->first();

        $response = [
            'stats' => $categoriesStats,
            'last_article' => $lastArticle
        ];

        return $this->successfulResponseJSON(null, $response);
    }

    public function getSlugs(Request $request) {
        $languageExists = Language::where('code', $request->query('lang'))->first();

        if ($languageExists) {
            $slugs = Article::where('is_draft', 'false')
                ->where('lang_id', $languageExists->id)
                ->select('id', 'slug', 'updated_at')
                ->orderBy('updated_at', 'DESC')
                ->get();

            return $this->successfulResponseJSON(null, [
                'slugs' => $slugs
            ]);
        }

        return $this->failedResponseJSON('Language code not found', 404);
    }

    private function setRequestData(mixed $request) {
        $isDraft = filter_var($request->is_draft, FILTER_VALIDATE_BOOLEAN);
        $requestData = $request->all();
        $requestData['category_id'] = (int) $request->category_id;
        $requestData['is_draft'] = $isDraft;

        unset($requestData['img_thumbnail']);
        unset($requestData['_method']);

        return $requestData;
    }

    private function update(mixed $requestData, int $articleId, string $pathOldImgThumbnail, $newImgThumbnail) {
        DB::beginTransaction();
        unset($requestData['slug']);

        if ($newImgThumbnail) {
            $pathImageThumbnail = self::storeImageThumbnail($newImgThumbnail, $pathOldImgThumbnail);
            $requestData['img_thumbnail'] = $pathImageThumbnail;
        } else {
            unset($requestData['img_thumbnail']);
        }

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

            // get all articles and save to cache for search features
            $articles = Article::orderBy('created_at', 'DESC')
                ->where('is_draft', false)
                ->select(['title', 'slug', 'img_thumbnail','created_at', 'updated_at', 'category_id', 'user_id', 'lang_id', 'excerpt'])
                ->with(['category:id,name,slug', 'user:id,name,username'])
                ->get();
            $encodedArticles = json_encode($articles);
            Redis::set(parent::getKeyPublicAllArticles(), $encodedArticles);

            return $this->successfulResponseJSON('Article has been successfully created');
        }

        DB::rollBack();
        return $this->failedResponseJSON('Article failed to create');
    }

    private function storeImageThumbnail($newImgThumbnail, string $pathOldImgThumbnail = null) {
        if (!is_null($pathOldImgThumbnail)) {
            $explodedPath = explode('/', $pathOldImgThumbnail);
            $fileImage = end($explodedPath); // filename at last index
            $oldImagePath = __DIR__ . '/../../../../../../public_html/api.kodefiksi.com/images/articles/' . $fileImage;

            if (File::exists($oldImagePath)) {
                File::delete($oldImagePath);
            }
        }

        // save
        $image = $newImgThumbnail;
        $imageName = $image->hashName();
        $destinationPath = realpath(__DIR__ . '/../../../../../../public_html/api.kodefiksi.com/images/articles/');

        if ($destinationPath === false) {
            $destinationPath = __DIR__ . '/../../../../../../public_html/api.kodefiksi.com/images/articles/';
        }

        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        $image->move($destinationPath, $imageName);

        // get path
        $imagePath = config('app.url') . '/images/articles/' . $imageName;

        return $imagePath;
    }
}
