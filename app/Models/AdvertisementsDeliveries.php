<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisementsDeliveries extends BaseModel
{
    protected $fillable = [
        'ad_id',
        'user_id',
        'message_id',
        'sent_at',
    ];

    public function advertisement()
    {
        return $this->belongsTo(Advertisements::class, 'ad_id');
    }
}
