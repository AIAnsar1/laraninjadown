<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Advertisements extends BaseModel
{
    protected $fillable = [
        'ad_uuid',
        'content',
        'media_type',
        'media_file_id',
        'target_lang',
        'is_active',
    ];

    public function deliveries()
    {
        return $this->hasMany(AdvertisementsDeliveries::class, 'ad_id');
    }
}
