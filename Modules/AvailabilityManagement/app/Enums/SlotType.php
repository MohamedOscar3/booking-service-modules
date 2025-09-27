<?php

namespace Modules\AvailabilityManagement\Enums;

enum SlotType: string
{
    case recurring = 'recurring';
    case once = 'once';
}
