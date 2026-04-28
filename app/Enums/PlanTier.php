<?php

namespace App\Enums;

enum PlanTier: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Unlimited = 'unlimited';
}
