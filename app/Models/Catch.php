<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Catch extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'species',
        'weight',
        'length',
        'location',
        'latitude',
        'longitude',
        'water_body',
        'caught_at',
        'bait_lure',
        'technique',
        'water_temp',
        'air_temp',
        'weather_conditions',
        'photos',
        'notes',
        'is_released',
        'is_personal_best',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'caught_at' => 'datetime',
        'weight' => 'decimal:3',
        'length' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'water_temp' => 'decimal:2',
        'air_temp' => 'decimal:2',
        'photos' => 'array',
        'is_released' => 'boolean',
        'is_personal_best' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns this catch.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include catches from a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('caught_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include catches of a specific species.
     */
    public function scopeBySpecies($query, $species)
    {
        return $query->where('species', 'like', "%{$species}%");
    }

    /**
     * Scope a query to only include catches from a specific location.
     */
    public function scopeByLocation($query, $location)
    {
        return $query->where('location', 'like', "%{$location}%");
    }

    /**
     * Scope a query to only include personal best catches.
     */
    public function scopePersonalBests($query)
    {
        return $query->where('is_personal_best', true);
    }

    /**
     * Scope a query to only include released catches.
     */
    public function scopeReleased($query)
    {
        return $query->where('is_released', true);
    }

    /**
     * Get the first photo URL for this catch.
     */
    public function getMainPhotoAttribute()
    {
        $photos = $this->photos ?? [];
        return count($photos) > 0 ? $photos[0] : null;
    }

    /**
     * Get formatted weight with unit.
     */
    public function getFormattedWeightAttribute()
    {
        if (!$this->weight) return null;
        
        $unit = $this->user->getPreference('weight_unit', 'kg');
        return $this->weight . ' ' . $unit;
    }

    /**
     * Get formatted length with unit.
     */
    public function getFormattedLengthAttribute()
    {
        if (!$this->length) return null;
        
        $unit = $this->user->getPreference('length_unit', 'cm');
        return $this->length . ' ' . $unit;
    }

    /**
     * Check if this catch has location data.
     */
    public function hasLocationData()
    {
        return $this->latitude && $this->longitude;
    }

    /**
     * Get distance between this catch and another location.
     */
    public function distanceTo($latitude, $longitude)
    {
        if (!$this->hasLocationData()) {
            return null;
        }

        $earthRadius = 6371; // km

        $latDiff = deg2rad($latitude - $this->latitude);
        $lonDiff = deg2rad($longitude - $this->longitude);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) *
             sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}