<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SubUsers;

class ProjectComment extends Model
{
    protected $fillable = [
        'tenant_id',
        'project_version_id',
        'creator_id',
        'creator_subuser_id',
        'description',
    ];

    public function version()
    {
        return $this->belongsTo(ProjectVersion::class, 'project_version_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function subCreator()
    {
        return $this->belongsTo(SubUsers::class, 'creator_subuser_id');
    }
}
