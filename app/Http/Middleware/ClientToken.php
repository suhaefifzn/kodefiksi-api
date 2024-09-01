<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientToken = config('app.client.token');
        $requestClientToken = $request->header('Client-Token');

        if ($clientToken === $requestClientToken) {
            return $next($request);
        }

        return response()->json([
            'status' => 'fail',
            'error' => 'Token',
            'message' => 'Client token not found'
        ]);
    }
}
