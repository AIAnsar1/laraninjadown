<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;

class TelegramUser extends BaseModel
{
    use QueryCacheable;

    protected $fillable = [
        'user_id',
        'username',
        'name',
        'surname',
        'age',
        'description',
        'phone',
        'language',
    ];

    /**
     * The tags for the query cache. Can be useful
     * if flushing cache for specific tags only.
     *
     * @var null|array
     */
    public $cacheTags = ['telegram_users'];

    /**
     * A cache prefix string that will be prefixed
     * on each cache key generation.
     *
     * @var string
     */
    public $cachePrefix = 'telegram_users_';

}
