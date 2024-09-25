<?php

namespace App\Http\Controllers\Articles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Pagination\LengthAwarePaginator;

// ? Models - tables
use App\Models\Article;
use App\Models\Category;
use App\Models\User;

class PublicArticleController extends Controller
{
    private $selectedColumns = ['title', 'slug', 'img_thumbnail','created_at', 'category_id', 'user_id', 'excerpt'];
    private $withTables = ['category:id,name,slug', 'user:id,name,username'];

    public function getArticles(Request $request) {
        if ($request->has('category') and $request->has('page')) {
            return self::getByCategory($request->query('category'), $request->query('page'));
        }

        if ($request->has('username') and $request->has('page')) {
            return self::getByAuthor($request->query('username'), $request->query('page'));
        }

        if ($request->has('search') and $request->has('page')) {
            return self::getBySearch($request->query('search'), $request->query('page'));
        }

        if ($request->has('page')) {
            return self::getByPagination($request->query('page'));
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
            $article = $article->toArray();

            // get related articles
            $relatedArticles = Article::where('category_id', $article['category_id'])
                ->orderBy('created_at', 'DESC')
                ->limit(3)
                ->get(['slug', 'title', 'img_thumbnail', 'excerpt']);

            // get newest articles
            $newestArticles = Article::orderBy('created_at', 'DESC')
                ->limit(5)
                ->get(['slug', 'title', 'img_thumbnail', 'created_at']);

            $article['related_articles'] = $relatedArticles;
            $article['newest_articles'] = $newestArticles;

            unset($article['category_id']);
            unset($article['user_id']);

            // save to cache
            $encodedArticle = json_encode($article);
            Redis::set(parent::getKeyPublicOneArticle($article['slug']), $encodedArticle);
        }

        return $this->successfulResponseJSON(null, $article);
    }

    private function getBySearch($searchTerm, $pageNumber = 1) {
        $validatedValueQueryPage = filter_var($pageNumber, FILTER_VALIDATE_INT);

        if ($validatedValueQueryPage) {
            if (Redis::exists(parent::getKeyPublicAllArticles())) {
                $articles = json_decode(Redis::get(parent::getKeyPublicAllArticles()), true);
            } else {
                $selectedColumns = $this->selectedColumns;
                array_push($selectedColumns, 'body');

                $query = Article::where('is_draft', false)
                    ->orderBy('created_at', 'DESC')
                    ->select($selectedColumns)
                    ->with($this->withTables);

                $articles = $query->orwhere('title', 'like', "%{$searchTerm}%")
                    ->orWhere('body', 'like', "%{$searchTerm}%")
                    ->get()
                    ->toArray();

                // save to cache
                Redis::set(parent::getKeyPublicAllArticles(), json_encode($articles));
            }

            $articles = array_map(function($article) {
                unset($article['category_id'], $article['user_id'], $article['body']);
                return $article;
            }, $articles);

            // pagination
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
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
        return parent::failedResponseJSON('The value for the page query in the URL was not valid', 400);
    }

    private function getByPagination($pageNumber) {
        $validatedValueQueryPage = filter_var($pageNumber, FILTER_VALIDATE_INT);

        if ($validatedValueQueryPage) {
            if (Redis::exists(parent::getKeyPublicArticlesPerPagination($validatedValueQueryPage))) {
                $response = json_decode(Redis::get(parent::getKeyPublicArticlesPerPagination($validatedValueQueryPage)));
            } else {
                $articles = Article::orderBy('created_at', 'DESC')
                    ->select($this->selectedColumns)
                    ->with($this->withTables)
                    ->where('is_draft', false)
                    ->paginate(10);

                    $response = self::setFormatResponse($articles);
                    $encodedArticles = json_encode($response);
                Redis::set(parent::getKeyPublicArticlesPerPagination($validatedValueQueryPage), $encodedArticles);
            }
            return parent::successfulResponseJSON(null, $response);
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
                            ->paginate(10);

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
                            ->paginate(10);

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
