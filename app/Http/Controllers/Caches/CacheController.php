<?php

namespace App\Http\Controllers\Caches;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Redis;

class CacheController extends Controller
{
    public function flushAll() {
        Redis::flushall();
        return $this->successfulResponseJSON('Articles cache cleared successfully');
    }
}
