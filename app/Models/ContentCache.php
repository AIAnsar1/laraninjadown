<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;

class ContentCache extends BaseModel
{
    use QueryCacheable;

    protected $fillable = [
        'title',
        'content_link',
        'quality',
        'formats',
        'chat_id',
        'message_id',
        'file_id',
    ];

    /**
     * The tags for the query cache. Can be useful
     * if flushing cache for specific tags only.
     *
     * @var null|array
     */
    public $cacheTags = ['content_cache'];

    /**
     * A cache prefix string that will be prefixed
     * on each cache key generation.
     *
     * @var string
     */
    public $cachePrefix = 'content_cache_';
}
