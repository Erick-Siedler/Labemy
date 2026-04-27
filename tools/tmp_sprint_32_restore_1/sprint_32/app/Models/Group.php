<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Project;

class Group extends Model
{   

    protected $fillable = [
        'tenant_id', 'lab_id', 'creator_id', 'name', 'code', 'status'
    ];

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    public function projects_versions()
    {
        return $this->hasMany(ProjectVersion::class);
    }

    public function projects(){
        return $this->hasMany(Project::class);
    }

    public function subUsers(){
        return $this->hasMany(SubUsers::class);
    }
}
