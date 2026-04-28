<?php

namespace App\Enums;

enum UsageAction: string
{
    case Embed = 'embed';
    case Chat = 'chat';
    case Summarize = 'summarize';
    case Draft = 'draft';
    case Title = 'title';
}
