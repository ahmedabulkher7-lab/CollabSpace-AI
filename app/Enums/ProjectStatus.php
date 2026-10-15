<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Pending = 'Pending';
    case InProgress = 'In Progress';
    case OnHold = 'On Hold';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
