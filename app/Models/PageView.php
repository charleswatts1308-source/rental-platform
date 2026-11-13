<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    protected $table = 'page_views';
    protected $primaryKey = 'page_view_id';
    public $timestamps = false;

    /**
     * Snag #92: no cookies column, and page_name carries no query string.
     * See TrackPageViews.
     */
    protected $fillable = [
        'view_date_time',
        'page_name',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'view_date_time' => 'datetime',
    ];

    /**
     * User-agent fragments treated as crawlers. Shared by the Page views
     * screen and the admin dashboard so the two counts cannot drift.
     */
    public const CRAWLER_PATTERNS = [
        'Googlebot', 'Bingbot', 'YandexBot', 'Baiduspider', 'DuckDuckBot',
        'Slurp', 'facebot', 'ia_archiver', 'Applebot', 'AhrefsBot',
        'SemrushBot', 'MJ12bot', 'DotBot', 'PetalBot', 'Bytespider',
        'GPTBot', 'ClaudeBot', 'bot', 'crawler', 'spider',
    ];

    public function scopeCrawlers(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            foreach (self::CRAWLER_PATTERNS as $pattern) {
                $q->orWhere('user_agent', 'LIKE', '%' . $pattern . '%');
            }
        });
    }

    public function scopeHumans(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            foreach (self::CRAWLER_PATTERNS as $pattern) {
                $q->where('user_agent', 'NOT LIKE', '%' . $pattern . '%');
            }
        });
    }
}
