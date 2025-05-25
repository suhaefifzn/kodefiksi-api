<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

// Models - tables
use App\Models\Category;
use App\Models\User;

class Article extends Model
{
    use HasFactory, HasSlug;

    protected $guarded = ['id'];
    protected $hidden = ['id'];

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function category() {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function language() {
        return $this->belongsTo(Language::class, 'lang_id', 'id');
    }

    public function type() {
        return $this->belongsTo(ArticleType::class, 'article_type_id', 'id');
    }
}
