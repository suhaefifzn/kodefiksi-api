<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $guarded = ['id'];

    public function articles() {
        return $this->hasMany(Article::class, 'lang_id', 'id');
    }
}
