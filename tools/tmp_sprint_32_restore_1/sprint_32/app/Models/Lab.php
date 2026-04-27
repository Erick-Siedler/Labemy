<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Group;
use App\Models\Project;
use App\Models\SubUsers;

class Lab extends Model
{

    protected $fillable = [
        'tenant_id', 'creator_id', 'creator_subuser_id', 'name', 'code', 'status'
    ];

    public function groups()
    {   
        return $this->hasMany(Group::class);
    }

    public function projects(){
        return $this->hasMany(Project::class);
    }

    public function subUsers(){
        return $this->hasMany(SubUsers::class);
    }

    public function creatorSubuser()
    {
        return $this->belongsTo(SubUsers::class, 'creator_subuser_id');
    }
}
