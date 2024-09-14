<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        $paragraphs = fake()->paragraphs(rand(6, 10));
        $wrappedParagraphs = array_map(function ($paragraph, $index) {
            $image = '';

            if ($index === 2) {
                $image = '<img src="https://source.unsplash.com/japan/1280x720" alt="Random Image"/>';
            }

            return "<p>$paragraph</p>$image";
        }, $paragraphs, array_keys($paragraphs));
        $implodedParagraphs = implode('\n', $wrappedParagraphs);

        // random category
        $categories = [1, 2, 3];
        $randomCategory = array_rand($categories);

        // random is_daraft
        $isDrafts = [false, true];
        $randomIsDraft = array_rand($isDrafts);

        return [
            'user_id' => $users[$randomUser],
            'category_id' => $categories[$randomCategory],
            'title' => fake()->words(5, true),
            'img_thumbnail' => 'https://source.unsplash.com/programming/1280x720',
            'body' => $implodedParagraphs,
            'is_draft' => $isDrafts[$randomIsDraft]
        ];
    }
}
