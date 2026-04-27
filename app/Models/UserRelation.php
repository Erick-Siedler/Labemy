<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRelation extends Model
{
    protected $table = 'users_rel';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'lab_id',
        'group_id',
        'status',
        'role',
        'invited_at',
        'accepted_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Executa a rotina 'user' no fluxo de negocio.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

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
}
