<?php

namespace App\Http\Controllers\Articles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

// ? Models - tables
use App\Models\Article;
use App\Models\Category;
use App\Models\User;

class PublicArticleController extends Controller
{
    private $selectedColumns = ['title', 'slug', 'img_thumbnail','created_at', 'updated_at', 'category_id', 'user_id', 'excerpt'];
    private $withTables = ['category:id,name,slug', 'user:id,name,username'];
    private $totalItemsPerPage = 9;

    public function getHomeArticles() {
        if (Redis::exists(parent::getKeyPublicHomeArticles())) {
            $articles = json_decode(Redis::get(parent::getKeyPublicHomeArticles()), true);
        } else {
            $latestArticle = Article::where('is_draft', false)
                ->select($this->selectedColumns)
                ->with($this->withTables)
                ->latest()->first();

            $categories = Category::with(['articles' => function ($query) use ($latestArticle) {
                $query->where('is_draft', false)
                    ->whereNot('slug', $latestArticle->slug)
                    ->select($this->selectedColumns)
                    ->with('user')->latest()->take(3);
            }])->get();

            $articles = [
                'articles' => [
                    'latest' => $latestArticle,
                    'per_categories' => $categories
                ]
            ];

            $encodedArticles = json_encode($articles);

            // save to cache
            Redis::set(parent::getKeyPublicHomeArticles(), $encodedArticles);
        }

        return $this->successfulResponseJSON(null, $articles);
    }

    public function getArticles(Request $request) {
        $validator = Validator::make($request->all(), [
            'search' => 'string|min:3|max:100|regex:/^[a-zA-Z0-9\s:?!,\.@#\$%&\*\-]+$/',
            'category' => 'string|min:3|max:100|regex:/^[a-zA-Z0-9\s:?!,\.@#\$%&\*\-]+$/',
            'username' => 'string|min:3|max:100|regex:/^[a-zA-Z0-9\s:?!,\.@#\$%&\*\-]+$/',
            'page' => 'integer|min:1|max:100|regex:/^[a-zA-Z0-9\s:?!,\.@#\$%&\*\-]+$/',
        ]);

        if ($validator->fails()) {
            return $this->failedResponseJSON('Not a valid value', 400);
        }

        if ($request->has('category') and $request->has('page')) {
            $category = htmlspecialchars($request->query('category'));
            return self::getByCategory($category, $request->query('page'));
        }

        if ($request->has('username') and $request->has('page')) {
            $username = htmlspecialchars($request->query('username'));
            return self::getByAuthor($username, $request->query('page'));
        }

        if ($request->has('search') and $request->has('page')) {
            $search = htmlspecialchars($request->query('search'));
            return self::getBySearch($search, $request->query('page'));
        }

        return parent::failedResponseJSON('Query was not found', 404);
    }

    public function getOneArticle(Article $article) {
        if (Redis::exists(parent::getKeyPublicOneArticle($article->slug))) {
            $article = json_decode(Redis::get(parent::getKeyPublicOneArticle($article->slug)), true);
        } else {
            $article = $article->where('slug', $article->slug)
                ->where('is_draft', false)
                ->with(['category:id,name,slug', 'user:id,name,username'])
                ->first();

            if ($article) {
                $article = $article->toArray();

                // get newest articles
                $newestArticles = Article::orderBy('created_at', 'DESC')
                    ->where('is_draft', false)
                    ->limit(3)
                    ->get(['slug', 'title', 'img_thumbnail', 'created_at']);

                $article['newest_articles'] = $newestArticles;

                unset($article['category_id']);
                unset($article['user_id']);

                // save to cache
                $encodedArticle = json_encode($article);
                Redis::set(parent::getKeyPublicOneArticle($article['slug']), $encodedArticle);
            } else {
                return $this->failedResponseJSON('Article was not found', 404);
            }
        }

        return $this->successfulResponseJSON(null, $article);
    }

    private function getBySearch($searchTerm, $pageNumber = 1) {
        $validatedValueQueryPage = filter_var($pageNumber, FILTER_VALIDATE_INT);

        if ($validatedValueQueryPage) {
            // Check and get from cache first
            if (Redis::exists(parent::getKeyPublicAllArticles())) {
                $articles = collect(json_decode(Redis::get(parent::getKeyPublicAllArticles()), true));

                // Filter articles based on the search term, only by title
                $filteredArticles = $articles->filter(function ($article) use ($searchTerm) {
                    return str_contains(strtolower($article['title']), strtolower($searchTerm));
                });

                // Pagination
                $currentPage = LengthAwarePaginator::resolveCurrentPage();
                $perPage = $this->totalItemsPerPage;
                $articlesPaginated = new LengthAwarePaginator(
                    $filteredArticles->forPage($currentPage, $perPage)->values(),
                    $filteredArticles->count(),
                    $perPage,
                    $currentPage,
                    ['path' => LengthAwarePaginator::resolveCurrentPath()]
                );

                $formattedResponse = self::setFormatResponse($articlesPaginated);

                return parent::successfulResponseJSON(null, $formattedResponse);
            } else {
                $searchTerm = strtolower($searchTerm);
                $query = Article::where('is_draft', false)
                    ->orderBy('created_at', 'DESC')
                    ->select($this->selectedColumns)
                    ->with($this->withTables);

                // get all, then save to cache
                Redis::set(parent::getKeyPublicAllArticles(), json_encode($query->get()));

                if (!empty($searchTerm)) {
                    $query->where(function ($q) use ($searchTerm) {
                        $q->whereRaw('LOWER(title) LIKE ?', ["%{$searchTerm}%"]);
                    });
                }

                $articles = $query->get();

                // pagination
                $currentPage = LengthAwarePaginator::resolveCurrentPage();
                $perPage = $this->totalItemsPerPage;
                $collectionArticles = collect($articles);

                $articlesPaginated = new LengthAwarePaginator(
                    $collectionArticles->forPage($currentPage, $perPage)->values(),
                    $collectionArticles->count(),
                    $perPage,
                    $currentPage,
                    ['path' => LengthAwarePaginator::resolveCurrentPath()]
                );

                $formattedResponse = self::setFormatResponse($articlesPaginated);

                return $this->successfulResponseJSON(null, $formattedResponse);
            }
        }

        return parent::failedResponseJSON('The value for the page query in the URL was not valid', 400);
    }

    private function getByCategory($categorySlug, $pageNumber = 1) {
        $validatedValueQueryPage = filter_var($pageNumber, FILTER_VALIDATE_INT);
        $categoryExists = Category::where('slug', $categorySlug)->first();

        if ($categoryExists) {
            if ($validatedValueQueryPage) {
                if (Redis::exists(
                    parent::getKeyPublicArticlesPerCategory($categoryExists['slug'], $validatedValueQueryPage)
                    )) {
                        $response = json_decode(Redis::get(parent::getKeyPublicArticlesPerCategory(
                            $categoryExists['slug'], $validatedValueQueryPage
                        )));
                    } else {
                        $articles = Article::where('category_id', $categoryExists['id'])
                            ->orderBy('created_at', 'DESC')
                            ->select($this->selectedColumns)
                            ->with($this->withTables)
                            ->where('is_draft', false)
                            ->paginate($this->totalItemsPerPage);

                        $response = self::setFormatResponse($articles);
                        $encodedArticles = json_encode($response);
                        Redis::set(
                            parent::getKeyPublicArticlesPerCategory($categoryExists['slug'], $pageNumber), $encodedArticles
                        );
                    }
                    return parent::successfulResponseJSON(null, $response);
            }
            return parent::failedResponseJSON('The value for the page query in the URL was not valid', 400);
        }
        return parent::failedResponseJSON('The value for the category query in the URL was not found', 404);
    }

    private function getByAuthor($username, $pageNumber = 1) {
        $validatedValueQueryPage = filter_var($pageNumber, FILTER_VALIDATE_INT);
        $userExists = User::where('username', $username)->first();

        if ($userExists) {
            if ($validatedValueQueryPage) {
                if (Redis::exists(
                    parent::getKeyPublicArticlesPerCategory($userExists['username'], $validatedValueQueryPage)
                    )) {
                        $response = json_decode(Redis::get(parent::getKeyPublicArticlesPerAuthor(
                            $userExists['username'], $validatedValueQueryPage
                        )));
                    } else {
                        $articles = Article::where('user_id', $userExists['id'])
                            ->orderBy('created_at', 'DESC')
                            ->select($this->selectedColumns)
                            ->with($this->withTables)
                            ->where('is_draft', false)
                            ->paginate($this->totalItemsPerPage);

                        $response = self::setFormatResponse($articles);
                        $encodedArticles = json_encode($response);
                        Redis::set(
                            parent::getKeyPublicArticlesPerAuthor($userExists['slug'], $pageNumber), $encodedArticles
                        );
                    }
                    return parent::successfulResponseJSON(null, $response);
            }
            return parent::failedResponseJSON('The value for the page query in the URL was not valid', 400);
        }
        return parent::failedResponseJSON('The value for the username query in the URL was not found', 404);
    }

    private function setFormatResponse($responseData) {
        $articlesArray = $responseData->toArray();
        $articlesArray['data'] = array_map(function($article) {
            unset($article['category_id'], $article['user_id']);
            return $article;
        }, $articlesArray['data']);

        $response = [
            'articles' => $articlesArray['data'],
            'meta' =>[
                'current_page' => $articlesArray['current_page'],
                'next_page_url' => $articlesArray['next_page_url'],
                'prev_page_url' => $articlesArray['prev_page_url'],
                'item_per_page' => $articlesArray['per_page'],
                'total_items' => $articlesArray['total']
            ]
        ];

        return $response;
    }
}
