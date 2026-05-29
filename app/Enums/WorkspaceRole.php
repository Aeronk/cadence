<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function canManageWorkspace(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            self::Member => false,
        };
    }

    public function canDeleteWorkspace(): bool
    {
        return $this === self::Owner;
    }
}
