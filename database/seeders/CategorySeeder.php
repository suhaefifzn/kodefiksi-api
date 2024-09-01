<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::factory()->create([
            'name' => 'Anime'
        ]);

        Category::factory()->create([
            'name' => 'Game'
        ]);

        Category::factory()->create([
            'name' => 'Pemrograman',
        ]);
    }
}
