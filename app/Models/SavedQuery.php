<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedQuery extends Model
{
    protected $fillable = ['connection_id', 'label', 'prompt'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
