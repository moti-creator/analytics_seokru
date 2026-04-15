<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatBinding extends Model
{
    protected $guarded = [];
    protected $casts = ['auth_token_expires_at' => 'datetime'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
