<?php

return [
    /*
    | Relic AI: Care (buyer/seller) + Ops (admin).
    | NLU cục bộ (Classifier) + kiến thức sàn + LLM (OpenAI/Gemini) khi có API key.
    */
    'name' => 'Relic AI',
    'care' => [
        'max_message' => 2000,
        'product_cards' => 4,
        // true = trả lời đầy đủ ngay, không hỏi “muốn nghe thêm?”
        'full_answers' => true,
    ],
    'ops' => [
        'max_message' => 800,
        'list_limit' => 8,
        'auto_approve' => false,
    ],
    'llm' => [
        'enabled' => env('AI_LLM_ENABLED', true),
        // auto (gemini → groq → openrouter → openai) | gemini | groq | openrouter | openai | off
        'provider' => env('AI_LLM_PROVIDER', 'auto'),
        'temperature' => (float) env('AI_LLM_TEMPERATURE', 0.4),
        'max_tokens' => (int) env('AI_LLM_MAX_TOKENS', 400),
        'max_reply_chars' => 1200,
        'timeout' => (int) env('AI_LLM_TIMEOUT', 20),
        'history_turns' => (int) env('AI_LLM_HISTORY_TURNS', 6),
    ],
];
