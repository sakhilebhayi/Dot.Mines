<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base API Controller
 *
 * All API controllers extend this for common functionality
 */
class Controller extends BaseController
{
    use AuthorizesRequests;

    public function __construct()
    {
        // All API endpoints require authentication
        $this->middleware('auth:sanctum');
        // Validate team context
        $this->middleware('ensure_team');
    }
}
