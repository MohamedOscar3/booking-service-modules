<?php

namespace App\Services;

class ApiResponseService
{
    public function pagination($message, $data, $resource)
    {
        $meta = [
            'current_page' => $data->currentPage(),
            'from' => $data->firstItem(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'to' => $data->lastItem(),
            'total' => $data->total(),
        ];

        $links = [
            'first' => $data->url(1),
            'last' => $data->url($data->lastPage()),
            'prev' => $data->previousPageUrl(),
            'next' => $data->nextPageUrl(),
        ];

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $resource::collection($data),
            'links' => $links,
            'meta' => $meta,
        ], 200);
    }

    public function created($data, $message = 'Resource created successfully')
    {
        return $this->successResponse(
            message: $message,
            data: $data,
            code: 201
        );
    }

    public function updated($message, $data)
    {
        return $this->successResponse(
            message: $message,
            data: $data,
            code: 200);
    }

    public function deleted($message = 'Resource deleted successfully')
    {
        return $this->successResponse(
            message: $message,
            code: 200
        );
    }

    public function notFound($message = 'Resource not found', $errors = null, $code = 404)
    {
        return $this->failedResponse(
            message: $message,
            errors: $errors,
            code: $code
        );
    }

    public function forbidden($message = "You don't have permission to access this resource", $errors = null, $code = 403)
    {
        return $this->failedResponse(
            message: $message,
            errors: $errors,
            code: $code
        );
    }

    public function unauthorized($message = 'Unauthorized access', $errors = null, $code = 401)
    {
        return $this->failedResponse(
            message: $message,
            errors: $errors,
            code: $code
        );
    }

    public function unprocessable($message = 'Unprocessable entity', $errors = null, $code = 422)
    {
        return $this->failedResponse(
            message: $message,
            errors: $errors,
            code: $code
        );
    }

    public function badRequest($message = 'Bad Request', $errors = null, $code = 400)
    {
        return $this->failedResponse(
            message: $message,
            errors: $errors,
            code: $code
        );
    }

    public function serverError($message = 'Server Error', $errors = null, $code = 500)
    {
        return $this->failedResponse(
            message: $message,
            errors: $errors,
            code: $code
        );
    }

    /**
     * Handle exception and return appropriate response
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleException(\Throwable $exception)
    {
        $exceptionClass = get_class($exception);
        $message = $exception->getMessage() ?: 'An error occurred';
        $errors = config('app.debug') ? [
            'exception' => $exceptionClass,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
        ] : null;

        // Handle specific exception types
        switch (true) {
            case $exception instanceof \Illuminate\Auth\AuthenticationException:
                return $this->unauthorized($message, $errors);

            case $exception instanceof \Illuminate\Auth\Access\AuthorizationException:
                return $this->forbidden($message, $errors);

            case $exception instanceof \Illuminate\Validation\ValidationException:
                return $this->unprocessable($message, $exception->errors());

            case $exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException:
                return $this->notFound('Resource not found', $errors);

            case $exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException:
                return $this->notFound('Route not found', $errors);

            case $exception instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException:
                return $this->badRequest('Method not allowed', $errors);

            case $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException:
                $statusCode = $exception->getStatusCode();
                if ($statusCode === 403) {
                    return $this->forbidden($message, $errors);
                } elseif ($statusCode === 401) {
                    return $this->unauthorized($message, $errors);
                } elseif ($statusCode === 404) {
                    return $this->notFound($message, $errors);
                } elseif ($statusCode === 422) {
                    return $this->unprocessable($message, $errors);
                } else {
                    return $this->failedResponse($message, $errors, $statusCode);
                }

            default:
                return $this->serverError($message, $errors);
        }
    }

    public function failedResponse($message, $errors = null, $code = 500)
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    public function successResponse($message, $code = 200, $data = null)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
