<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'preferences' => [
                'weight_unit' => 'kg',
                'length_unit' => 'cm',
                'temperature_unit' => 'C',
                'wind_speed_unit' => 'm/s',
                'distance_unit' => 'km',
                'date_format' => 'Y-m-d',
                'time_format' => '24h',
                'notifications_enabled' => true,
                'public_profile' => false,
            ],
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
        ], Response::HTTP_CREATED);
    }

    /**
     * Login user.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Revoke existing tokens for security
        $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Logout from all devices.
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices successfully',
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request)
    {
        $user = $request->user();
        $user->load(['recentCatches' => function ($query) {
            $query->limit(5)->orderBy('caught_at', 'desc');
        }, 'activeGoals']);

        return response()->json([
            'user' => $user,
            'stats' => [
                'total_catches' => $user->total_catches,
                'active_goals' => $user->activeGoals()->count(),
                'personal_best' => $user->personal_best,
            ],
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'bio' => ['sometimes', 'string', 'max:1000'],
            'location' => ['sometimes', 'string', 'max:255'],
            'avatar' => ['sometimes', 'image', 'max:2048'], // 2MB max
        ]);

        if (isset($validated['avatar'])) {
            // Handle avatar upload
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $avatarPath;
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Update user preferences.
     */
    public function updatePreferences(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'weight_unit' => ['sometimes', 'in:kg,lbs'],
            'length_unit' => ['sometimes', 'in:cm,inches'],
            'temperature_unit' => ['sometimes', 'in:C,F'],
            'wind_speed_unit' => ['sometimes', 'in:m/s,mph,knots'],
            'distance_unit' => ['sometimes', 'in:km,miles'],
            'date_format' => ['sometimes', 'in:Y-m-d,d/m/Y,m/d/Y'],
            'time_format' => ['sometimes', 'in:12h,24h'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'public_profile' => ['sometimes', 'boolean'],
            'auto_weather_logging' => ['sometimes', 'boolean'],
            'goal_reminders' => ['sometimes', 'boolean'],
        ]);

        $currentPreferences = $user->preferences ?? [];
        $updatedPreferences = array_merge($currentPreferences, $validated);
        
        $user->update(['preferences' => $updatedPreferences]);

        return response()->json([
            'message' => 'Preferences updated successfully',
            'preferences' => $updatedPreferences,
        ]);
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all tokens for security
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password changed successfully. Please log in again.',
        ]);
    }

    /**
     * Delete user account.
     */
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => ['required'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        // Soft delete approach - deactivate instead of hard delete
        $user->update([
            'is_active' => false,
            'email' => $user->email . '_deleted_' . time(),
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Account deactivated successfully.',
        ]);
    }
}