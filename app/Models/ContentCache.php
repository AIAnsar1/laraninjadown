<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentCache extends BaseModel
{
    protected $fillable = [
        'title',
        'content_link',
        'quality',
        'formats',
        'chat_id',
        'message_id',
        'file_id',
    ];
}
