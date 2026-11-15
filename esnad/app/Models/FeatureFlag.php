<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    use Audited;

    protected $fillable = ['key', 'enabled', 'description'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
