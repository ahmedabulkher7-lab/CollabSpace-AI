<?php

namespace Database\Factories;

use App\Models\FileAttachment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileAttachment>
 */
class FileAttachmentFactory extends Factory
{
    protected $model = FileAttachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['pdf', 'png', 'docx', 'jpg', 'zip']);
        $filename = fake()->slug(2).'.'.$extension;

        return [
            'name' => $filename,
            'file_path' => 'attachments/projects/1/'.$filename,
            'disk' => 'public',
            'file_size' => fake()->numberBetween(10240, 5242880), // 10KB to 5MB
            'mime_type' => match ($extension) {
                'pdf' => 'application/pdf',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'zip' => 'application/zip',
                default => 'application/octet-stream',
            },
            'uploaded_by' => User::factory(),
            'project_id' => Project::factory(),
            'task_id' => null,
        ];
    }
}
