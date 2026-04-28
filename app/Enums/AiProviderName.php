<?php

namespace App\Enums;

enum AiProviderName: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
}
