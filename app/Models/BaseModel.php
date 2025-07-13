<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\{Model, Builder};
use Spatie\Translatable\HasTranslations;
use Laravel\Passport\HasApiTokens;
use Rennokki\QueryCache\Traits\QueryCacheable;

/**
 * App\Models\BaseModel
 *
 * @method static Builder|self newModelQuery()
 * @method static Builder|self newQuery()
 * @method static Builder|self query()
 * @method static Builder|self filter(array $data)
 */


 abstract class BaseModel extends Model
 {
     use HasFactory, HasTranslations, Notifiable, HasApiTokens, QueryCacheable;

     /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [

    ];

    public $translatable = [

    ];

    /**
     * Specify the amount of time to cache queries.
     * Do not specify or set it to null to disable caching.
     *
     * @var int|\DateTime
     */
    public $cacheFor = 21600; // cache time, in seconds

    /**
     * The cache driver to be used.
     *
     * @var string
     */
    public $cacheDriver = 'redis';

    /*
     public function scopeFilter($query, $data)
     {
         if (isset($data['status']))
         {
             $query->whereHas('roles', function ($q) use ($data) {
                 $q->where('status', $data['status']);
             });
         }

         if (isset($data['role']))
         {
             $query->whereHas('roles', function ($q) use ($data) {
                 $q->where('role_code', $data['role']);
             });
         }
         return $query;
     }
         */


 }
