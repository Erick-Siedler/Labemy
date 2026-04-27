<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Project;
use App\Models\User;
use App\Models\UserRelation;

class Group extends Model
{   

    protected $fillable = [
        'tenant_id', 'lab_id', 'creator_id', 'name', 'code', 'status'
    ];

    /**
     * Executa a rotina 'lab' no fluxo de negocio.
     */
    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    /**
     * Executa a rotina 'projects_versions' no fluxo de negocio.
     */
    public function projects_versions()
    {
        return $this->hasMany(ProjectVersion::class);
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
        return $this->belongsToMany(User::class, 'users_rel', 'group_id', 'user_id')
            ->select([
                'users.*',
                'users_rel.tenant_id as tenant_id',
                'users_rel.lab_id as lab_id',
                'users_rel.group_id as group_id',
                'users_rel.role as role',
            ])
            ->withPivot(['id', 'tenant_id', 'lab_id', 'role', 'status'])
            ->wherePivot('status', 'active');
    }

    /**
     * Executa a rotina 'userRelations' no fluxo de negocio.
     */
    public function userRelations()
    {
        return $this->hasMany(UserRelation::class, 'group_id');
    }
}
