<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Tenant extends Model
{
    protected $fillable = [
        'creator_id',
        'name',
        'slug',
        'type',
        'status',
        'plan',
        'trial_ends_at',
        'settings',
        'storage_used_mb',
        'max_storage_mb',
        'max_labs',
        'max_users'
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function users(){
        return $this->hasMany(User::class);
    }

    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings;

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            $settings = $decoded;
        }

        if (!is_array($settings)) {
            $settings = [];
        }

        return data_get($settings, $key, $default);
    }

    public function limitFor(string $resource): ?int
    {
        return match ($resource) {
            'labs' => $this->max_labs ?? $this->getSetting('max_labs'),
            'groups' => $this->getSetting('max_groups'),
            'projects' => $this->getSetting('max_projects'),
            'users' => $this->getSetting('max_users'),
            'storage' => $this->getSetting('max_storage_mb'),
            default => null,
        };
    }

    public function hasReachedLimit(string $resource, int $currentCount): bool
    {
        $limit = $this->limitFor($resource);
        return $limit !== null && $currentCount >= $limit;
    }
}
