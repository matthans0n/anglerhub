<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Catch;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CatchController extends Controller
{
    /**
     * Get all catches for the authenticated user.
     */
    public function index(Request $request)
    {
        $query = $request->user()->catches()->with('user');

        // Apply filters
        if ($request->has('species')) {
            $query->bySpecies($request->species);
        }

        if ($request->has('location')) {
            $query->byLocation($request->location);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        if ($request->boolean('personal_bests')) {
            $query->personalBests();
        }

        if ($request->boolean('released_only')) {
            $query->released();
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'caught_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $catches = $query->paginate($perPage);

        return response()->json($catches);
    }

    /**
     * Store a new catch.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'species' => ['required', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999.999'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'location' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'water_body' => ['nullable', 'string', 'max:255'],
            'caught_at' => ['required', 'date'],
            'bait_lure' => ['nullable', 'string', 'max:255'],
            'technique' => ['nullable', 'string', 'max:255'],
            'water_temp' => ['nullable', 'numeric', 'between:-10,50'],
            'air_temp' => ['nullable', 'numeric', 'between:-50,60'],
            'weather_conditions' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_released' => ['boolean'],
            'is_personal_best' => ['boolean'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'max:10240'], // 10MB max per photo
        ]);

        $validated['user_id'] = $request->user()->id;

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('catches', 'public');
                $photoPaths[] = $path;
            }
            $validated['photos'] = $photoPaths;
        }

        $catch = Catch::create($validated);
        $catch->load('user');

        // Update user goals if applicable
        $this->updateRelatedGoals($catch);

        return response()->json([
            'message' => 'Catch recorded successfully',
            'catch' => $catch,
        ], Response::HTTP_CREATED);
    }

    /**
     * Get a specific catch.
     */
    public function show(Request $request, Catch $catch)
    {
        // Ensure user can only access their own catches
        if ($catch->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $catch->load('user');

        return response()->json($catch);
    }

    /**
     * Update a catch.
     */
    public function update(Request $request, Catch $catch)
    {
        // Ensure user can only update their own catches
        if ($catch->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'species' => ['sometimes', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999.999'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'location' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'water_body' => ['nullable', 'string', 'max:255'],
            'caught_at' => ['sometimes', 'date'],
            'bait_lure' => ['nullable', 'string', 'max:255'],
            'technique' => ['nullable', 'string', 'max:255'],
            'water_temp' => ['nullable', 'numeric', 'between:-10,50'],
            'air_temp' => ['nullable', 'numeric', 'between:-50,60'],
            'weather_conditions' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_released' => ['boolean'],
            'is_personal_best' => ['boolean'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'max:10240'], // 10MB max per photo
        ]);

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            // Delete old photos
            if ($catch->photos) {
                foreach ($catch->photos as $photoPath) {
                    Storage::disk('public')->delete($photoPath);
                }
            }

            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('catches', 'public');
                $photoPaths[] = $path;
            }
            $validated['photos'] = $photoPaths;
        }

        $catch->update($validated);
        $catch->load('user');

        // Update user goals if applicable
        $this->updateRelatedGoals($catch);

        return response()->json([
            'message' => 'Catch updated successfully',
            'catch' => $catch,
        ]);
    }

    /**
     * Delete a catch.
     */
    public function destroy(Request $request, Catch $catch)
    {
        // Ensure user can only delete their own catches
        if ($catch->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        // Delete associated photos
        if ($catch->photos) {
            foreach ($catch->photos as $photoPath) {
                Storage::disk('public')->delete($photoPath);
            }
        }

        $catch->delete();

        // Update user goals after deletion
        $user = $request->user();
        foreach ($user->goals as $goal) {
            $goal->updateProgress();
        }

        return response()->json([
            'message' => 'Catch deleted successfully',
        ]);
    }

    /**
     * Get catch statistics for the authenticated user.
     */
    public function statistics(Request $request)
    {
        $user = $request->user();
        $catches = $user->catches();

        // Apply date range if specified
        if ($request->has('start_date') && $request->has('end_date')) {
            $catches = $catches->dateRange($request->start_date, $request->end_date);
        }

        $stats = [
            'total_catches' => $catches->count(),
            'total_weight' => $catches->sum('weight'),
            'average_weight' => $catches->avg('weight'),
            'heaviest_catch' => $catches->orderBy('weight', 'desc')->first(),
            'longest_catch' => $catches->orderBy('length', 'desc')->first(),
            'most_common_species' => $catches->select('species')
                ->groupBy('species')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(5)
                ->pluck('species'),
            'catches_by_month' => $catches->selectRaw('MONTH(caught_at) as month, COUNT(*) as count')
                ->groupBy('month')
                ->pluck('count', 'month'),
            'favorite_locations' => $catches->select('location')
                ->groupBy('location')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(5)
                ->pluck('location'),
            'personal_bests_count' => $catches->where('is_personal_best', true)->count(),
            'released_count' => $catches->where('is_released', true)->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get nearby catches based on location.
     */
    public function nearby(Request $request)
    {
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:1', 'max:100'], // km
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radius = $request->get('radius', 10); // Default 10km

        // Simple bounding box approach (more efficient than haversine for large datasets)
        $latRange = $radius / 111; // Approximate degrees per km
        $lonRange = $radius / (111 * cos(deg2rad($latitude)));

        $catches = $request->user()->catches()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latRange, $latitude + $latRange])
            ->whereBetween('longitude', [$longitude - $lonRange, $longitude + $lonRange])
            ->orderBy('caught_at', 'desc')
            ->limit(50)
            ->get();

        // Calculate exact distances and filter
        $nearbyCatches = $catches->filter(function ($catch) use ($latitude, $longitude, $radius) {
            $distance = $catch->distanceTo($latitude, $longitude);
            return $distance !== null && $distance <= $radius;
        });

        return response()->json($nearbyCatches->values());
    }

    /**
     * Update related goals after catch creation/update.
     */
    protected function updateRelatedGoals(Catch $catch)
    {
        $user = $catch->user;
        foreach ($user->activeGoals as $goal) {
            $goal->updateProgress();
        }
    }
}