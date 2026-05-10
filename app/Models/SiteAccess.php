<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAccess extends Model
{
    protected $fillable = [
        'path',
        'ip',
        'referer',
        'user_agent',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];
}
