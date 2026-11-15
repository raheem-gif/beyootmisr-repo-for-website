<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** 180 days (Anti-Cybercrime Law, Article 2), then deleted. */
    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(180));
    }
}
