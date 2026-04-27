<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class FuncReq extends Model
{
    protected $table = 'func_reqs';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'created_by_table',
        'created_by_id',
        'code',
        'title',
        'description',
        'priority',
        'status',
        'acceptance_criteria',
    ];

    /**
     * Executa a rotina 'project' no fluxo de negocio.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Executa a rotina 'nonFunctional' no fluxo de negocio.
     */
    public function nonFunctional(): BelongsToMany
    {
        return $this->belongsToMany(
            NonFuncReq::class,
            'func_non_func',
            'func_req_id',
            'non_func_req_id'
        )->withTimestamps();
    }

    /**
     * Executa a rotina 'scopeForTenant' no fluxo de negocio.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Executa a rotina 'scopeForProject' no fluxo de negocio.
     */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }
}

