<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMessage>
 */
class ProjectMessageFactory extends Factory
{
    protected $model = ProjectMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'file_attachment_id' => null,
            'message' => fake()->sentence(8),
            'is_edited' => false,
        ];
    }
}
