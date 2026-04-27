<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Tenant;
use App\Models\Lab;
use App\Models\Group;
use App\Models\User;

class SubUserInvite extends Model
{
    protected $fillable = [
        'tenant_id',
        'lab_id',
        'group_id',
        'email',
        'role',
        'invited_by_user_id',
        'accepted_by_user_id',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Executa a rotina 'tenant' no fluxo de negocio.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Executa a rotina 'lab' no fluxo de negocio.
     */
    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    /**
     * Executa a rotina 'group' no fluxo de negocio.
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Executa a rotina 'invitedBy' no fluxo de negocio.
     */
    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Executa a rotina 'acceptedBy' no fluxo de negocio.
     */
    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }
}
