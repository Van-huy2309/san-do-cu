@include('ai.widget', [
    'title' => 'Relic Care',
    'tone' => 'care',
    'mark' => 'C',
    'subtitle' => 'AI tư vấn mua · bán · đơn · escrow',
    'hello' => 'Chào bạn, Relic Care đây. Bạn cần tìm máy, xem đơn hay hỏi về thanh toán?',
    'endpoint' => route('mcp'),
    'tool' => 'relic.care',
    'maxMessage' => (int) config('ai.care.max_message', 2000),
    'placeholder' => 'Hỏi Care về mua bán trên Relic…',
    'chips' => [],
    'history' => session('relic.ai.care.log', []),
    'open' => session('relic.ai.open') || session()->pull('relic.ai.care.open', false),
])
