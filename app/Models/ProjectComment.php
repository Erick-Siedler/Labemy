<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectComment extends Model
{
    protected $fillable = [
        'tenant_id',
        'project_version_id',
        'creator_id',
        'creator_subuser_id',
        'description',
    ];

    /**
     * Executa a rotina 'version' no fluxo de negocio.
     */
    public function version()
    {
        return $this->belongsTo(ProjectVersion::class, 'project_version_id');
    }

    /**
     * Executa a rotina 'creator' no fluxo de negocio.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Executa a rotina 'subCreator' no fluxo de negocio.
     */
    public function subCreator()
    {
        return $this->belongsTo(User::class, 'creator_subuser_id');
    }
}
