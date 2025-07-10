<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramUser extends BaseModel
{
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
}
