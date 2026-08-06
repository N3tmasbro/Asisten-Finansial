<?php

namespace App\Enums;

enum SubscriptionTier: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';
    case Business = 'business';
}
