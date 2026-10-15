<?php

namespace App\Models;

use App\Enums\AuditLogCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'category',
    'action',
    'description',
    'ip_address',
    'user_agent',
    'payload',
])]
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => AuditLogCategory::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The user associated with the audit log.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
