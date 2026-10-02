@include('ai.widget', [
    'title' => 'Relic Ops',
    'tone' => 'ops',
    'mark' => 'O',
    'subtitle' => 'Phụ tá vận hành',
    'hello' => 'Ê, Ops đây. Cứ hỏi tự nhiên — hôm nay có gì mới, mở trang, doanh thu…',
    'endpoint' => route('mcp'),
    'tool' => 'relic.ops',
    'chips' => ['Hôm nay có gì mới?', 'Đánh giá doanh số', 'Mở tin chờ', 'Dự tính doanh thu 15 ngày', 'Bao nhiêu user?', 'KYC chờ', 'Soạn từ chối KYC', 'Hàng ưu tiên'],
    'history' => session('relic.ai.ops.log', []),
    'open' => (bool) session()->pull('relic.ai.ops.open', false),
])
