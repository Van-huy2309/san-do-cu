<?php

namespace App\Services;

/**
 * Chặn câu hỏi/câu trả lời lộ dữ liệu nội bộ của sàn cho Relic Care.
 * Chuỗi so khớp đã bỏ dấu, viết thường.
 */
class RelicCareGuard
{
    public const REFUSAL = 'Xin lỗi, mình không được trả lời thông tin này.';

    private const PHRASES = [
        // phí / hoa hồng / tài chính sàn
        'phi san', 'chiet khau', 'hoa hong', 'commission', 'phi day tin', 'phi day',
        'shop nhan bao nhieu', 'shop nhan may', 'san giu bao nhieu', 'tru bao nhieu',
        'bi tru bao nhieu', 'phi giao dich', 'ty le phi', 'muc phi', 'bao nhieu %',
        'bao nhieu phan tram', 'phan tram san', 'boost fee', 'phi boost', 'vi sao tru',
        'doanh thu', 'doanh so', 'loi nhuan', 'bao cao tai chinh', 'dong tien',
        'san kiem', 'san lai', 'gmv',
        // người dùng
        'danh sach user', 'danh sach nguoi dung', 'danh sach khach', 'danh sach shop',
        'danh sach nguoi ban', 'danh sach tai khoan', 'thong tin user', 'thong tin nguoi dung',
        'thong tin khach hang', 'thong tin nguoi ban', 'thong tin shop', 'email user',
        'email cua', 'sdt cua', 'so dien thoai cua', 'dia chi cua', 'cccd cua', 'cmnd cua',
        'user khac', 'nguoi dung khac', 'tai khoan khac', 'nick khac', 'khach hang khac',
        'nguoi mua khac', 'ai dang online', 'ai da mua', 'ai dang ky',
        'tong user', 'so luong user', 'so user', 'so nguoi dung', 'so thanh vien',
        'so luong nguoi dung', 'so luong thanh vien', 'so luong khach', 'so luong shop',
        'so luong nguoi ban', 'user moi', 'nguoi dung moi',
        // số lượng sản phẩm / đơn / tồn kho
        'so luong san pham', 'so luong tin', 'so luong hang', 'so luong may', 'so luong don',
        'tong so san pham', 'tong so tin', 'tong so don', 'tong don', 'tong san pham',
        'ton kho', 'hang ton', 'da ban duoc bao nhieu', 'ban duoc bao nhieu', 'luot ban',
        'luot xem', 'bao nhieu don toan san', 'thong ke', 'so lieu', 'bao cao so',
        // hệ thống / bảo mật
        'admin password', 'mat khau admin', 'tai khoan admin', 'api key', 'secret',
        'token', 'database', 'co so du lieu', 'sql', '.env', 'cau hinh he thong',
        'cau hinh server', 'source code', 'ma nguon', 'server', 'may chu', 'hack', 'lo hong', 'bypass',
        // prompt injection
        'system prompt', 'prompt he thong', 'bo qua huong dan', 'bo qua lenh',
        'ignore previous', 'ignore all', 'ignore the above', 'developer mode', 'jailbreak',
        'dan mode', 'quen het', 'dong vai admin', 'gia lam admin', 'ban la admin',
        'toi la admin', 'che do admin',
    ];

    private const ENTITIES = '(user|users|nguoi dung|thanh vien|tai khoan|khach hang|shop|seller|nguoi ban|nguoi mua|'
        .'san pham|tin dang|mat hang|don hang|giao dich|luot)';

    private const PRICE_WORDS = '/\b(gia|tien|trieu|tr|k|nghin|ngan|dong|vnd|bao lau|ngay|gio|pin|gb|tb|inch|ram)\b/u';

    public function blocksQuestion(string $text): bool
    {
        $text = preg_replace('/\bcua (toi|minh|em|tao|tui)\b/u', '', $text) ?? $text;
        if (trim($text) === '') {
            return false;
        }

        foreach (self::PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }

        $priceLike = (bool) preg_match(self::PRICE_WORDS, $text)
            || (bool) preg_match('/\b(nay|kia|ay)\b/u', $text);

        // “có bao nhiêu / tổng / mấy + user/sản phẩm/đơn…”
        if (! $priceLike && preg_match('/\b(bao nhieu|tong cong|tong so|dem)\b.{0,40}\b'.self::ENTITIES.'\b/u', $text)) {
            return true;
        }
        if (! $priceLike && preg_match('/\b'.self::ENTITIES.'\b.{0,30}\b(bao nhieu|may cai|may nguoi|may chiec)\b/u', $text)) {
            return true;
        }
        // “sàn có bao nhiêu…”, “bao nhiêu máy/tin đang bán…”
        if (! $priceLike && preg_match('/\b(san|relic|web|trang)\b.{0,15}\b(co|dang co|hien co)?\s*bao nhieu\b/u', $text)) {
            return true;
        }
        if (preg_match('/\b(bao nhieu|may)\s+(chiec |cai |mon )?(may|tin|don|mon|nguoi|user|shop)\b.{0,25}\b(dang ban|tren san|dang co|da ban|con lai|hien co|toan san|dang ky|online)\b/u', $text)) {
            return true;
        }

        // “x% phí/hoa hồng”
        if (preg_match('/\b\d+\s*%/u', $text) && preg_match('/\b(phi|hoa|chiet|san|shop|nhan|tru)\b/u', $text)) {
            return true;
        }

        return false;
    }

    /** Câu trả lời LLM có lộ số liệu nội bộ không. */
    public function leaksInAnswer(string $answer): bool
    {
        $t = $this->norm($answer);

        if (preg_match('/\b\d+([.,]\d+)?\s*%/u', $answer) && preg_match('/\b(phi|hoa hong|chiet khau|san giu|san thu|shop nhan|tru)\b/u', $t)) {
            return true;
        }
        if (preg_match('/\b\d[\d.,]*\s*(user|nguoi dung|thanh vien|tai khoan|khach hang|shop|nguoi ban|san pham|tin dang|mat hang|don hang|giao dich)\b/u', $t)) {
            return true;
        }
        foreach (['hoa hong', 'doanh thu', 'loi nhuan', 'api key', 'system prompt', 'mat khau admin', 'ty le phi'] as $bad) {
            if (str_contains($t, $bad)) {
                return true;
            }
        }
        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $answer) || preg_match('/\b0\d{9,10}\b/u', $answer)) {
            return true;
        }

        return false;
    }

    public function norm(string $message): string
    {
        $s = mb_strtolower(trim($message));
        $from = ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ','đ'];
        $to = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];

        return preg_replace('/\s+/', ' ', str_replace($from, $to, $s)) ?? str_replace($from, $to, $s);
    }
}
