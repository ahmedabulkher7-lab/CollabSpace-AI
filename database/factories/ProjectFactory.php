<?php

namespace Database\Factories;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = Carbon::now()->subDays(fake()->numberBetween(1, 30));
        $deadline = Carbon::now()->addDays(fake()->numberBetween(10, 60));

        return [
            'name' => fake()->unique()->catchPhrase().' Project',
            'description' => fake()->paragraph(),
            'start_date' => $startDate->toDateString(),
            'deadline' => $deadline->toDateString(),
            'priority' => fake()->randomElement(ProjectPriority::values()),
            'status' => fake()->randomElement(ProjectStatus::values()),
            'progress_percentage' => fake()->randomFloat(2, 0, 100),
            'created_by' => User::factory(),
        ];
    }
}
