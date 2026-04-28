<?php

return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),
    'default_chat_model' => env('AI_DEFAULT_CHAT_MODEL', 'gpt-4o-mini'),
    'default_embedding_model' => env('AI_DEFAULT_EMBEDDING_MODEL', 'text-embedding-3-small'),

    'embedding' => [
        'dimensions' => 1536,
        'batch_size' => 100,
    ],

    'chunking' => [
        'tokens_per_chunk' => 500,
        'overlap_tokens' => 50,
        'chars_per_token' => 4,
    ],

    'retrieval' => [
        'top_k' => 6,
        'min_similarity' => 0.0,
    ],

    'daily_caps' => [
        'free' => (int) env('AI_FREE_DAILY_TOKENS', 50_000),
        'pro' => (int) env('AI_PRO_DAILY_TOKENS', 500_000),
    ],

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'chat_models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'],
            'embedding_model' => 'text-embedding-3-small',
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'chat_models' => ['claude-haiku-4-5', 'claude-sonnet-4-6', 'claude-opus-4-7'],
            'version' => '2023-06-01',
        ],
    ],
];
