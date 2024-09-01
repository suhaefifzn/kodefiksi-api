<?php

namespace App\Http\Middleware;

use App\Exceptions\MyCustomErrorHandler;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() and JWTAuth::parseToken()->authenticate()) {
            return $next($request);
        }

        return response()->json([
            'status' => 'fail',
            'error' => 'Token',
            'message' =>'Access token could not be found'
        ], 401);
    }
}
