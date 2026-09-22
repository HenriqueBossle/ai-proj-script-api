<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Script extends Model
{
    protected $fillable = [
        'user_id',
        'topic',
        'source_text',
        'style_text',
        'content',
        'status',
        'error_message'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
