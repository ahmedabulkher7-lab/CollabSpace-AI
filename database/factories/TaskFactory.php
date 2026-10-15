<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'project_id' => Project::factory(),
            'team_id' => null,
            'assigned_to' => User::factory(),
            'created_by' => User::factory(),
            'priority' => fake()->randomElement(TaskPriority::values()),
            'status' => TaskStatus::Pending->value,
            'due_date' => Carbon::now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Completed->value,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Indicate that the task is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::InProgress->value,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the task is in review.
     */
    public function inReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::InReview->value,
            'completed_at' => null,
        ]);
    }
}
