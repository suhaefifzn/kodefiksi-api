<?php

use App\Http\Controllers\Articles\ArticleController;
use App\Http\Controllers\Articles\PublicArticleController;
use App\Http\Controllers\Authentications\AuthenticationController;
use App\Http\Controllers\Categories\CategoryController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

// Authentications
Route::controller(AuthenticationController::class)
    ->prefix('authentications')
    ->group(function () {
        Route::post('', 'login');
        Route::delete('', 'logout')->middleware('auth.jwt');
        Route::get('/check', 'check')->middleware('auth.jwt');
    });

// Users
Route::controller(UserController::class)
    ->prefix('users')
    ->group(function () {
        // all roles
        Route::prefix('my')
            ->middleware('auth.jwt')
            ->group(function () {
                Route::get('/profile', 'getProfile');
                Route::put('/profile', 'updateProfile');
                Route::put('/password', 'updatePassword');
                Route::post('/image', 'updateImage');
            });

        // admin only
        Route::middleware('auth.admin')
            ->group(function () {
                Route::get('', 'getUsers');
                Route::post('', 'addUser');
                Route::get('/{user:username}', 'getOneUser');
                Route::delete('/{user:username}', 'deleteUser');
                Route::put('/{user:username}', 'updateProfileUser');
                Route::put('/{user:username}/password', 'updatePasswordUser');
            });
    });

// Categories
Route::controller(CategoryController::class)
    ->prefix('categories')
    ->group(function () {
        // admin
        Route::middleware('auth.admin')
            ->group(function () {
                Route::post('', 'addCategory');
                Route::put('/{category:slug}', 'updateCategory');
                Route::delete('/{category:slug}', 'deleteCategory');
            });

        // all roles
        Route::middleware('auth.jwt')
            ->group(function () {
                Route::get('', 'getCategories');
                Route::get('/{category:slug}', 'getOneCategory');
            });
    });

// Articles
Route::controller(ArticleController::class)
    ->prefix('articles')
    ->group(function () {
        // public
        Route::controller(PublicArticleController::class)
            ->middleware('auth.client')
            ->prefix('public')
            ->group(function () {
                Route::get('', 'getArticles');
                Route::get('/{article:slug}', 'getOneArticle');
            });

        // members
        Route::middleware('auth.jwt')
            ->group(function () {
                Route::get('', 'getArticles');
                Route::post('', 'addArticle');
                Route::post('/generate-slug', 'generateSlug');
                Route::post('/upload-image', 'addImage');
                Route::get('/images', 'getAllBodyImages');
                Route::get('/{article:slug}', 'getOneArticle');
                Route::put('/{article:slug}', 'editArticle');
                Route::delete('/{article:slug}', 'deleteArticle');
            });
    });
