<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SubFolder;

class Project extends Model
{   

    protected $fillable =[
        'tenant_id', 'lab_id', 'group_id', 'title', 'slug', 'description', 'status', 'current_version', 'submitted_at', 'approved_at'
    ];
    
    /**
     * Executa a rotina 'lab' no fluxo de negocio.
     */
    public function lab(){
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
     * Executa a rotina 'subfolders' no fluxo de negocio.
     */
    public function subfolders()
    {
        return $this->hasMany(SubFolder::class);
    }

    /**
     * Executa a rotina 'versions' no fluxo de negocio.
     */
    public function versions()
    {
        return $this->hasMany(ProjectVersion::class);
    }

    /**
     * Executa a rotina 'funcReqs' no fluxo de negocio.
     */
    public function funcReqs()
    {
        return $this->hasMany(FuncReq::class);
    }

    /**
     * Executa a rotina 'nonFuncReqs' no fluxo de negocio.
     */
    public function nonFuncReqs()
    {
        return $this->hasMany(NonFuncReq::class);
    }
}
