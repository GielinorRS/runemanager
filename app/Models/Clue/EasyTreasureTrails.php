<?php

namespace App\Models\Clue;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EasyTreasureTrails extends Model
{
    protected $table = 'easy_treasure_trails';

    protected $fillable = [
        'obtained',
        'kill_count',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
