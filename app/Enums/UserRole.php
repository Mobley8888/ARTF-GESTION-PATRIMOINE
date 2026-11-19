<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Administrator = 'admin';
    case AssetManager = 'asset_manager';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrateur',
            self::Administrator => 'Administrateur',
            self::AssetManager => 'Gestionnaire du patrimoine',
            self::Viewer => 'Agent de consultation',
        };
    }

    public function canManageAssets(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Administrator, self::AssetManager], true);
    }

    public function canManageAssignments(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Administrator, self::AssetManager], true);
    }
}
