<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function canManageWorkspace(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            self::Member, self::Viewer => false,
        };
    }

    public function canDeleteWorkspace(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Whether the role may create, update or delete workspace content.
     * Viewers have read-only access to everything they can see.
     */
    public function canEdit(): bool
    {
        return $this !== self::Viewer;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Roles that may be handed out through an invitation. Ownership transfers
     * are a separate operation, so Owner is never invitable.
     *
     * @return array<int, string>
     */
    public static function invitableValues(): array
    {
        return [self::Admin->value, self::Member->value, self::Viewer->value];
    }
}
