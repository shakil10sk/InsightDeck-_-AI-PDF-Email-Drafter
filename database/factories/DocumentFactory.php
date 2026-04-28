<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'original_filename' => $this->faker->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(10_000, 5_000_000),
            'page_count' => $this->faker->numberBetween(1, 50),
            'status' => 'ready',
            'error_message' => null,
            'storage_disk' => 'local',
            'storage_path' => 'documents/test/'.$this->faker->uuid().'.pdf',
        ];
    }
}
