<?php

namespace App\Enums;

enum InventoryCampaignStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case ToVerify = 'to_verify';
    case Validated = 'validated';
    case Closed = 'closed';
}
