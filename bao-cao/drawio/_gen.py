# -*- coding: utf-8 -*-
import os

OUT = os.path.dirname(os.path.abspath(__file__))

SPECS = [
    {
        "file": "01-tim-va-xem-san-pham",
        "name": "Tìm và xem sản phẩm",
        "actor": "Người dùng",
        "main": "Tìm và xem sản phẩm",
        "include": None,
        "extends": ["Tìm kiếm, lọc tin", "Xem chi tiết tin"],
    },
    {
        "file": "02-dang-ky-dang-nhap",
        "name": "Đăng ký / đăng nhập",
        "actor": "Người dùng",
        "main": "Đăng ký / đăng nhập",
        "include": None,
        "extends": ["Đăng ký tài khoản", "Đăng nhập", "Quên mật khẩu"],
    },
    {
        "file": "03-gio-hang-dat-hang",
        "name": "Giỏ hàng và đặt hàng",
        "actor": "Người dùng",
        "main": "Giỏ hàng và đặt hàng",
        "include": "Đăng nhập",
        "extends": ["Thêm vào giỏ", "Xóa khỏi giỏ", "Đặt hàng"],
    },
    {
        "file": "04-thanh-toan",
        "name": "Thanh toán",
        "actor": "Người dùng",
        "main": "Thanh toán",
        "include": "Đăng nhập",
        "extends": ["Thanh toán COD", "Thanh toán MoMo"],
    },
    {
        "file": "05-chat-voi-shop",
        "name": "Chat với shop",
        "actor": "Người dùng",
        "main": "Chat với shop",
        "include": "Đăng nhập",
        "extends": ["Gửi tin nhắn", "Xem hội thoại"],
    },
    {
        "file": "06-danh-gia",
        "name": "Đánh giá",
        "actor": "Người dùng",
        "main": "Đánh giá",
        "include": "Đăng nhập",
        "extends": ["Gửi số sao", "Viết nhận xét"],
    },
    {
        "file": "07-dang-tin-ban",
        "name": "Đăng tin bán",
        "actor": "Người bán",
        "main": "Đăng tin bán",
        "include": "Đăng nhập",
        "extends": ["Nhập thông tin máy", "Tải ảnh sản phẩm"],
    },
    {
        "file": "08-an-tin-da-ban",
        "name": "Ẩn tin hoặc đánh dấu đã bán",
        "actor": "Người bán",
        "main": "Ẩn tin hoặc đánh dấu đã bán",
        "include": "Đăng nhập",
        "extends": ["Ẩn tin", "Đánh dấu đã bán"],
    },
    {
        "file": "09-quan-ly-nguoi-dung",
        "name": "Quản lý người dùng",
        "actor": "Admin",
        "main": "Quản lý người dùng",
        "include": "Đăng nhập",
        "extends": ["Khóa tài khoản", "Mở khóa tài khoản", "Cấp quyền bán"],
    },
    {
        "file": "10-quan-ly-san-pham",
        "name": "Quản lý sản phẩm",
        "actor": "Admin",
        "main": "Quản lý sản phẩm",
        "include": "Đăng nhập",
        "extends": ["Duyệt tin", "Từ chối tin", "Ẩn tin"],
    },
    {
        "file": "11-quan-ly-don-hang",
        "name": "Quản lý đơn hàng",
        "actor": "Admin",
        "main": "Quản lý đơn hàng",
        "include": "Đăng nhập",
        "extends": ["Xem chi tiết đơn", "Cập nhật trạng thái", "Hủy đơn"],
    },
    {
        "file": "12-doanh-thu-tai-chinh",
        "name": "Doanh thu và tài chính",
        "actor": "Admin",
        "main": "Doanh thu và tài chính",
        "include": "Đăng nhập",
        "extends": ["Xem thống kê", "Xuất báo cáo"],
    },
    {
        "file": "13-ho-tro-khach-hang",
        "name": "Hỗ trợ khách hàng",
        "actor": "Admin",
        "main": "Hỗ trợ khách hàng",
        "include": "Đăng nhập",
        "extends": ["Xem hội thoại", "Trả lời khách"],
    },
]


def esc(text):
    return (
        text.replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace('"', "&quot;")
    )


def cell(cid, value, style, x, y, w, h):
    return (
        f'<mxCell id="{cid}" value="{esc(value)}" style="{style}" vertex="1" parent="1">'
        f'<mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry"/>'
        f"</mxCell>"
    )


def edge(cid, value, style, source, target):
    label = f' value="{esc(value)}"' if value else ' value=""'
    return (
        f'<mxCell id="{cid}"{label} style="{style}" edge="1" parent="1" source="{source}" target="{target}">'
        f'<mxGeometry relative="1" as="geometry"/>'
        f"</mxCell>"
    )


ELLIPSE = "ellipse;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#000000;fontSize=13;fontColor=#000000;arcSize=50;"
ACTOR = "shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;html=1;outlineConnect=0;strokeColor=#000000;fillColor=#ffffff;fontSize=13;fontColor=#000000;"
BOX = "rounded=0;whiteSpace=wrap;html=1;fillColor=none;strokeColor=#000000;"
TAB = "rounded=0;whiteSpace=wrap;html=1;fillColor=#ffffff;strokeColor=#000000;fontSize=13;fontColor=#000000;align=center;verticalAlign=middle;"
INC = "endArrow=block;endFill=0;dashed=1;dashPattern=8 6;html=1;rounded=0;strokeColor=#000000;fontSize=12;fontColor=#000000;endSize=14;labelBackgroundColor=#ffffff;exitX=0.5;exitY=0;entryX=0.5;entryY=1;"
EXT = "endArrow=block;endFill=0;dashed=1;dashPattern=8 6;html=1;rounded=0;strokeColor=#000000;fontSize=12;fontColor=#000000;endSize=14;labelBackgroundColor=#ffffff;exitX=0;exitY=0.5;entryX=1;"
ASSOC = "endArrow=none;html=1;rounded=0;strokeColor=#000000;exitX=1;exitY=0.5;entryX=0;entryY=0.5;"


def build(spec):
    exts = spec["extends"]
    n = len(exts)
    has_login = bool(spec["include"])
    main_w = 200 if len(spec["main"]) > 18 else 170
    parts = []
    parts.append(cell("b", "", BOX, 190, 40, 620, 430))
    parts.append(cell("t", "UC", TAB, 190, 16, 52, 26))
    parts.append(cell("a", spec["actor"], ACTOR, 48, 200, 36, 72))
    main_x = 300 if main_w == 170 else 280
    main_y = 200
    parts.append(cell("m", spec["main"], ELLIPSE, main_x, main_y, main_w, 70))
    if has_login:
        parts.append(cell("login", spec["include"], ELLIPSE, main_x + 10, 78, 150, 58))
    if n == 1:
        ys = [207]
        entry = ["0.5"]
    elif n == 2:
        ys = [150, 270]
        entry = ["0.32", "0.72"]
    else:
        ys = [110, 207, 304]
        entry = ["0.25", "0.5", "0.78"]
    for i, label in enumerate(exts):
        ew = 168 if len(label) > 16 else 150
        parts.append(cell(f"e{i}", label, ELLIPSE, 610, ys[i], ew, 56))
    parts.append(edge("as", "", ASSOC, "a", "m"))
    if has_login:
        parts.append(edge("inc", "<<include>>", INC, "m", "login"))
    for i in range(n):
        style = EXT + f"entryY={entry[i]};"
        parts.append(edge(f"x{i}", "<<extend>>", style, f"e{i}", "m"))
    body = "".join(parts)
    return f'''<mxfile host="app.diagrams.net" type="device">
  <diagram id="{spec["file"]}" name="{esc(spec["name"])}">
    <mxGraphModel dx="1100" dy="700" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="900" pageHeight="560" math="0" shadow="0">
      <root>
        <mxCell id="0"/>
        <mxCell id="1" parent="0"/>
        {body}
      </root>
    </mxGraphModel>
  </diagram>
</mxfile>
'''


pages = []
for spec in SPECS:
    xml = build(spec)
    path = os.path.join(OUT, spec["file"] + ".drawio")
    with open(path, "w", encoding="utf-8", newline="\n") as f:
        f.write(xml)
    # inner diagram for combined file
    start = xml.find("<diagram ")
    end = xml.rfind("</diagram>") + len("</diagram>")
    pages.append(xml[start:end])
    print(spec["file"])

combined = (
    '<mxfile host="app.diagrams.net" type="device">\n'
    + "\n".join(pages)
    + "\n</mxfile>\n"
)
with open(os.path.join(OUT, "00-tat-ca-phan-ra.drawio"), "w", encoding="utf-8", newline="\n") as f:
    f.write(combined)
print("combined", len(SPECS))
