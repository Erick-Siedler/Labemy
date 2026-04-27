<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubFolder extends Model
{
    protected $fillable = [
        'tenant_id',
        'lab_id',
        'group_id',
        'project_id',
        'name',
        'slug',
        'description',
        'order_index',
        'current_version',
    ];

    /**
     * Executa a rotina 'project' no fluxo de negocio.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
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
     * Executa a rotina 'versions' no fluxo de negocio.
     */
    public function versions()
    {
        return $this->hasMany(ProjectVersion::class, 'subfolder_id');
    }
}
