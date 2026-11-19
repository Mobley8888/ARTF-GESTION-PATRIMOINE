<?php

namespace App\Enums;

enum LocationType: string
{
    case Site = 'site';
    case Building = 'building';
    case Batiment = 'batiment';
    case Room = 'room';
    case Local = 'local';

    public function parentTypes(): array
    {
        return match (true) {
            $this->isSite() => [],
            $this->isBuilding() => [self::Site],
            $this->isLocal() => [self::Site, self::Building, self::Batiment],
        };
    }

    public function acceptsParent(self $parent): bool
    {
        return match (true) {
            $this->isBuilding() => $parent->isSite(),
            $this->isLocal() => $parent->isSite() || $parent->isBuilding(),
            default => false,
        };
    }

    public function isSite(): bool
    {
        return $this === self::Site;
    }

    public function isBuilding(): bool
    {
        return in_array($this, [self::Building, self::Batiment], true);
    }

    public function isLocal(): bool
    {
        return in_array($this, [self::Room, self::Local], true);
    }

    public function label(): string
    {
        return match (true) {
            $this->isSite() => 'Site',
            $this->isBuilding() => 'Bâtiment',
            $this->isLocal() => 'Local',
        };
    }

    public function canonical(): self
    {
        return match (true) {
            $this->isSite() => self::Site,
            $this->isBuilding() => self::Batiment,
            $this->isLocal() => self::Local,
        };
    }

    public function familyValues(): array
    {
        return match (true) {
            $this->isSite() => [self::Site->value],
            $this->isBuilding() => self::buildingValues(),
            $this->isLocal() => self::localValues(),
        };
    }

    public static function buildingValues(): array
    {
        return [self::Building->value, self::Batiment->value];
    }

    public static function localValues(): array
    {
        return [self::Room->value, self::Local->value];
    }
}
