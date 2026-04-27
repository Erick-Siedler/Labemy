<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Tenant;
use App\Models\Lab;
use App\Models\Group;

class SubUserInvite extends Model
{
    protected $fillable = [
        'tenant_id',
        'lab_id',
        'group_id',
        'email',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
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
