<?php

namespace App\Models;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'description',
    'start_date',
    'deadline',
    'priority',
    'status',
    'progress_percentage',
    'created_by',
])]
class Project extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
            'priority' => ProjectPriority::class,
            'status' => ProjectStatus::class,
            'progress_percentage' => 'decimal:2',
        ];
    }

    /**
     * The manager/creator of the project.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Teams assigned to this project.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_team')
            ->withTimestamps();
    }

    /**
     * Users assigned directly to this project.
     *
     * @return BelongsToMany<User, $this>
     */
    public function directMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Tasks belonging to this project.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Files uploaded to this project.
     *
     * @return HasMany<FileAttachment, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(FileAttachment::class);
    }

    /**
     * Chat messages in this project channel.
     *
     * @return HasMany<ProjectMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class);
    }

    /**
     * Meetings scheduled for this project.
     *
     * @return HasMany<Meeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * AI interaction logs for this project.
     *
     * @return HasMany<AiInteraction, $this>
     */
    public function aiInteractions(): HasMany
    {
        return $this->hasMany(AiInteraction::class);
    }

    /**
     * Determine if a user has access to this project (creator, member, or via assigned teams).
     */
    public function hasAccess(User $user): bool
    {
        if ($user->hasRole('Administrator')) {
            return true;
        }

        if ($this->created_by === $user->id) {
            return true;
        }

        if ($this->directMembers()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return $this->teams()->whereHas('members', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })->exists();
    }

    /**
     * Recalculate and update the progress percentage based on completed tasks.
     */
    public function updateProgress(): void
    {
        $total = $this->tasks()->count();
        if ($total === 0) {
            return;
        }

        $completed = $this->tasks()->where('status', TaskStatus::Completed->value)->count();
        $this->update([
            'progress_percentage' => round(($completed / $total) * 100, 2),
        ]);
    }
}
