<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectVersion extends Model
{
    protected $fillable = [
        'tenant_id', 'lab_id', 'group_id', 'project_id', 'version_number', 'title', 'description', 'status_version', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at'
    ];

    public function files()
    {
        return $this->hasMany(\App\Models\ProjectFile::class, 'project_versions_id');
    }

    public function comments()
    {
        return $this->hasMany(ProjectComment::class, 'project_version_id');
    }
}
