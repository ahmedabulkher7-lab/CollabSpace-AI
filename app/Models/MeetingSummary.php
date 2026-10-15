<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'meeting_id',
    'summary',
    'decisions_made',
    'action_items',
    'assigned_tasks',
    'risks',
    'next_steps',
    'generated_by_ai',
])]
class MeetingSummary extends Model
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
            'decisions_made' => 'array',
            'action_items' => 'array',
            'assigned_tasks' => 'array',
            'risks' => 'array',
            'next_steps' => 'array',
            'generated_by_ai' => 'boolean',
        ];
    }

    /**
     * The meeting this summary belongs to.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
