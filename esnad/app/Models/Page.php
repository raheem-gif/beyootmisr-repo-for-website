<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use Audited;

    protected $fillable = [
        'slug', 'kind', 'title_ar', 'title_en', 'body_ar', 'body_en', 'version', 'is_published', 'counsel_reviewed_on', 'counsel_name', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'counsel_reviewed_on' => 'date'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function title(): string
    {
        return app()->getLocale() === 'en' ? $this->title_en : $this->title_ar;
    }

    public function body(): string
    {
        return app()->getLocale() === 'en' ? $this->body_en : $this->body_ar;
    }
}
