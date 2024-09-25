<?php

namespace App\Http\Controllers\Authentications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticationController extends Controller
{
    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $credentials = $request->only('email', 'password');
        $token = auth()->attempt($credentials);

        if ($token) {
            $data = [
                'token' => [
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth()->factory()->getTTL() . ' minutes'
                ]
            ];

            return $this->successfulResponseJSON(null, $data, 201);
        }

        return $this->failedResponseJSON('Oops, your credentials does not match our records', 404);
    }

    public function logout() {
        $token = JWTAuth::getToken()->get();

        if ($token) {
            JWTAuth::invalidate(true);
        }

        auth()->logout();
        return $this->successfulResponseJSON('The access token has been successfully invalidated');
    }

    public function check() {
        $token = JWTAuth::getToken()->get();
        return $this->successfulResponseJSON(null, [
            'token' => $token
        ]);
    }
}
