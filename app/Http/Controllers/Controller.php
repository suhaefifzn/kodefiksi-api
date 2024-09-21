<?php

namespace App\Http\Controllers;

abstract class Controller
{
    // dashboard cache key
    private $dashboardCacheOneArticleKey = 'dashboard:article:';
    private $dashboardCacheDraftListArticlesKey = 'dashboard:articles:draft';
    private $dashboardCachePublishListArticlesKey = 'dashboard:articles:publish';

    // public cache key
    private $publicCacheArticlesPerPaginationKey = 'public:articles:page:';
    private $publicCacheArticlesPerCategoryKey = 'public:articles:category:';
    private $publicCacheArticlesPerAuthorKey = 'public:articles:author:';
    private $publicCacheOneArticleKey = 'public:article:';
    private $publicCacheAllArticlesKey = 'public:articles:all';

    protected function successfulResponseJSON($message = null, $data = null, $code = 200) {
        $response = [
            'status' => 'success',
        ];

        if (is_null($data)) {
            $response['message'] = $message;
            return response()->json($response, $code);
        }

        $response['data'] = $data;
        return response()->json($response, $code);
    }

    protected function failedResponseJSON($message = null, $code = 500) {
        $response = [
            'status' => 'fail',
            'message' => $message,
        ];

        return response()->json($response, $code);
    }

    protected function getKeyDashboardCacheOneArticle($articleId = '') {
        return $this->dashboardCacheOneArticleKey . $articleId;
    }

    protected function getKeyDashboardDraftArticles() {
        return $this->dashboardCacheDraftListArticlesKey;
    }

    protected function getKeyDashboardPublishedArticles() {
        return $this->dashboardCachePublishListArticlesKey;
    }

    protected function getKeyPublicOneArticle($articleSlug = '') {
        return $this->publicCacheOneArticleKey . $articleSlug;
    }

    protected function getKeyPublicArticlesPerPagination($pageNumber = '') {
        return $this->publicCacheArticlesPerPaginationKey . $pageNumber;
    }

    protected function getKeyPublicArticlesPerCategory($categorySlug = '', $pageNumber = '') {
        return $this->publicCacheArticlesPerCategoryKey . $categorySlug . ':' . $pageNumber;
    }

    protected function getKeyPublicArticlesPerAuthor($username = '', $pageNumber = '') {
        return $this->publicCacheArticlesPerAuthorKey . $username . ':' . $pageNumber;
    }

    protected function getKeyPublicAllArticles() {
        return $this->publicCacheAllArticlesKey;
    }
}
