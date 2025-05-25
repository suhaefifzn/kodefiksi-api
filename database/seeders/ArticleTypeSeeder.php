<?php

namespace Database\Seeders;

use App\Models\ArticleType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ArticleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ArticleType::create([
            'name' => 'NewsArticle'
        ]);

        ArticleType::create([
            'name' => 'BlogPost'
        ]);

        ArticleType::create([
            'name' => 'Review'
        ]);

        ArticleType::create([
            'name' => 'ItemList'
        ]);

        ArticleType::create([
            'name' => 'HowTo'
        ]);

        ArticleType::create([
            'name' => 'CreativeWork'
        ]);
    }
}
