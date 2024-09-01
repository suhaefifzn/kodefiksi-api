<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

// JWT Exception
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class MyCustomErrorHandler
{
    public static function handle(Exception $e, bool $isToken = false): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return self::handleValidationException($e);
        } else if ($e instanceof QueryException) {
            return self::handleQueryException($e);
        } else if ($e instanceof ModelNotFoundException) {
            return self::handleModelNotFoundException($e);
        } else if ($e instanceof HttpException) {
            return self::handleHttpException($e);
        } else if($e instanceof TokenInvalidException) {
            return self::handleTokenInvalidException($e);
        } else if ($e instanceof TokenExpiredException) {
            return self::handleTokenExpiredException($e);
        } else {
            return self::handleGenericException($e, $isToken);
        }
    }

    private static function setErrorResponse($errorType = 'Unknown', $message = null, $code = 500, $errors = null): JsonResponse
    {
        $response = [
            'status' => 'fail',
            'error' => $errorType,
            'message' => $message
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    private static function handleTokenInvalidException(TokenInvalidException $e): JsonResponse
    {
        return self::setErrorResponse('Token', $e->getMessage(), 401);
    }

    private static function handleTokenExpiredException(TokenExpiredException $e): JsonResponse
    {
        return self::setErrorResponse('Token', $e->getMessage(), 401);
    }

    private static function handleValidationException(ValidationException $e): JsonResponse
    {
        return self::setErrorResponse('Validation', $e->getMessage(), 422, $e->validator->errors());
    }

    private static function handleQueryException(QueryException $e): JsonResponse
    {
        return self::setErrorResponse('Database', $e->getMessage());
    }

    private static function handleModelNotFoundException(ModelNotFoundException $e): JsonResponse
    {
        return self::setErrorResponse('Model', $e->getMessage(), 404);
    }

    private static function handleHttpException(HttpException $e): JsonResponse
    {
        return self::setErrorResponse('HTTP', $e->getMessage(), $e->getStatusCode());
    }

    private static function handleGenericException(Exception $e, $isToken): JsonResponse
    {
        if ($isToken) {
            return self::setErrorResponse('Token', 'Access denied. Token not found', 401);
        }

        return self::setErrorResponse('Server', $e->getMessage());
    }
}
