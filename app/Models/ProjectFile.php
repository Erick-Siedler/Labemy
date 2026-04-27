<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectFile extends Model
{
    protected $fillable = [
        'tenant_id',
        'lab_id',
        'group_id',
        'project_versions_id',
        'uploaded_by',
        'original_name',
        'stored_name',
        'path',
        'extension',
        'mime_type',
        'size',
        'type',
    ];
}
