<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'bio',
        'location',
        'preferences',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'preferences' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the catches for this user.
     */
    public function catches()
    {
        return $this->hasMany(Catch::class);
    }

    /**
     * Get the goals for this user.
     */
    public function goals()
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * Get the weather logs for this user.
     */
    public function weatherLogs()
    {
        return $this->hasMany(WeatherLog::class);
    }

    /**
     * Get the user's recent catches.
     */
    public function recentCatches($limit = 10)
    {
        return $this->catches()
            ->orderBy('caught_at', 'desc')
            ->limit($limit);
    }

    /**
     * Get the user's active goals.
     */
    public function activeGoals()
    {
        return $this->goals()
            ->where('status', 'active')
            ->where('target_date', '>=', now());
    }

    /**
     * Get user preferences with defaults.
     */
    public function getPreference($key, $default = null)
    {
        $preferences = $this->preferences ?? [];
        return $preferences[$key] ?? $default;
    }

    /**
     * Set a user preference.
     */
    public function setPreference($key, $value)
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;
        $this->preferences = $preferences;
        $this->save();
    }

    /**
     * Get user's total catch count.
     */
    public function getTotalCatchesAttribute()
    {
        return $this->catches()->count();
    }

    /**
     * Get user's personal best catch by weight.
     */
    public function getPersonalBestAttribute()
    {
        return $this->catches()
            ->whereNotNull('weight')
            ->orderBy('weight', 'desc')
            ->first();
    }
}