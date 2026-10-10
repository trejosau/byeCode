<?php

return [
    'rules_version' => 'r1-r6-v1.0',
    'prompt_version' => 'byecode-semantic-1.1',
    'kit_clock' => '2026-10-09T16:00:00Z',
    'model' => env('OPENAI_MODEL') ?: 'gpt-5.6-luna',
    'max_output_tokens' => 1800,
    'max_context_bytes' => 20000,
    'max_import_bytes' => 2097152,
    'max_import_lines' => 2000,
    'budget' => [
        'daily_mxn' => 22,
        'weekly_mxn' => 160,
        'daily_calls' => 200,
        'mxn_per_usd' => 25,
        'safety_multiplier' => 1.25,
    ],
    // USD / 1M tokens. Unknown model IDs are blocked, never priced by guesswork.
    'prices' => [
        'gpt-5.6-luna' => ['input' => 0.20, 'output' => 1.20],
        'gpt-4.1-mini-2025-04-14' => ['input' => 0.40, 'output' => 1.60],
    ],
];
