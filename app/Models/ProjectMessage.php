<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id',
    'user_id',
    'parent_id',
    'file_attachment_id',
    'message',
    'is_edited',
])]
class ProjectMessage extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_edited' => 'boolean',
        ];
    }

    /**
     * The project this message belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The user who sent the message.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Parent message if this is a reply.
     *
     * @return BelongsTo<ProjectMessage, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProjectMessage::class, 'parent_id');
    }

    /**
     * Replies to this message.
     *
     * @return HasMany<ProjectMessage, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ProjectMessage::class, 'parent_id');
    }

    /**
     * Attached file if any.
     *
     * @return BelongsTo<FileAttachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(FileAttachment::class, 'file_attachment_id');
    }

    /**
     * Mentioned users in this message.
     *
     * @return BelongsToMany<User, $this>
     */
    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_mentions', 'message_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Users who read this message.
     *
     * @return BelongsToMany<User, $this>
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_reads', 'message_id', 'user_id')
            ->withPivot('read_at')
            ->withTimestamps();
    }
}
