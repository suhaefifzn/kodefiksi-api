<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Suhaefi',
            'username' => 'suhaefifzn',
            'email' => 'suhaefi21@gmail.com',
            'password' => Hash::make('password'),
            'is_admin' => true
        ]);

        Category::factory()->create();
        Article::factory()->create();
    }
}
