<?php

namespace App\Enums;

enum InventoryItemStatus: string
{
    case Pending = 'pending';
    case PresentConform = 'present_conform';
    case PresentAnomaly = 'present_anomaly';
    case Absent = 'absent';
    case Moved = 'moved';
    case Damaged = 'damaged';
    case Unidentified = 'unidentified';
    case Discovered = 'discovered';
}
