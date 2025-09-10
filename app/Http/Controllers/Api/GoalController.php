<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class GoalController extends Controller
{
    /**
     * Get all goals for the authenticated user.
     */
    public function index(Request $request)
    {
        $query = $request->user()->goals()->with('user');

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->boolean('completed_only')) {
            $query->completed();
        }

        if ($request->boolean('overdue_only')) {
            $query->active()->where('target_date', '<', now());
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $goals = $query->paginate($perPage);

        // Add computed attributes
        $goals->getCollection()->transform(function ($goal) {
            return $goal->append([
                'progress_percentage',
                'is_completed',
                'is_overdue',
                'days_remaining',
                'type_name',
                'status_name'
            ]);
        });

        return response()->json($goals);
    }

    /**
     * Store a new goal.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(array_keys(Goal::TYPES))],
            'criteria' => ['required', 'array'],
            'target_value' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'target_date' => ['required', 'date', 'after:start_date'],
            'is_public' => ['boolean'],
        ]);

        // Validate criteria based on goal type
        $this->validateCriteria($request->type, $validated['criteria']);

        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'active';

        $goal = Goal::create($validated);
        $goal->updateProgress(); // Initialize progress
        $goal->load('user');

        return response()->json([
            'message' => 'Goal created successfully',
            'goal' => $goal->append([
                'progress_percentage',
                'is_completed',
                'is_overdue',
                'days_remaining',
                'type_name',
                'status_name'
            ]),
        ], Response::HTTP_CREATED);
    }

    /**
     * Get a specific goal.
     */
    public function show(Request $request, Goal $goal)
    {
        // Ensure user can only access their own goals
        if ($goal->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $goal->load('user');
        $goal->append([
            'progress_percentage',
            'is_completed',
            'is_overdue',
            'days_remaining',
            'type_name',
            'status_name'
        ]);

        return response()->json($goal);
    }

    /**
     * Update a goal.
     */
    public function update(Request $request, Goal $goal)
    {
        // Ensure user can only update their own goals
        if ($goal->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        // Don't allow updating completed goals
        if ($goal->status === 'completed') {
            return response()->json([
                'message' => 'Cannot update completed goals'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'criteria' => ['sometimes', 'array'],
            'target_value' => ['nullable', 'integer', 'min:1'],
            'target_date' => ['sometimes', 'date', 'after:start_date'],
            'status' => ['sometimes', Rule::in(array_keys(Goal::STATUSES))],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        // Validate criteria if provided
        if (isset($validated['criteria'])) {
            $this->validateCriteria($goal->type, $validated['criteria']);
        }

        $goal->update($validated);
        $goal->updateProgress();
        $goal->load('user');

        return response()->json([
            'message' => 'Goal updated successfully',
            'goal' => $goal->append([
                'progress_percentage',
                'is_completed',
                'is_overdue',
                'days_remaining',
                'type_name',
                'status_name'
            ]),
        ]);
    }

    /**
     * Delete a goal.
     */
    public function destroy(Request $request, Goal $goal)
    {
        // Ensure user can only delete their own goals
        if ($goal->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $goal->delete();

        return response()->json([
            'message' => 'Goal deleted successfully',
        ]);
    }

    /**
     * Mark a goal as completed.
     */
    public function complete(Request $request, Goal $goal)
    {
        // Ensure user can only complete their own goals
        if ($goal->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        if ($goal->status === 'completed') {
            return response()->json([
                'message' => 'Goal is already completed'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $goal->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Goal marked as completed',
            'goal' => $goal->append([
                'progress_percentage',
                'is_completed',
                'is_overdue',
                'days_remaining',
                'type_name',
                'status_name'
            ]),
        ]);
    }

    /**
     * Pause a goal.
     */
    public function pause(Request $request, Goal $goal)
    {
        // Ensure user can only pause their own goals
        if ($goal->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        if (!in_array($goal->status, ['active'])) {
            return response()->json([
                'message' => 'Only active goals can be paused'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $goal->update(['status' => 'paused']);

        return response()->json([
            'message' => 'Goal paused',
            'goal' => $goal->append([
                'progress_percentage',
                'is_completed',
                'is_overdue',
                'days_remaining',
                'type_name',
                'status_name'
            ]),
        ]);
    }

    /**
     * Resume a paused goal.
     */
    public function resume(Request $request, Goal $goal)
    {
        // Ensure user can only resume their own goals
        if ($goal->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        if ($goal->status !== 'paused') {
            return response()->json([
                'message' => 'Only paused goals can be resumed'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $goal->update(['status' => 'active']);
        $goal->updateProgress();

        return response()->json([
            'message' => 'Goal resumed',
            'goal' => $goal->append([
                'progress_percentage',
                'is_completed',
                'is_overdue',
                'days_remaining',
                'type_name',
                'status_name'
            ]),
        ]);
    }

    /**
     * Get goal statistics for the authenticated user.
     */
    public function statistics(Request $request)
    {
        $user = $request->user();
        $goals = $user->goals();

        $stats = [
            'total_goals' => $goals->count(),
            'active_goals' => $goals->where('status', 'active')->count(),
            'completed_goals' => $goals->where('status', 'completed')->count(),
            'paused_goals' => $goals->where('status', 'paused')->count(),
            'cancelled_goals' => $goals->where('status', 'cancelled')->count(),
            'overdue_goals' => $goals->active()
                ->where('target_date', '<', now())
                ->count(),
            'goals_by_type' => $goals->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'completion_rate' => $goals->count() > 0 
                ? ($goals->where('status', 'completed')->count() / $goals->count()) * 100 
                : 0,
            'upcoming_deadlines' => $goals->active()
                ->where('target_date', '>=', now())
                ->where('target_date', '<=', now()->addDays(7))
                ->orderBy('target_date')
                ->get()
                ->append([
                    'progress_percentage',
                    'days_remaining',
                    'type_name'
                ]),
        ];

        return response()->json($stats);
    }

    /**
     * Refresh progress for all active goals.
     */
    public function refreshProgress(Request $request)
    {
        $user = $request->user();
        $activeGoals = $user->activeGoals;

        foreach ($activeGoals as $goal) {
            $goal->updateProgress();
        }

        return response()->json([
            'message' => 'Progress updated for all active goals',
            'updated_count' => $activeGoals->count(),
        ]);
    }

    /**
     * Validate criteria based on goal type.
     */
    protected function validateCriteria($type, $criteria)
    {
        switch ($type) {
            case 'species':
                if (!isset($criteria['species'])) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        ['criteria.species' => ['Species is required for species goals']]
                    );
                }
                break;

            case 'weight':
                if (!isset($criteria['target_weight'])) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        ['criteria.target_weight' => ['Target weight is required for weight goals']]
                    );
                }
                break;

            case 'location':
                if (!isset($criteria['location'])) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        ['criteria.location' => ['Location is required for location goals']]
                    );
                }
                break;
        }
    }
}