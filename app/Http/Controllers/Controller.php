<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function successfulResponseJSON($message = null, $data = null, $code = 200) {
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

    public function failedResponseJSON($message = null, $code = 500) {
        $response = [
            'status' => 'fail',
            'message' => $message,
        ];

        return response()->json($response, $code);
    }
}
