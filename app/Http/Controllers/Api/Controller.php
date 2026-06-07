<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base API Controller
 *
 * All API controllers extend this for common functionality.
 * Current API version: v1
 */
class Controller extends BaseController
{
    use AuthorizesRequests;

    /** Current API version string injected into responses. */
    protected string $apiVersion = 'v1';

    public function __construct()
    {
        // All API endpoints require authentication
        $this->middleware('auth:sanctum');
        // Validate team context
        $this->middleware('ensure_team');
    }

    /**
     * Return a standardised success response.
     */
    protected function success(mixed $data = null, string $message = '', int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a standardised error response.
     */
    protected function error(string $message, int $status = Response::HTTP_BAD_REQUEST, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a standardised paginated response wrapping a LengthAwarePaginator.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    protected function paginated(mixed $paginator, string $message = ''): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
