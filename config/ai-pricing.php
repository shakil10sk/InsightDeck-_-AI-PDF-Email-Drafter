<?php

// USD per 1,000 tokens. Update as providers change pricing.
return [
    'openai' => [
        'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.00060],
        'gpt-4o' => ['input' => 0.00250, 'output' => 0.01000],
        'gpt-4-turbo' => ['input' => 0.01000, 'output' => 0.03000],
        'text-embedding-3-small' => ['input' => 0.00002, 'output' => 0.0],
        'text-embedding-3-large' => ['input' => 0.00013, 'output' => 0.0],
    ],
    'anthropic' => [
        'claude-haiku-4-5' => ['input' => 0.00025, 'output' => 0.00125],
        'claude-sonnet-4-6' => ['input' => 0.00300, 'output' => 0.01500],
        'claude-opus-4-7' => ['input' => 0.01500, 'output' => 0.07500],
    ],
];
