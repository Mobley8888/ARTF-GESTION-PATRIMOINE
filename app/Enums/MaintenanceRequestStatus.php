<?php

namespace App\Enums;

enum MaintenanceRequestStatus: string
{
    case New = 'new';
    case PendingValidation = 'pending_validation';
    case Approved = 'approved';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Suspended = 'suspended';
    case PendingClosure = 'pending_closure';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nouvelle',
            self::PendingValidation => 'En attente de validation',
            self::Approved => 'Validée',
            self::Planned => 'Planifiée',
            self::InProgress => 'En cours',
            self::Suspended => 'Suspendue',
            self::PendingClosure => 'À clôturer',
            self::Completed => 'Terminée',
            self::Rejected => 'Rejetée',
            self::Cancelled => 'Annulée',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::PendingValidation, self::Approved, self::Planned, self::InProgress, self::Suspended, self::PendingClosure], true);
    }
}
