<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectVersion extends Model
{
    protected $fillable = [
        'tenant_id', 'lab_id', 'group_id', 'project_id', 'subfolder_id', 'version_number', 'title', 'description', 'status_version', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Executa a rotina 'subfolder' no fluxo de negocio.
     */
    public function subfolder()
    {
        return $this->belongsTo(SubFolder::class, 'subfolder_id');
    }

    /**
     * Executa a rotina 'files' no fluxo de negocio.
     */
    public function files()
    {
        return $this->hasMany(\App\Models\ProjectFile::class, 'project_versions_id');
    }

    /**
     * Executa a rotina 'comments' no fluxo de negocio.
     */
    public function comments()
    {
        return $this->hasMany(ProjectComment::class, 'project_version_id');
    }
}
