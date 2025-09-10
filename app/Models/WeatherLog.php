<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'location_name',
        'temperature',
        'feels_like',
        'humidity',
        'pressure',
        'wind_speed',
        'wind_direction',
        'weather_main',
        'weather_description',
        'precipitation',
        'cloud_cover',
        'uv_index',
        'visibility',
        'recorded_at',
        'api_source',
        'raw_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'temperature' => 'decimal:2',
        'feels_like' => 'decimal:2',
        'pressure' => 'decimal:2',
        'wind_speed' => 'decimal:2',
        'precipitation' => 'decimal:2',
        'uv_index' => 'decimal:1',
        'recorded_at' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Get the user that owns this weather log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include logs from a specific date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include logs from a specific location.
     */
    public function scopeByLocation($query, $locationName)
    {
        return $query->where('location_name', 'like', "%{$locationName}%");
    }

    /**
     * Scope a query to only include logs within a radius of coordinates.
     */
    public function scopeNearLocation($query, $latitude, $longitude, $radiusKm = 10)
    {
        $latRange = $radiusKm / 111; // Approximate degrees per km
        $lonRange = $radiusKm / (111 * cos(deg2rad($latitude)));

        return $query->whereBetween('latitude', [$latitude - $latRange, $latitude + $latRange])
                    ->whereBetween('longitude', [$longitude - $lonRange, $longitude + $lonRange]);
    }

    /**
     * Get formatted temperature with unit.
     */
    public function getFormattedTemperatureAttribute()
    {
        $unit = $this->user->getPreference('temperature_unit', 'C');
        return round($this->temperature, 1) . '°' . $unit;
    }

    /**
     * Get formatted wind speed with unit.
     */
    public function getFormattedWindSpeedAttribute()
    {
        if (!$this->wind_speed) return null;
        
        $unit = $this->user->getPreference('wind_speed_unit', 'm/s');
        return round($this->wind_speed, 1) . ' ' . $unit;
    }

    /**
     * Get wind direction as compass point.
     */
    public function getWindDirectionCompassAttribute()
    {
        if (!$this->wind_direction) return null;

        $directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
        $index = round($this->wind_direction / 22.5) % 16;
        
        return $directions[$index];
    }

    /**
     * Get weather conditions summary.
     */
    public function getWeatherSummaryAttribute()
    {
        $summary = $this->weather_main;
        
        if ($this->temperature) {
            $summary .= ', ' . $this->formatted_temperature;
        }
        
        if ($this->wind_speed) {
            $summary .= ', Wind: ' . $this->formatted_wind_speed;
            if ($this->wind_direction_compass) {
                $summary .= ' ' . $this->wind_direction_compass;
            }
        }
        
        return $summary;
    }

    /**
     * Check if conditions are good for fishing based on basic criteria.
     */
    public function getIsFishingFriendlyAttribute()
    {
        // Basic criteria - can be customized based on user preferences
        $goodTemperature = $this->temperature >= 10 && $this->temperature <= 30; // 10-30°C
        $lowWind = !$this->wind_speed || $this->wind_speed <= 5; // <= 5 m/s
        $notHeavyRain = !in_array($this->weather_main, ['Thunderstorm', 'Drizzle', 'Rain']) || 
                        ($this->precipitation && $this->precipitation < 5);

        return $goodTemperature && $lowWind && $notHeavyRain;
    }

    /**
     * Find similar weather conditions for this location.
     */
    public function findSimilarConditions($tolerance = 5)
    {
        return static::where('user_id', $this->user_id)
            ->where('location_name', $this->location_name)
            ->where('id', '!=', $this->id)
            ->whereBetween('temperature', [$this->temperature - $tolerance, $this->temperature + $tolerance])
            ->where('weather_main', $this->weather_main)
            ->orderBy('recorded_at', 'desc')
            ->get();
    }

    /**
     * Get pressure trend compared to recent readings.
     */
    public function getPressureTrendAttribute()
    {
        $recent = static::where('user_id', $this->user_id)
            ->where('location_name', $this->location_name)
            ->where('recorded_at', '<', $this->recorded_at)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$recent || !$recent->pressure) {
            return 'stable';
        }

        $diff = $this->pressure - $recent->pressure;
        
        if ($diff > 2) return 'rising';
        if ($diff < -2) return 'falling';
        return 'stable';
    }
}