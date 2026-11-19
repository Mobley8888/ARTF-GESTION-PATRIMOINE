<?php

namespace App\Enums;

enum AssetStatus: string
{
    case InService = 'in_service';
    case UnderMaintenance = 'under_maintenance';
    case Retired = 'retired';
    case Disposed = 'disposed';
    case Lost = 'lost';
}
