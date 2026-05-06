<?php

namespace App\Enums;

enum SenderRole: string
{
    case System = 'system';
    case Tenant = 'tenant';
    case Landlord = 'landlord';
}
