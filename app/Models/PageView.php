<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    protected $table = 'page_views';
    protected $primaryKey = 'page_view_id';
    public $timestamps = false;

    protected $fillable = [
        'view_date_time',
        'page_name',
        'ip_address',
        'user_agent',
        'cookies',
    ];

    protected $casts = [
        'view_date_time' => 'datetime',
    ];
}
