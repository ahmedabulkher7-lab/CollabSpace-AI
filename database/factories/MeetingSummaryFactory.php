<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingSummary>
 */
class MeetingSummaryFactory extends Factory
{
    protected $model = MeetingSummary::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'summary' => fake()->paragraph(3),
            'decisions_made' => [
                'Approved migration to MySQL 8.0 for production.',
                'Adopted JWT with Sanctum for stateless authentication.',
            ],
            'action_items' => [
                [
                    'title' => 'Finalize database migrations schema',
                    'suggested_assignee' => 'Backend Dev',
                    'priority' => 'High',
                ],
                [
                    'title' => 'Implement Figma responsive mobile components',
                    'suggested_assignee' => 'Frontend Dev',
                    'priority' => 'Medium',
                ],
            ],
            'assigned_tasks' => [
                'Backend Dev' => 'Finalize migrations',
                'Frontend Dev' => 'Responsive components',
            ],
            'risks' => [
                'Potential delay in OAuth verification approval by Google.',
            ],
            'next_steps' => [
                'Code review session scheduled for next Monday.',
            ],
            'generated_by_ai' => true,
        ];
    }
}
