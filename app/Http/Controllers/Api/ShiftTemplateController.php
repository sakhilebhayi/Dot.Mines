<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedPost;
use App\Models\ShiftTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShiftTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftTemplate::class);

        $query = ShiftTemplate::with('creator:id,name')->latest();

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ShiftTemplate::class);

        $validated = $request->validate([
            'category' => 'required|in:'.implode(',', FeedPost::CATEGORIES),
            'title' => 'required|string|max:255',
            'template_body' => 'required|string|max:5000',
            'required_fields' => 'nullable|array',
            'required_fields.*' => 'string|max:100',
        ]);

        $template = ShiftTemplate::create(array_merge($validated, [
            'team_id' => Auth::user()->current_team_id,
            'created_by' => Auth::id(),
        ]));

        $template->load('creator:id,name');

        return response()->json($template, 201);
    }

    public function update(Request $request, ShiftTemplate $shiftTemplate): JsonResponse
    {
        $this->authorize('update', $shiftTemplate);

        $validated = $request->validate([
            'category' => 'sometimes|in:'.implode(',', FeedPost::CATEGORIES),
            'title' => 'sometimes|string|max:255',
            'template_body' => 'sometimes|string|max:5000',
            'required_fields' => 'nullable|array',
            'required_fields.*' => 'string|max:100',
        ]);

        $shiftTemplate->update($validated);
        $shiftTemplate->load('creator:id,name');

        return response()->json($shiftTemplate);
    }

    public function destroy(ShiftTemplate $shiftTemplate): JsonResponse
    {
        $this->authorize('delete', $shiftTemplate);
        $shiftTemplate->delete();

        return response()->json(null, 204);
    }
}
