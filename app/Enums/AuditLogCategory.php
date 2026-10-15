<?php

namespace App\Enums;

enum AuditLogCategory: string
{
    case Auth = 'auth';
    case Project = 'project';
    case Task = 'task';
    case File = 'file';
    case Chat = 'chat';
    case Meeting = 'meeting';
    case Ai = 'ai';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
