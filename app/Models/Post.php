<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title',
        'slug',
        'source_url',
        'original_content',
        'category',
        'ai_tone',
        'ai_summary',
        'ai_tags',
        'ai_rewritten_content',
        'thumbnail',
        'metadata',
        'status',
        'last_ai_error',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $post): void {
            if (! filled($post->title) || filled($post->slug)) {
                return;
            }

            $base = Str::slug($post->title);
            if ($base === '') {
                $base = 'bai-viet';
            }

            $slug = $base;
            $n = 1;
            while (static::query()
                ->where('slug', $slug)
                ->when($post->exists, fn ($q) => $q->whereKeyNot($post->getKey()))
                ->exists()) {
                $slug = $base.'-'.$n;
                $n++;
            }
            $post->slug = $slug;
        });
    }
}
