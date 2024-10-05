<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Article;
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
            'username' => 'suhaefi21',
            'email' => 'suhaefi21@gmail.com',
            'password' => Hash::make(config('app.my_config.user_password')),
            'is_admin' => true
        ]);

        $this->call(CategorySeeder::class);
        Article::factory(200)->create();
    }
}
