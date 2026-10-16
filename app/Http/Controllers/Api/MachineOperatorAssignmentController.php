<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\IneligibleAssignmentException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OperatorAssignmentResource;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\User;
use App\Services\Operators\OperatorAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Who operates a machine, through the one service every other path uses,
 * so the eligibility gate (employment, licence, medical, induction) cannot
 * be walked around by calling the API instead of clicking the fleet page.
 */
class MachineOperatorAssignmentController extends Controller
{
    public function __construct(private readonly OperatorAssignmentService $assignments) {}

    /**
     * Assign an operator to a machine (relieves the current operator)
     *
     * POST /api/machines/{id}/operator-assignments
     */
    public function store(Request $request, Machine $machine): JsonResponse
    {
        Gate::authorize('update', $machine);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $validated = $request->validate([
            'operator_id' => ['required', 'integer', Rule::exists('operators', 'id')->where('team_id', $machine->team_id)->whereNull('deleted_at')],
            'shift' => 'nullable|string|max:32',
            'override_reason' => 'nullable|string|max:500',
        ]);

        $operator = Operator::query()->findOrFail((int) $validated['operator_id']);

        try {
            $assignment = $this->assignments->assign(
                $operator,
                $machine,
                $user,
                $validated['shift'] ?? $operator->default_shift,
                $validated['override_reason'] ?? null,
            );
        } catch (IneligibleAssignmentException $e) {
            // One error shape for the whole API: the blockers are the
            // validation errors of the operator_id field.
            throw ValidationException::withMessages(['operator_id' => $e->blockers]);
        }

        return response()->json([
            'data' => OperatorAssignmentResource::make($assignment->load(['operator', 'machine'])),
            'message' => 'Operator assigned successfully',
        ], 201);
    }

    /**
     * Unassign the current operator from a machine
     *
     * DELETE /api/machines/{id}/operator-assignments
     */
    public function destroy(Request $request, Machine $machine): JsonResponse
    {
        Gate::authorize('update', $machine);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $assignment = $machine->currentOperatorAssignment();

        if ($assignment === null) {
            abort(404, 'No operator is assigned to this machine.');
        }

        $assignment = $this->assignments->unassign($assignment, $user, $validated['reason'] ?? null);

        return response()->json([
            'data' => OperatorAssignmentResource::make($assignment->load(['operator', 'machine'])),
            'message' => 'Operator unassigned successfully',
        ]);
    }
}
