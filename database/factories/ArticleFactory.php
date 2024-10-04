<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // random user
        $users = User::all()->pluck('id')->flatten()->toArray();
        $randomUser = array_rand($users);

        // wrapp paragraps with tag p
        $paragraphs = fake()->paragraphs(rand(10, 20));
        $wrappedParagraphs = array_map(function ($paragraph, $index) {
            $image = '';

            if ($index === 2) {
                $image = '<figure><img src="https://picsum.photos/1280/720" alt="Random Image" class="img-fluid"/></figure>';
            }

            return "<p>$paragraph</p>$image";
        }, $paragraphs, array_keys($paragraphs));
        $implodedParagraphs = implode('', $wrappedParagraphs);

        // random category
        $categories = [1, 2, 3];
        $randomCategory = array_rand($categories);

        // random is_daraft
        $isDrafts = [false, true];
        $randomIsDraft = array_rand($isDrafts);

        return [
            'user_id' => $users[$randomUser],
            'category_id' => $categories[$randomCategory],
            'title' => fake()->words(10, true),
            'img_thumbnail' => 'https://picsum.photos/1280/720',
            'excerpt' => Str::limit(strip_tags($implodedParagraphs), 150),
            'body' => $implodedParagraphs,
            'is_draft' => $isDrafts[$randomIsDraft]
        ];
    }
}
