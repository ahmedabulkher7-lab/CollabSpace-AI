<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'Administrator';
    case ProjectManager = 'Project Manager';
    case TeamMember = 'Team Member';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
