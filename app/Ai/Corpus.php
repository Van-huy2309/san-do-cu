<?php

namespace App\Ai;

class Corpus
{
    /** @return list<array{text: string, intent: string}> */
    public function samples(): array
    {
        $out = [];
        foreach ($this->templates() as $intent => $lines) {
            foreach ($lines as $line) {
                $out[] = ['text' => $line, 'intent' => $intent];
            }
        }
        foreach (array_keys(Lexicon::aliases()) as $alias) {
            if (mb_strlen($alias) < 2) {
                continue;
            }
            $out[] = ['text' => $alias, 'intent' => 'search'];
            $out[] = ['text' => 'tim '.$alias, 'intent' => 'search'];
            $out[] = ['text' => 'find '.$alias, 'intent' => 'search'];
            $out[] = ['text' => $alias.' duoi 10tr', 'intent' => 'search'];
            $out[] = ['text' => 'mua '.$alias, 'intent' => 'search'];
            $out[] = ['text' => 'co '.$alias.' khong', 'intent' => 'search'];
        }

        return $out;
    }

    /** @return array<string, list<string>> */
    private function templates(): array
    {
        return [
            'greet' => [
                'hello', 'hello relic', 'hi', 'hi relic', 'hey', 'hey there', 'yo', 'alo', 'halo', 'helo',
                'good morning', 'good afternoon', 'good evening', 'morning',
                'xin chao', 'xin chào', 'xin chao ban', 'xin chao relic', 'chao', 'chao ban', 'chao relic',
                'chao shop', 'chao ad', 'chào bạn', 'chào', 'hii', 'hiii', 'hello ban', 'hi ban',
                'relic oi', 'cho minh hoi', 'care oi', 'chao care', 'hey care', 'minh can hoi',
                'co ai khong', 'ai oi', 'tro ly oi',
            ],
            'thanks' => [
                'cam on', 'cảm ơn', 'thanks', 'thank you', 'ty', 'tks', 'ok cam on', 'hay qua',
                'cam on nhieu', 'cam on care', 'perfect', 'tuyet voi', 'ro roi cam on',
            ],
            'bye' => [
                'tam biet', 'tạm biệt', 'bye', 'goodbye', 'pp', 'hen gap lai',
                'thoi nha', 'minh di day', 'see you', 'nghi day',
            ],
            'who' => [
                'ban la ai', 'may la ai', 'ai day', 'you are', 'who are you', 'relic la gi', 'ban ten gi',
                'care la gi', 'ban dung model gi', 'ban la chatgpt khong', 'ban la gemini khong',
                'tro ly ai la gi', 'gioi thieu ban than',
            ],
            'help' => [
                'help', 'giup toi', 'ban giup duoc gi', 'lam duoc gi', 'huong dan', 'can giup do',
                'ban can giup gi', 'toi can ho tro', 'menu', 'tinh nang', 'ho tro gi',
                'tu van toi', 'tu van mua may', 'huong dan su dung', 'care giup gi',
            ],
            'search' => [
                'tim may', 'tim san pham', 'may dang ban', 'co may nao', 'goi y may', 'muon mua may',
                'tim iphone', 'tim laptop', 'tim ipad', 'tim may anh', 'tim tai nghe',
                'iphone re', 'laptop gaming', 'may anh canon', 'dien thoai cu',
                'ip13 duoi 10tr', 's23u gia re', 'mba m1', 'tim ip13', 'find iphone 13',
                'search macbook', 'buy used phone', 'cheap laptop', 'co hang khong',
                'xem hang', 'show products', 'san pham nao dang co',
                'toi muon xem cac san pham dien thoai hien tai',
                'xem san pham dien thoai', 'dien thoai dang ban', 'laptop dang ban',
                'cac san pham iphone', 'liet ke dien thoai',
                'goi y dien thoai pin tot', 'may nao dang hot', 'hang moi dang',
                'tim airpods', 'tim apple watch', 'tim may choi game', 'ps5 gia re',
                'co iphone 13 khong', 'con macbook air khong', 'tim may cu zin',
                'dien thoai duoi 5 trieu', 'laptop duoi 15tr', 'may anh duoi 20tr',
                'san pham apple', 'hang samsung', 'may xiaomi',
            ],
            'followup' => [
                're hon', 're nua', 'dat hon', 'xem them', 'cai dau tien', 'con khong', 'may khac',
                'cheaper', 'more', 'next', 'con cai nao re hon', 'show more', 'them lua chon',
                'cai thu 2', 'cai cuoi', 'doi mau khac',
            ],
            'escrow' => [
                'escrow', 'escrow momo', 'giu tien', 'thanh toan momo', 'how escrow',
                'shop nhan tien khi nao', 'cod hay momo', 'tien nam o dau',
                'relic giu tien the nao', 'bao ve nguoi mua', 'khi nao shop nhan tien',
                'giai ngan the nao', 'escrow la gi', 'co an toan khong',
            ],
            'payment' => [
                'the atm', 'the napas', '9704', 'thanh toan lai', 'pay momo', 'huong dan momo',
                'the test momo', 'loi 1002', 'paywithatm', 'thanh toan khong duoc', 'loi momo',
                'khong thanh toan duoc', 'momo bi loi', 'the ao momo', 'otp momo',
                'thanh toan that bai', 'quy trinh thanh toan', 'huong dan tra tien',
            ],
            'sell' => [
                'dang tin', 'cach dang ban', 'day tin', 'kenh nguoi ban', 'toi muon dang tin ban',
                'an tin', 'danh dau da ban', 'up bai ban', 'phi day tin bao nhieu',
                'cach ban do cu', 'toi muon ban may', 'huong dan ban hang', 'dang ban iphone',
            ],
            'commission' => [
                'phi san 5%', 'chiet khau', 'hoa hong', 'shop nhan bao nhieu', 'vi sao tru 5',
                'phi san bao nhieu', 'phi giao dich', 'bi tru bao nhieu', 'phi san la gi',
            ],
            'kyc' => [
                'kyc', 'cccd', 'dinh danh', 'chung minh nhan dan', 'upload cccd',
                'xac minh danh tinh', 'duyet kyc bao lau', 'kyc bi tu choi',
            ],
            'login' => [
                'dang nhap', 'login google', 'otp sms', 'apple sign in', 'quen mat khau',
                'khong dang nhap duoc', 'mat khau sai', 'doi mat khau',
            ],
            'account' => [
                'doi sdt', 'doi ten', 'doi email', 'thay doi ho so', 'cap nhat tai khoan',
                'sua thong tin', 'doi so dien thoai',
            ],
            'gps' => [
                'gps', 'gan toi', 'ban kinh', 'near me', 'tim may gan day', 'ship noi thanh',
            ],
            'wallet' => [
                'vi relic', 'nap tien', 'rut tien', 'so du vi', 'wallet', 'dong bang vi',
                'rut tien bao lau', 'nap vi the nao', 'tien trong vi',
            ],
            'dispute' => [
                'khieu nai', 'hoan tien', 'tra hang', 'unbox sai', 'refund',
                'hang khong dung mo ta', 'may loi', 'muon hoan tien', 'mo dispute',
            ],
            'chat' => [
                'tra gia', 'offer', 'chat voi shop', 'hoi pin', 'icloud',
                'hoi bao hanh', 'hoi hop', 'dam phan gia', 'nhan tin shop',
            ],
            'alerts' => [
                'yeu thich', 'luu tin', 'danh sach yeu thich',
            ],
            'orders' => [
                'don hang', 'don cua toi', 'van don ghn', 'nhan hang', 'huy don',
                'ship cham', 'giao hang cham', 'ma van don', 'bao lau nhan hang',
                'trang thai don', 'don dang giao', 'toi da mua gi', 'kiem tra don',
            ],
            'safety' => [
                'lua dao', 'chuyen khoan ngoai', 'gap mat', 'an toan mua ban',
                'co bi lua khong', 'tin cay khong', 'canh bao lua dao',
            ],
            'escalate' => [
                'gap admin', 'goi admin', 'bi doa', 'nghi lua', 'can admin', 'bao cao lua dao',
                'lien he ho tro', 'toi can nguoi that',
            ],
            'chitchat' => [
                't dep trai khong', 'toi dep trai khong', 'minh dep trai ko', 'dep trai khong',
                'ban dep gai khong', 't xinh khong', 'toi ngau khong', 'dep khong',
                'khoe khong', 'khoe ko', 'ban khoe khong', 'hom nay the nao',
                'an com chua', 'buon qua', 'vui qua', 'met qua', 'chill di',
                'dua thoi', 'do dua', 'haha', 'hihi', 'kkk', 'lol',
                'troi mua chua', 'may gio roi', 'thoi tiet hom nay',
                'toi thich ban', 'ban co ny chua', 'yeu chua',
                'ops oi', 'care oi', 'lam viec gi day', 'ban dang lam gi',
                'choi game khong', 'xem phim khong', 'hat di',
                'ke chuyen di', 'ban thong minh khong', 'do vui di',
            ],
            'ops' => [
                'hang uu tien', 'tin cho duyet', 'kyc cho', 'khieu nai mo',
                'tai chinh escrow', 'bao nhieu user', 'bao nhieu nguoi dung',
                'cac nguoi dung hien tai', 'don hang moi', 'thong ke san',
                'spam trung', 'seal cho', 'user bi khoa', 'co bao nhieu tk',
                'mo doanh thu', 'mo trang doanh thu', 'mo tin dang', 'mo don hang',
                'mo kyc', 'mo tai chinh', 'mo nguoi dung', 'mo dashboard',
                'xuat excel doanh thu', 'xuat bao cao doanh thu', 'xuat file excel',
                'xuat excel san pham', 'xuat excel don hang', 'bao cao doanh thu',
                'doanh thu hom nay', 'xem bieu do doanh thu', 'mo analytics',
                'du tinh doanh thu', 'du tinh doanh thu 3 ngay', 'du tinh doanh thu 10 ngay',
                'du tinh doanh thu 15 ngay', 'du tinh doanh thu 30 ngay', 'du bao doanh thu',
                'uoc tinh doanh thu theo thang', 'du tinh doanh thu theo quy',
                'danh gia doanh so', 'danh gia doanh thu', 'phan tich so lieu doanh thu',
                'doanh thu the nao', 'cham diem doanh so',
                'hello ops', 'chao ops', 'ops oi', 'alo ops',
                'hom nay co gi moi', 'co gi moi hom nay', 'viec gap hom nay',
                'mo tin cho', 'mo vi cho', 'soan tu choi kyc', 'soan ly do khoa',
                'thang nay ban ra sao', 'xem danh muc', 'bao nhieu danh muc',
                'tin #1', 'user #1', 'xem tin 12',
            ],
        ];
    }
}
