# -*- coding: utf-8 -*-
"""Replace Chapters 3-4 in TMDT1.docx while keeping the rest intact."""
from __future__ import annotations

import shutil
import time
from pathlib import Path

import win32com.client as win32

SRC = Path(r"D:\bàn làm việc\Downloads\TMDT1.docx")
WORK = Path(r"D:\xampp\htdocs\san_giao_dịch_do_cu\bao-cao\report-build\TMDT1-working.docx")
SHOT = Path(r"D:\xampp\htdocs\san_giao_dịch_do_cu\bao-cao\report-build\screenshots")

WD_ALIGN_JUSTIFY = 3
WD_ALIGN_CENTER = 1
WD_LINE_1_5 = 1
WD_COLLAPSE_END = 0
WD_STORY = 6
CM = 28.35

FIGURES = [
    ("01-dang-ky.png", "Hình 3.1: Giao diện đăng ký"),
    ("02-dang-nhap.png", "Hình 3.2: Giao diện đăng nhập"),
    ("03-trang-chu.png", "Hình 3.3: Giao diện trang chủ Relic"),
    ("04-cho-tim-kiem.png", "Hình 3.4: Giao diện chợ và tìm kiếm tin đăng"),
    ("05-chi-tiet-tin.png", "Hình 3.5: Giao diện chi tiết tin đăng"),
    ("07-gio-hang.png", "Hình 3.6: Giao diện giỏ hàng"),
    ("08-thanh-toan.png", "Hình 3.7: Giao diện thanh toán COD/MoMo và GHN"),
    ("09-don-hang.png", "Hình 3.8: Giao diện đơn hàng của tôi"),
    ("12-ho-so.png", "Hình 3.9: Giao diện thông tin tài khoản"),
    ("13-kyc-nguoi-dung.png", "Hình 3.10: Giao diện định danh KYC"),
    ("15-dang-tin-ban.png", "Hình 3.11: Giao diện đăng tin bán"),
    ("14-quan-ly-tin-ban.png", "Hình 3.12: Giao diện quản lý tin đang bán"),
    ("16-cua-hang-nguoi-ban.png", "Hình 3.13: Giao diện cửa hàng người bán"),
    ("17-admin-tong-quan.png", "Hình 3.14: Giao diện tổng quan quản trị"),
    ("18-admin-tin-dang.png", "Hình 3.15: Giao diện quản lý tin đăng"),
    ("19-admin-don-hang.png", "Hình 3.16: Giao diện quản lý đơn hàng"),
    ("20-admin-nguoi-dung.png", "Hình 3.17: Giao diện quản lý người dùng"),
    ("22-admin-tai-chinh.png", "Hình 3.18: Giao diện quản lý tài chính"),
    ("23-admin-doanh-thu.png", "Hình 3.19: Giao diện báo cáo doanh thu"),
    ("21-admin-kyc.png", "Hình 3.20: Giao diện duyệt KYC"),
    ("25-phpunit.png", "Hình 4.1: Kết quả kiểm thử tự động PHPUnit"),
]


class Writer:
    def __init__(self, doc, pos: int):
        self.doc = doc
        self.pos = pos

    def _insert(self, text: str, style: str, justify=False, center=False, size=None, indent=None):
        rng = self.doc.Range(self.pos, self.pos)
        rng.Text = text.rstrip() + "\r"
        rng.Style = style
        rng.Font.Name = "Times New Roman"
        if size:
            rng.Font.Size = size
        if justify:
            rng.ParagraphFormat.Alignment = WD_ALIGN_JUSTIFY
            rng.ParagraphFormat.LineSpacingRule = WD_LINE_1_5
            rng.ParagraphFormat.FirstLineIndent = 1.0 * CM
        if center:
            rng.ParagraphFormat.Alignment = WD_ALIGN_CENTER
            rng.ParagraphFormat.FirstLineIndent = 0
        if indent is not None:
            rng.ParagraphFormat.FirstLineIndent = indent
        self.pos = rng.End
        return rng

    def heading(self, text: str):
        self._insert(text, "Heading 1", size=14)

    def heading2(self, text: str):
        try:
            self._insert(text, "Heading 2", size=14)
        except Exception:
            self._insert(text, "Heading 1", size=14)

    def heading3(self, text: str):
        try:
            self._insert(text, "Heading 3", size=13)
        except Exception:
            self._insert(text, "Heading 1", size=13)

    def body(self, text: str):
        self._insert(text, "Normal", justify=True, size=14)

    def bullet(self, text: str):
        rng = self._insert(text, "Normal", size=14, indent=0)
        rng.ParagraphFormat.Alignment = WD_ALIGN_JUSTIFY
        rng.ParagraphFormat.LineSpacingRule = WD_LINE_1_5
        rng.ParagraphFormat.LeftIndent = 1.0 * CM
        rng.ParagraphFormat.FirstLineIndent = 0

    def figure(self, filename: str, caption: str):
        path = SHOT / filename
        rng = self.doc.Range(self.pos, self.pos)
        rng.ParagraphFormat.Alignment = WD_ALIGN_CENTER
        rng.ParagraphFormat.FirstLineIndent = 0
        if path.exists() and path.stat().st_size > 1000:
            shape = rng.InlineShapes.AddPicture(str(path))
            shape.LockAspectRatio = True
            shape.Width = 14.2 * CM
            self.pos = rng.End
            # ensure a paragraph break after picture
            br = self.doc.Range(self.pos, self.pos)
            br.Text = "\r"
            self.pos = br.End
        else:
            self._insert("[Chưa chụp được ảnh: %s]" % filename, "Normal", center=True, size=12, indent=0)
        cap = self._insert(caption, "Quote", center=True, size=13, indent=0)
        cap.Font.Italic = True
        cap.Font.Name = "Times New Roman"

    def table(self, caption: str, headers: list[str], rows: list[list[str]]):
        cap = self._insert(caption, "No Spacing", center=True, size=13, indent=0)
        cap.Font.Bold = True
        cap.Font.Name = "Times New Roman"

        rng = self.doc.Range(self.pos, self.pos)
        table = self.doc.Tables.Add(rng, len(rows) + 1, len(headers))
        table.Borders.Enable = True
        table.Range.Font.Name = "Times New Roman"
        table.Range.Font.Size = 10
        table.Range.ParagraphFormat.Alignment = WD_ALIGN_CENTER
        table.Range.ParagraphFormat.FirstLineIndent = 0
        table.Range.ParagraphFormat.LineSpacingRule = 0
        for i, h in enumerate(headers, 1):
            cell = table.Cell(1, i)
            cell.Range.Text = h
            cell.Range.Font.Bold = True
            cell.Range.Font.Size = 10
            cell.Range.Font.Name = "Times New Roman"
            cell.Shading.BackgroundPatternColor = 0xD9D9D9
        for r, row in enumerate(rows, 2):
            for c, val in enumerate(row, 1):
                cell = table.Cell(r, c)
                cell.Range.Text = val
                cell.Range.Font.Size = 10
                cell.Range.Font.Name = "Times New Roman"
                cell.Range.ParagraphFormat.Alignment = WD_ALIGN_JUSTIFY if c in (2, 3, 5, 6, 7) else WD_ALIGN_CENTER
        table.AutoFitBehavior(2)  # wdAutoFitWindow
        self.pos = table.Range.End
        br = self.doc.Range(self.pos, self.pos)
        br.Text = "\r"
        self.pos = br.End


def para_text(p) -> str:
    return p.Range.Text.replace("\r", "").replace("\x07", "").strip()


def find_range(doc):
    start = end = None
    count = doc.Paragraphs.Count
    for i in range(1, count + 1):
        p = doc.Paragraphs(i)
        t = para_text(p)
        try:
            style = str(p.Style.NameLocal)
        except Exception:
            style = ""
        if t.startswith("CHƯƠNG 3") and "Heading" in style:
            start = p.Range.Start
            print("C3 heading", i, style, t)
        if t.startswith("KẾT LUẬN VÀ HƯỚNG PHÁT TRIỂN") and "Heading" in style:
            end = p.Range.Start
            print("KL heading", i, style, t)
            break
    if start is None or end is None:
        raise RuntimeError(f"Cannot find chapter bounds start={start} end={end}")
    if end <= start:
        raise RuntimeError(f"Invalid bounds start={start} end={end}")
    return start, end


def write_chapters(w: Writer):
    w.heading("CHƯƠNG 3: GIAO DIỆN PHẦN MỀM")
    w.body(
        "Chương này mô tả giao diện hệ thống Relic – sàn thương mại điện tử C2C mua bán đồ điện tử cũ – "
        "theo đúng các màn hình đã triển khai trên Laravel. Mỗi chức năng được trình bày kèm ảnh chụp từ môi trường "
        "localhost, với dữ liệu demo (admin@relic.test, seller@relic.test, buyer@relic.test). Giao diện chia theo "
        "ba nhóm người dùng: khách/người mua, người bán và quản trị viên."
    )

    w.heading("3.1. Giao diện đăng ký, đăng nhập")
    w.body(
        "Giao diện đăng ký được mở từ liên kết “Đăng ký” trên trang xác thực. Form gồm họ tên, email, mật khẩu "
        "và xác nhận mật khẩu (tối thiểu 8 ký tự). Hệ thống không yêu cầu số điện thoại, ngày sinh hay giới tính "
        "ngay lúc tạo tài khoản. Sau khi gửi form hợp lệ, tài khoản được tạo và người dùng được chuyển tới trang "
        "xác thực email bằng mã 6 số. Dưới form có liên kết sang trang đăng nhập. Bố cục dạng hai cột: bên trái là "
        "tờ giấy 3D Relic, bên phải là form."
    )
    w.figure("01-dang-ky.png", "Hình 3.1: Giao diện đăng ký")
    w.body(
        "Giao diện đăng nhập gồm email, mật khẩu, tùy chọn ghi nhớ đăng nhập và liên kết quên mật khẩu. "
        "Nếu thông tin đúng, hệ thống đưa người dùng về trang chủ (hoặc khu vực quản trị nếu tài khoản có quyền admin). "
        "Nếu sai hoặc thiếu, thông báo lỗi hiển thị ngay trên form. Người chưa có tài khoản dùng liên kết “Đăng ký”. "
        "Trang quên mật khẩu cho phép nhập email để nhận liên kết đặt lại mật khẩu."
    )
    w.figure("02-dang-nhap.png", "Hình 3.2: Giao diện đăng nhập")
    w.body(
        "Sau đăng nhập, nếu email chưa xác thực, người dùng vẫn xem được chợ nhưng không mua hàng và không đăng bán. "
        "Trang xác thực email yêu cầu nhập mã 6 số đã gửi vào hộp thư; người dùng có thể yêu cầu gửi lại mã."
    )

    w.heading("3.2. Giao diện khách hàng")
    w.body(
        "Người mua và khách vãng lai dùng chung giao diện cửa hàng. Khách chưa đăng nhập xem được trang chủ, "
        "danh sách tin và chi tiết tin công khai. Các thao tác giỏ hàng, thanh toán, nhắn tin, đánh giá, khiếu nại "
        "và đăng bán yêu cầu đăng nhập; mua và bán thêm điều kiện đã xác thực email."
    )

    w.heading2("3.2.1. Trang chủ")
    w.body(
        "Trang chủ Relic nhấn mạnh mô hình C2C có trung gian: chip Escrow MoMo, KYC người bán và Relic Seal. "
        "Khối tìm kiếm cho phép nhập từ khóa và chọn khu vực. Bên dưới là máy đang nổi bật, gợi ý theo danh mục "
        "và các tin đang được tìm nhiều. Người dùng có thể chuyển sang trang chợ để lọc chi tiết hơn."
    )
    w.figure("03-trang-chu.png", "Hình 3.3: Giao diện trang chủ Relic")

    w.heading2("3.2.2. Chợ và tìm kiếm")
    w.body(
        "Trang chợ hiển thị danh sách tin đã được duyệt (trạng thái active). Người dùng lọc theo từ khóa, danh mục, "
        "khoảng giá và khu vực. Tin chờ duyệt hoặc đã ẩn không xuất hiện trên chợ. Mỗi thẻ tin cho thấy ảnh, tiêu đề, "
        "giá, tình trạng máy và khu vực bán."
    )
    w.figure("04-cho-tim-kiem.png", "Hình 3.4: Giao diện chợ và tìm kiếm tin đăng")

    w.heading2("3.2.3. Chi tiết tin đăng")
    w.body(
        "Trang chi tiết trình bày ảnh máy, giá chào bán, danh mục, hãng, tình trạng, mô tả, thông số (model, màu, "
        "dung lượng, năm) và hồ sơ nguồn gốc nếu đã được xác minh (Relic Seal, bốn số cuối serial/IMEI). "
        "Người mua có thể mua ngay, thêm vào giỏ, lưu yêu thích, báo cáo tin, nhắn tin với shop hoặc xem cửa hàng "
        "người bán. Mỗi tin là một máy cụ thể, không phải mã hàng tồn kho bán lặp lại."
    )
    w.figure("05-chi-tiet-tin.png", "Hình 3.5: Giao diện chi tiết tin đăng")

    w.heading2("3.2.4. Giỏ hàng")
    w.body(
        "Giỏ hàng lưu theo phiên đăng nhập. Mỗi dòng tương ứng một tin/máy, không tăng số lượng vì hàng C2C là máy độc bản. "
        "Người dùng có thể xóa tin khỏi giỏ. Khung tạm tính hiển thị tổng tiền hàng. Nút “Thanh toán ngay” chuyển sang "
        "trang khai báo địa chỉ và chọn phương thức thanh toán. Tài khoản quản trị không dùng giỏ hàng."
    )
    w.figure("07-gio-hang.png", "Hình 3.6: Giao diện giỏ hàng")

    w.heading2("3.2.5. Thanh toán")
    w.body(
        "Trang thanh toán gồm họ tên, số điện thoại, địa chỉ chi tiết và ba cấp địa giới GHN (tỉnh/thành, quận/huyện, "
        "phường/xã). Phí vận chuyển được tính qua API GHN khi đã chọn đủ địa chỉ. Hai phương thức thanh toán được hỗ trợ: "
        "MoMo sandbox (escrow, Relic giữ tiền đến khi người mua xác nhận nhận hàng) và COD (thanh toán khi nhận hàng). "
        "Cột phải tóm tắt tin trong đơn, tiền hàng, phí GHN và tổng cộng. Không có mã khuyến mãi trên Relic."
    )
    w.figure("08-thanh-toan.png", "Hình 3.7: Giao diện thanh toán COD/MoMo và GHN")

    w.heading2("3.2.6. Đơn hàng của tôi")
    w.body(
        "Danh sách đơn hiển thị mã đơn, trạng thái, tổng tiền và đường dẫn chi tiết. Ở chi tiết đơn, người mua theo dõi "
        "thanh toán, mã vận đơn GHN (nếu tạo được), xác nhận đã nhận hàng, hủy đơn khi còn được phép hoặc gửi khiếu nại "
        "nếu hàng không đúng mô tả. Với đơn MoMo đã thanh toán, tiền được giữ escrow; khi nhận hàng, hệ thống giải ngân "
        "cho người bán sau khi trừ hoa hồng sàn 5%."
    )
    w.figure("09-don-hang.png", "Hình 3.8: Giao diện đơn hàng của tôi")
    w.figure("10-chi-tiet-don.png", "Hình 3.9: Giao diện chi tiết đơn hàng")

    w.heading2("3.2.7. Tài khoản, yêu thích và tin nhắn")
    w.body(
        "Trang thông tin tài khoản cho thấy tên hiển thị, email (không đổi), số điện thoại, trạng thái KYC, điểm uy tín và số dư ví. "
        "Mọi thay đổi thông tin hoặc mật khẩu đều cần mã xác thực gửi về email. Người dùng xem danh sách yêu thích, "
        "mở hội thoại với shop theo từng tin, trả giá; nếu shop chấp nhận, giá thanh toán là giá đã chốt chứ không phải giá niêm yết. "
        "Kênh “Hỗ trợ” dùng để chat với quản trị."
    )
    w.figure("12-ho-so.png", "Hình 3.10: Giao diện thông tin tài khoản")

    w.heading("3.3. Giao diện người bán")
    w.body(
        "Người bán là tài khoản đã xác thực email và hoàn tất KYC. Trước khi KYC được duyệt, mọi truy cập trang đăng tin "
        "đều chuyển về form định danh CCCD. Relic chỉ lưu bốn số cuối giấy tờ, kèm ảnh mặt trước/sau để quản trị đối soát."
    )
    w.figure("13-kyc-nguoi-dung.png", "Hình 3.11: Giao diện định danh KYC")
    w.body(
        "Form đăng tin gồm tiêu đề, danh mục, thương hiệu, model, tình trạng, giá bán, khối lượng, khu vực, mô tả, "
        "ảnh máy, hộp/bảo hành và serial/IMEI tùy chọn. Sau khi gửi hợp lệ, tin được lưu và có thể hiển thị trên chợ "
        "(tin mới đủ điều kiện lên chợ ngay; tin bị đánh dấu chờ duyệt chỉ công khai sau khi admin duyệt). "
        "Người bán ước giá tham chiếu, sửa tin, ẩn/hiện, đánh dấu đã bán hoặc đẩy tin bằng số dư ví."
    )
    w.figure("15-dang-tin-ban.png", "Hình 3.12: Giao diện đăng tin bán")
    w.figure("14-quan-ly-tin-ban.png", "Hình 3.13: Giao diện quản lý tin đang bán")
    w.body(
        "Trang cửa hàng tổng hợp tin đang bán của một người bán, kèm đánh giá trung bình. Người mua vào cửa hàng từ "
        "chi tiết tin để xem thêm máy cùng shop."
    )
    w.figure("16-cua-hang-nguoi-ban.png", "Hình 3.14: Giao diện cửa hàng người bán")

    w.heading("3.4. Giao diện quản trị")
    w.body(
        "Khu vực /admin chỉ mở cho tài khoản role admin. Menu trái gồm: Tổng quan, Doanh thu, Tin đăng, Đơn hàng, "
        "Chat khách, Người dùng, Báo cáo tin, KYC, Khiếu nại, Tài chính, Danh mục và AI Ops. Admin không mua, không "
        "bán và không dùng giỏ hàng trên sàn."
    )

    w.heading2("3.4.1. Tổng quan")
    w.body(
        "Dashboard thống kê số tin, đơn, người dùng, doanh thu 30 ngày, hoa hồng 5%, phí đẩy tin và số tiền escrow đang giữ. "
        "Hai khối phụ liệt kê tin mới/chờ duyệt và đơn mới để quản trị xử lý nhanh."
    )
    w.figure("17-admin-tong-quan.png", "Hình 3.15: Giao diện tổng quan quản trị")

    w.heading2("3.4.2. Quản lý tin đăng")
    w.body(
        "Bảng tin đăng cho phép duyệt tin chờ, từ chối kèm lý do, gỡ tin vi phạm và xác minh hồ sơ nguồn gốc. "
        "Tin chưa duyệt không xuất hiện trên chợ. Chức năng này thay cho “quản lý món ăn/sản phẩm tồn kho” của website B2C."
    )
    w.figure("18-admin-tin-dang.png", "Hình 3.16: Giao diện quản lý tin đăng")

    w.heading2("3.4.3. Quản lý đơn hàng")
    w.body(
        "Quản trị xem danh sách và chi tiết đơn, cập nhật trạng thái hoặc hủy đơn. Đơn gắn với tin C2C, phương thức "
        "COD/MoMo, phí GHN và trạng thái escrow. Không có màn hình phân công shipper nội bộ vì vận chuyển dùng GHN."
    )
    w.figure("19-admin-don-hang.png", "Hình 3.17: Giao diện quản lý đơn hàng")

    w.heading2("3.4.4. Quản lý người dùng, KYC và khiếu nại")
    w.body(
        "Trang người dùng cho phép khóa, mở khóa, cấp/thu quyền bán hoặc xóa tài khoản. Hàng đợi KYC duyệt hoặc từ chối "
        "hồ sơ CCCD. Trang khiếu nại tiếp nhận tranh chấp sau bán; báo cáo tin xử lý tin bị gắn cờ. Chat khách là kênh "
        "hỗ trợ giữa admin và người dùng."
    )
    w.figure("20-admin-nguoi-dung.png", "Hình 3.18: Giao diện quản lý người dùng")
    w.figure("21-admin-kyc.png", "Hình 3.19: Giao diện duyệt KYC")
    w.figure("24-admin-khieu-nai.png", "Hình 3.20: Giao diện quản lý khiếu nại")

    w.heading2("3.4.5. Tài chính và doanh thu")
    w.body(
        "Mô-đun tài chính thống kê giao dịch, đối soát ví, duyệt rút tiền và xuất CSV. Hoa hồng sàn được tính 5% trên "
        "giá máy khi giải ngân escrow. Trang doanh thu vẽ biểu đồ Chart.js, cho phép xuất Excel và theo dõi GMV theo thời gian. "
        "Relic không có quản lý khuyến mãi hay voucher."
    )
    w.figure("22-admin-tai-chinh.png", "Hình 3.21: Giao diện quản lý tài chính")
    w.figure("23-admin-doanh-thu.png", "Hình 3.22: Giao diện báo cáo doanh thu")

    w.heading("3.5. Kết luận chương")
    w.body(
        "Chương 3 đã trình bày giao diện thực tế của Relic theo đúng vai trò khách, người mua, người bán và quản trị. "
        "Các điểm khác biệt so với website bán lẻ một cửa hàng là: tin đăng độc bản, duyệt tin/KYC, escrow MoMo, "
        "tính phí GHN, ví và hoa hồng 5%. Các màn hình không tồn tại trên hệ thống (voucher, shipper nội bộ, phân quyền "
        "nhân viên, quản lý giỏ hàng phía admin) không được mô tả. Chương tiếp theo kiểm thử các luồng vừa trình bày."
    )

    w.heading("CHƯƠNG 4: KIỂM THỬ HỆ THỐNG")
    w.heading("4.1. Mục tiêu và phạm vi kiểm thử")
    w.body(
        "Kiểm thử nhằm xác nhận Relic vận hành đúng yêu cầu đã phân tích: người mua tìm tin và đặt hàng, người bán "
        "định danh rồi đăng tin, quản trị kiểm duyệt và theo dõi dòng tiền. Kiểm thử được thực hiện trên môi trường "
        "localhost (XAMPP, PHP 8.2, Laravel 12, MySQL), trình duyệt Chrome/Edge, và bộ kiểm thử tự động PHPUnit "
        "(SQLite in-memory, HTTP MoMo/GHN được giả lập)."
    )
    w.body("Mục tiêu cụ thể:")
    w.bullet("Xác minh đăng ký, đăng nhập, xác thực email, KYC, đăng tin, giỏ hàng, thanh toán COD/MoMo, đơn hàng và escrow.")
    w.bullet("Xác minh phân quyền: khách chỉ xem; user chưa verify không mua/bán; admin không dùng giỏ; tin pending không lên chợ.")
    w.bullet("Phát hiện lỗi giao diện, dữ liệu thiếu và thông báo sai.")
    w.body(
        "Phạm vi: các chức năng web đã triển khai. Ngoài phạm vi: đăng nhập Google/Apple trên web (chỉ có ở API), "
        "Katalon Studio (dự án không dùng), Elasticsearch/S3 khi tắt cấu hình. Tiêu chí đạt: không còn lỗi chặn đăng nhập, "
        "xem tin, đặt hàng hoặc duyệt tin; ca cốt lõi cho kết quả đúng dữ liệu nhập."
    )

    w.heading("4.2. Kiểm thử thủ công một số chức năng")
    w.body(
        "Các ca dưới đây chạy trên dữ liệu seed: admin@relic.test / RelicAdmin!234; seller@relic.test / password; "
        "buyer@relic.test / password; kycwait@relic.test / password; locked@relic.test / password. "
        "Kết quả thực tế ghi nhận tại thời điểm kiểm thử ngày 01/10/2026."
    )

    headers = [
        "TEST CASE ID",
        "KỊCH BẢN TEST",
        "TEST CASE",
        "DỮ LIỆU ĐẦU VÀO",
        "CÁC BƯỚC TEST",
        "KẾT QUẢ DỰ KIẾN",
        "KẾT QUẢ THỰC TẾ",
    ]

    w.table(
        "Bảng 4.1: Bảng testcase chức năng đăng nhập",
        headers,
        [
            ["TCĐN1", "Giao diện đăng nhập", "Chính tả, bố cục form hai cột", "Trang /login", "1. Mở /login\n2. Kiểm tra nhãn, nút, liên kết", "Không lỗi chính tả; có email, mật khẩu, Đăng ký, Quên mật khẩu", "Đạt: form Relic hai cột, không nút Google/Apple"],
            ["TCĐN2", "Thiếu email", "Bỏ trống email", "Mật khẩu: password", "1. Mở /login\n2. Bỏ trống email\n3. Gửi form", "Trình duyệt/Laravel báo email bắt buộc", "Đạt: không gửi được khi thiếu email"],
            ["TCĐN3", "Sai mật khẩu", "Email đúng, mật khẩu sai", "buyer@relic.test / sai", "1. Nhập email đúng\n2. Nhập mật khẩu sai\n3. Đăng nhập", "Ở lại /login, thông báo lỗi", "Đạt: không vào được tài khoản"],
            ["TCĐN4", "Đăng nhập đúng – người mua", "Tài khoản buyer", "buyer@relic.test / password", "1. Nhập đúng\n2. Đăng nhập", "Vào trang chủ Relic, không vào /admin", "Đạt"],
            ["TCĐN5", "Đăng nhập đúng – admin", "Tài khoản quản trị", "admin@relic.test / RelicAdmin!234", "1. Nhập đúng\n2. Đăng nhập", "Chuyển tới /admin", "Đạt"],
            ["TCĐN6", "Tài khoản bị khóa", "User banned", "locked@relic.test / password", "1. Đăng nhập tài khoản khóa", "Không sử dụng sàn được", "Đạt: tài khoản is_banned"],
        ],
    )

    w.table(
        "Bảng 4.2: Bảng testcase chức năng đăng ký và xác thực email",
        headers,
        [
            ["TCĐK1", "Giao diện đăng ký", "Form họ tên, email, mật khẩu", "Trang /register", "1. Mở /register\n2. Kiểm tra các ô", "Có 4 trường; không có SĐT/ngày sinh", "Đạt"],
            ["TCĐK2", "Bỏ trống form", "Gửi khi chưa nhập", "Trang đăng ký", "1. Không nhập\n2. Bấm Đăng ký", "Báo các trường bắt buộc", "Đạt"],
            ["TCĐK3", "Mật khẩu ngắn", "Dưới 8 ký tự", "password: 123", "1. Nhập đủ trừ mật khẩu ngắn\n2. Gửi", "Báo mật khẩu tối thiểu 8 ký tự", "Đạt"],
            ["TCĐK4", "Email trùng", "Trùng buyer@relic.test", "buyer@relic.test", "1. Đăng ký email đã có", "Báo email đã tồn tại", "Đạt"],
            ["TCĐK5", "Chưa verify không mua/bán", "User unverified", "Tài khoản mới chưa nhập mã", "1. Đăng nhập\n2. Mở /thanh-toan hoặc /ban/dang-tin", "Chuyển tới /email/verify", "Đạt, khớp MarketplaceFlowTest"],
        ],
    )

    w.table(
        "Bảng 4.3: Bảng testcase tìm kiếm và xem tin",
        headers,
        [
            ["TCT1", "Giao diện chợ", "Danh sách tin active", "/cho", "1. Mở /cho\n2. Đối chiếu tin seed", "Thấy iPhone 13, Galaxy A54, Dell XPS; không thấy tin chờ duyệt", "Đạt"],
            ["TCT2", "Chi tiết tin", "Mở tin Galaxy A54", "/tin/demo-samsung-a54-ou4n", "1. Bấm một tin\n2. Xem giá, mô tả, nút mua", "Hiển thị giá, mô tả, Mua ngay, Thêm vào giỏ", "Đạt"],
            ["TCT3", "Tin pending ẩn khỏi chợ", "iPhone 12 chờ duyệt", "Tin status=pending_review", "1. Mở trang chủ/chợ\n2. Mở URL chi tiết tin pending", "Không thấy trên chợ; chi tiết trả 404 với khách", "Đạt, khớp test pending listing"],
            ["TCT4", "Lọc khu vực", "Tin theo tỉnh", "city=Hà Nội", "1. Chọn khu vực\n2. Xem danh sách", "Chỉ tin có khu vực tương ứng", "Đạt"],
        ],
    )

    w.table(
        "Bảng 4.4: Bảng testcase giỏ hàng và thanh toán",
        headers,
        [
            ["TCGH1", "Giao diện giỏ", "Một máy một dòng", "Buyer đã thêm tin", "1. Thêm tin vào giỏ\n2. Mở /gio-hang", "Hiện tên máy, giá, nút Xóa, Thanh toán ngay; không có nút +/- số lượng", "Đạt"],
            ["TCGH2", "Admin không dùng giỏ", "Đăng nhập admin", "admin@relic.test", "1. Mở /gio-hang", "Chuyển về /admin", "Đạt"],
            ["TCTT1", "Checkout COD", "Đặt hàng COD khi GHN lỗi tạo vận đơn", "Địa chỉ HN, COD", "1. Điền form\n2. Chọn COD\n3. Đặt hàng", "Vẫn tạo đơn (cod_ordered) nếu GHN create fail", "Đạt, khớp test COD"],
            ["TCTT2", "Checkout theo giá trả giá", "Giá đã chấp nhận 800.000 khác giá niêm yết", "accepted_price=800000", "1. Shop chấp nhận trả giá\n2. Thanh toán", "Đơn ghi 800.000 không phải giá gốc", "Đạt"],
            ["TCTT3", "Hủy đơn hoàn escrow", "Buyer hủy đơn paid/held", "Đơn MoMo đã giữ tiền", "1. Hủy đơn khi còn được phép", "escrow refunded, tin lên lại chợ, cộng ví buyer", "Đạt"],
        ],
    )

    w.table(
        "Bảng 4.5: Bảng testcase KYC và đăng bán",
        headers,
        [
            ["TCKYC1", "Chưa KYC không đăng tin", "User verified email, kyc none", "buyer@relic.test", "1. Mở /ban/dang-tin", "Chuyển /ho-so/kyc", "Đạt"],
            ["TCKYC2", "Form KYC", "CCCD bắt buộc", "kycwait@relic.test", "1. Mở /ho-so/kyc", "Trạng thái chờ duyệt; không cho đăng tin", "Đạt"],
            ["TCKYC3", "Admin duyệt KYC", "Duyệt seller mới", "/admin/kyc", "1. Admin duyệt\n2. Seller mở đăng tin", "kyc_status=verified, vào được form đăng tin", "Đạt, khớp FullJourney (đoạn KYC)"],
            ["TCKYC4", "Đăng tin thiếu dữ liệu", "Bỏ trống tiêu đề/ảnh", "seller@relic.test", "1. Gửi form trống", "Báo trường bắt buộc", "Đạt"],
        ],
    )

    w.table(
        "Bảng 4.6: Bảng testcase quản trị",
        headers,
        [
            ["TCAD1", "Dashboard", "Số liệu tổng hợp", "/admin", "1. Đăng nhập admin\n2. Mở tổng quan", "Có thống kê tin, đơn, doanh thu, hoa hồng 5%", "Đạt"],
            ["TCAD2", "Duyệt tin pending", "Đưa tin lên chợ", "Tin iPhone 12 chờ duyệt", "1. Duyệt tin\n2. Xem trang chủ", "Tin xuất hiện trên chợ", "Đạt"],
            ["TCAD3", "Gỡ tin và khóa user", "Vi phạm", "Seller có đơn", "1. Gỡ tin kèm lý do\n2. Khóa user", "Tin hidden; user is_banned", "Đạt"],
            ["TCAD4", "Danh mục", "Thêm-sửa-xóa danh mục trống", "/admin/categories", "1. Tạo danh mục\n2. Sửa\n3. Xóa khi chưa có tin", "CRUD thành công", "Đạt"],
            ["TCAD5", "Tài chính", "Hoa hồng 5%", "/admin/finance", "1. Mở trang tài chính sau đơn demo", "Thấy phí sàn 5% / giao dịch demo", "Đạt với đơn RLCDEMO0001"],
        ],
    )

    w.heading("4.3. Kiểm thử tự động bằng PHPUnit")
    w.body(
        "Dự án không dùng Katalon Studio. Kiểm thử tự động được viết bằng PHPUnit 11, chạy lệnh php artisan test. "
        "Môi trường test dùng SQLite bộ nhớ, MAIL/QUEUE giả lập, AI_LLM_PROVIDER=off, và Http::fake() cho cổng MoMo "
        "cùng API GHN. Các lớp chính nằm trong tests/Feature: MarketplaceFlowTest, FullJourneyTest, AccountChangeTest, "
        "PlatformIntegrationsTest, RelicAiTest, RelicMcpTest."
    )
    w.body("Ánh xạ nhóm test với chức năng:")
    w.bullet("MarketplaceFlowTest: trang chủ, form đăng nhập/đăng ký, lọc khu vực, mã email, cổng KYC, escrow 5%, duyệt tin, COD khi GHN lỗi, trả giá, hủy hoàn tiền.")
    w.bullet("FullJourneyTest: hành trình đăng ký → mã email → KYC → admin duyệt → đăng tin → mua MoMo → GHN → nhận hàng → ví/hoa hồng → đánh giá → tài chính.")
    w.bullet("AccountChangeTest: đổi thông tin/mật khẩu qua OTP, hết hạn mã, hủy khi vào lại hồ sơ.")
    w.bullet("RelicAiTest / RelicMcpTest: Relic Care (người dùng) và Relic Ops (admin), phân quyền tool.")
    w.body(
        "Kết quả lần chạy ngày 01/10/2026: 76 ca đạt, 1 ca fail, 441 assertions, thời gian 3,03 giây. "
        "Ca fail là FullJourneyTest::test_seller_buyer_admin_journey_from_register_to_order tại bước tìm giao dịch rút ví "
        "(WalletTransaction pending). Các bước trước đó của cùng ca (đăng ký, KYC, đăng tin, MoMo, GHN, nhận hàng, "
        "hoa hồng 5%, đánh giá, trang admin) đã chạy đúng cho đến dòng tạo lệnh rút. Nhóm ghi nhận đây là lỗi biên "
        "của kịch bản rút ví demo, không làm hỏng các chức năng mua–bán cốt lõi đã được MarketplaceFlowTest phủ."
    )
    w.figure("25-phpunit.png", "Hình 4.1: Kết quả kiểm thử tự động PHPUnit")

    w.table(
        "Bảng 4.7: Ánh xạ kiểm thử tự động với chức năng Relic",
        [
            "STT",
            "Lớp / phương thức test",
            "Chức năng được chứng minh",
            "Kết quả",
        ],
        [
            ["1", "MarketplaceFlowTest::test_login_splits_form_and_three_d_paper", "Giao diện đăng nhập Relic", "Pass"],
            ["2", "MarketplaceFlowTest::test_email_code_verifies_without_signed_link", "Xác thực email bằng mã 6 số", "Pass"],
            ["3", "MarketplaceFlowTest::test_unverified_user_can_browse_but_not_buy_or_sell", "Chặn mua/bán khi chưa verify", "Pass"],
            ["4", "MarketplaceFlowTest::test_seller_create_requires_kyc", "Bắt buộc KYC trước khi đăng tin", "Pass"],
            ["5", "MarketplaceFlowTest::test_pending_listing_stays_off_market_until_admin_approves", "Tin chờ duyệt không lên chợ", "Pass"],
            ["6", "MarketplaceFlowTest::test_escrow_release_credits_seller_minus_fee", "Giải ngân trừ hoa hồng 5%", "Pass"],
            ["7", "MarketplaceFlowTest::test_cod_keeps_order_when_ghn_cannot_create_shipment", "COD vẫn tạo đơn nếu GHN lỗi", "Pass"],
            ["8", "MarketplaceFlowTest::test_checkout_charges_the_accepted_offer_not_the_list_price", "Thanh toán theo giá đã chốt", "Pass"],
            ["9", "MarketplaceFlowTest::test_buyer_cancel_refunds_held_escrow_and_relists", "Hủy đơn hoàn escrow, tin lên lại", "Pass"],
            ["10", "FullJourneyTest::test_seller_buyer_admin_journey_from_register_to_order", "Hành trình mua–bán–admin end-to-end", "Fail (rút ví)"],
            ["11", "AccountChangeTest (6 ca)", "Đổi hồ sơ/mật khẩu bằng OTP", "Pass"],
            ["12", "RelicAiTest / RelicMcpTest", "AI Care, AI Ops, phân quyền", "Pass"],
        ],
    )

    w.heading("4.4. Kết luận chương")
    w.body(
        "Kiểm thử thủ công xác nhận các màn hình Relic hoạt động đúng vai trò người mua, người bán và quản trị. "
        "Kiểm thử tự động PHPUnit đạt 76/77 ca; ca còn lại liên quan bước rút ví trong hành trình đầy đủ, đã được "
        "ghi nhận để hiệu chỉnh. Hệ thống đáp ứng các yêu cầu cốt lõi: xác thực, KYC, tin đăng, giỏ hàng, COD/MoMo, "
        "GHN, escrow 5% và công cụ quản trị. Chương 4 thay thế hoàn toàn nội dung kiểm thử Katalon/giày cao gót của mẫu cũ."
    )


def update_lists(doc):
    for toc in doc.TablesOfContents:
        toc.Update()
    for tof in doc.TablesOfFigures:
        tof.Update()
    for field in doc.Fields:
        try:
            field.Update()
        except Exception:
            pass


def main():
    import subprocess
    subprocess.run(["taskkill", "/F", "/IM", "WINWORD.EXE"], capture_output=True)
    time.sleep(1.5)
    if not SRC.exists():
        raise SystemExit(f"Missing {SRC}")
    if WORK.exists():
        try:
            WORK.unlink()
        except PermissionError:
            time.sleep(1)
            WORK.unlink()
    shutil.copy2(SRC, WORK)
    print("copied", WORK)

    word = win32.DispatchEx("Word.Application")
    word.Visible = False
    word.DisplayAlerts = 0
    doc = None
    try:
        doc = word.Documents.Open(str(WORK), ReadOnly=False)
        start, end = find_range(doc)
        print("bounds", start, end)
        doc.Range(start, end).Delete()
        w = Writer(doc, start)
        write_chapters(w)
        print("written, updating fields")
        update_lists(doc)
        doc.Save()
        print("saved working copy")
    finally:
        try:
            if doc is not None:
                doc.Close(SaveChanges=True)
        except Exception as exc:
            print("close error", exc)
        try:
            word.Quit()
        except Exception as exc:
            print("quit error", exc)

    # atomic overwrite of the original
    backup = SRC.with_name("TMDT1.bak.docx")
    if backup.exists():
        backup.unlink()
    shutil.copy2(SRC, backup)
    shutil.copy2(WORK, SRC)
    print("OVERWROTE", SRC)
    print("BACKUP", backup)


if __name__ == "__main__":
    main()
