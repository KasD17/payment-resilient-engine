<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Incomplete = 'incomplete';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Unpaid = 'unpaid';
    case Trialing = 'trialing';
}
