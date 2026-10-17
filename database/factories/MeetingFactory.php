<?php

namespace Database\Factories;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = Carbon::now()->addDays(fake()->numberBetween(1, 14));
        $startHour = fake()->numberBetween(9, 16);

        return [
            'title' => fake()->sentence(3).' Sync',
            'description' => fake()->paragraph(),
            'meeting_date' => $date->toDateString(),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $startHour + 1),
            'meeting_link' => 'https://meet.google.com/'.fake()->lexify('???-????-???'),
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'status' => MeetingStatus::Scheduled->value,
        ];
    }

    /**
     * Indicate that the meeting is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MeetingStatus::Completed->value,
            'meeting_date' => Carbon::now()->subDays(1)->toDateString(),
        ]);
    }

    /**
     * Indicate that the meeting is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MeetingStatus::Cancelled->value,
        ]);
    }
}
