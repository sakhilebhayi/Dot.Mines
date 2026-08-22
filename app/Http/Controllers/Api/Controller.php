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

    // No constructor middleware: Laravel 12 removed Controller::middleware(),
    // so the old constructor 500'd EVERY API request. Both middleware this
    // constructor used to (re)declare -- auth:sanctum and ensure_team -- are
    // already applied by the route group in routes/api.php, which is the
    // single place API middleware is defined.
}
