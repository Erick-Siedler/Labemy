<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProjectVersion;

class Task extends Model
{
    protected $fillable = [
        'tenant_id', 'project_id', 'version_id', 'title', 'description', 'status'
    ];

    public function version()
    {
        return $this->belongsTo(ProjectVersion::class, 'version_id');
    }
}
