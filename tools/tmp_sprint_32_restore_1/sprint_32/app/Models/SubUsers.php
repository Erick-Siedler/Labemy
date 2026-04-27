<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Tenant;
use App\Models\Lab;
use App\Models\Group;

class SubUsers extends Authenticatable
{
    protected $fillable = [
        'tenant_id',
        'lab_id',
        'group_id',
        'name',
        'email',
        'password',
        'role',
        'phone',
        'institution',
        'bio',
        'preferences',
        'notifications',
        'profile_photo_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'preferences' => 'array',
        'notifications' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
