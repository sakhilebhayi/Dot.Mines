<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OperatorResource;
use App\Models\Operator;
use App\Support\ApiPayload;
use App\Support\ApiResponse;
use App\Support\PageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The team's operator register, read-only. Operators are people who drive
 * machines, not users who sign in; the application manages them and the
 * API lets a control-room platform mirror who exists and who is active.
 */
class OperatorController extends Controller
{
    /**
     * List operators for current team
     *
     * GET /api/operators
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Operator::class);

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'employment_status' => ['nullable', 'string', Rule::in(array_keys(Operator::STATUSES))],
            'search' => 'nullable|string|max:100',
        ]);

        $query = Operator::query()->orderBy('last_name')->orderBy('first_name');

        if (isset($validated['employment_status'])) {
            $query->where('employment_status', $validated['employment_status']);
        }

        if ($request->filled('search')) {
            $search = ApiPayload::str($request->input('search'));
            $query->where(function ($inner) use ($search): void {
                $inner->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('preferred_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        return ApiResponse::paginated($query->paginate(PageSize::from($request)), OperatorResource::class);
    }

    /**
     * Get a single operator
     *
     * GET /api/operators/{id}
     */
    public function show(Operator $operator): JsonResponse
    {
        Gate::authorize('view', $operator);

        return response()->json(['data' => OperatorResource::make($operator)]);
    }
}
