<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Group;
use App\Models\Project;
use App\Models\User;
use App\Models\UserRelation;

class Lab extends Model
{

    protected $fillable = [
        'tenant_id', 'creator_id', 'creator_subuser_id', 'name', 'code', 'status'
    ];

    /**
     * Executa a rotina 'groups' no fluxo de negocio.
     */
    public function groups()
    {   
        return $this->hasMany(Group::class);
    }

    /**
     * Executa a rotina 'projects' no fluxo de negocio.
     */
    public function projects(){
        return $this->hasMany(Project::class);
    }

    /**
     * Executa a rotina 'subUsers' no fluxo de negocio.
     */
    public function subUsers(){
        return $this->belongsToMany(User::class, 'users_rel', 'lab_id', 'user_id')
            ->select([
                'users.*',
                'users_rel.tenant_id as tenant_id',
                'users_rel.lab_id as lab_id',
                'users_rel.group_id as group_id',
                'users_rel.role as role',
            ])
            ->withPivot(['id', 'tenant_id', 'group_id', 'role', 'status'])
            ->wherePivot('status', 'active');
    }

    /**
     * Executa a rotina 'userRelations' no fluxo de negocio.
     */
    public function userRelations()
    {
        return $this->hasMany(UserRelation::class, 'lab_id');
    }

    /**
     * Executa a rotina 'creatorSubuser' no fluxo de negocio.
     */
    public function creatorSubuser()
    {
        return $this->belongsTo(User::class, 'creator_subuser_id');
    }
}
