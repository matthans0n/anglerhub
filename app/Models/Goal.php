<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'criteria',
        'target_value',
        'current_value',
        'progress_data',
        'start_date',
        'target_date',
        'completed_at',
        'status',
        'is_public',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'criteria' => 'array',
        'progress_data' => 'array',
        'start_date' => 'date',
        'target_date' => 'date',
        'completed_at' => 'date',
        'target_value' => 'integer',
        'current_value' => 'integer',
        'is_public' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * The goal types available.
     */
    const TYPES = [
        'species' => 'Species Target',
        'weight' => 'Weight Goal',
        'count' => 'Catch Count',
        'location' => 'Location Challenge',
        'custom' => 'Custom Goal'
    ];

    /**
     * The goal statuses available.
     */
    const STATUSES = [
        'active' => 'Active',
        'completed' => 'Completed',
        'paused' => 'Paused',
        'cancelled' => 'Cancelled'
    ];

    /**
     * Get the user that owns this goal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include active goals.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include completed goals.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include goals of a specific type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include public goals.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Calculate the progress percentage of this goal.
     */
    public function getProgressPercentageAttribute()
    {
        if (!$this->target_value || $this->target_value == 0) {
            return 0;
        }

        $percentage = ($this->current_value / $this->target_value) * 100;
        return min(100, $percentage);
    }

    /**
     * Check if this goal is completed.
     */
    public function getIsCompletedAttribute()
    {
        return $this->status === 'completed' || 
               ($this->target_value && $this->current_value >= $this->target_value);
    }

    /**
     * Check if this goal is overdue.
     */
    public function getIsOverdueAttribute()
    {
        return $this->status === 'active' && 
               $this->target_date && 
               $this->target_date->isPast() &&
               !$this->is_completed;
    }

    /**
     * Get days remaining until target date.
     */
    public function getDaysRemainingAttribute()
    {
        if (!$this->target_date || $this->is_completed) {
            return null;
        }

        return now()->diffInDays($this->target_date, false);
    }

    /**
     * Get formatted type name.
     */
    public function getTypeNameAttribute()
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get formatted status name.
     */
    public function getStatusNameAttribute()
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Update progress based on user's catches.
     */
    public function updateProgress()
    {
        $catches = $this->user->catches();

        switch ($this->type) {
            case 'species':
                $this->updateSpeciesProgress($catches);
                break;
            case 'weight':
                $this->updateWeightProgress($catches);
                break;
            case 'count':
                $this->updateCountProgress($catches);
                break;
            case 'location':
                $this->updateLocationProgress($catches);
                break;
        }

        // Check if goal is completed
        if ($this->is_completed && $this->status === 'active') {
            $this->status = 'completed';
            $this->completed_at = now();
        }

        $this->save();
    }

    /**
     * Update progress for species-based goals.
     */
    protected function updateSpeciesProgress($catches)
    {
        $criteria = $this->criteria;
        $targetSpecies = $criteria['species'] ?? null;

        if (!$targetSpecies) return;

        $matchingCatches = $catches->where('species', 'like', "%{$targetSpecies}%");

        if (isset($criteria['min_weight'])) {
            $matchingCatches = $matchingCatches->where('weight', '>=', $criteria['min_weight']);
        }

        $this->current_value = $matchingCatches->count();
    }

    /**
     * Update progress for weight-based goals.
     */
    protected function updateWeightProgress($catches)
    {
        $criteria = $this->criteria;
        $targetWeight = $criteria['target_weight'] ?? $this->target_value;

        $heaviestCatch = $catches->max('weight') ?? 0;
        $this->current_value = $heaviestCatch;
    }

    /**
     * Update progress for count-based goals.
     */
    protected function updateCountProgress($catches)
    {
        $criteria = $this->criteria;
        $query = $catches;

        // Apply date range if specified
        if (isset($criteria['date_range'])) {
            $query = $query->whereBetween('caught_at', [
                $criteria['date_range']['start'],
                $criteria['date_range']['end']
            ]);
        }

        // Apply species filter if specified
        if (isset($criteria['species'])) {
            $query = $query->where('species', 'like', "%{$criteria['species']}%");
        }

        $this->current_value = $query->count();
    }

    /**
     * Update progress for location-based goals.
     */
    protected function updateLocationProgress($catches)
    {
        $criteria = $this->criteria;
        $targetLocation = $criteria['location'] ?? null;

        if (!$targetLocation) return;

        $matchingCatches = $catches->where('location', 'like', "%{$targetLocation}%");
        $this->current_value = $matchingCatches->count();
    }
}