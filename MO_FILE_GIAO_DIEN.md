# Mở nhanh file theo chức năng trên giao diện

Ba cách, dùng cái nào cũng ra cùng một bộ file.

**Đang nhìn trang web.** Tìm chữ trên nút hoặc menu trong bảng dưới. Mở cột Giao diện trước (thấy HTML), rồi cột Controller (thấy xử lý bấm nút).

**Đang đứng trong file Blade.** Đường dẫn chính là tên view: `resources/views/orders/show.blade.php` = `orders.show`. Dòng đầu `@extends('layouts....')` là khung bọc trang đó (header, menu). Partial `@include('listings._card')` là mảnh nhúng, không phải cả trang.

**Đang đứng trong Controller.** Tìm `return view('ten.view')`. File giao diện là `resources/views/ten/view.blade.php`. Route gắn controller nằm trong `routes/web.php` (API app trong `routes/api.php`).

Khung dùng chung, đừng lẫn với chức năng bên trong:

| Khung | File |
|---|---|
| Header, menu, footer, giỏ, AI Care trên sàn | `resources/views/layouts/store.blade.php` |
| Menu tài khoản (Thông tin, KYC, Đơn mua, …) | `resources/views/layouts/account.blade.php` |
| Menu admin | `resources/views/layouts/admin.blade.php` |
| Khung đăng nhập / đăng ký | `resources/views/layouts/auth.blade.php` |
| CSS gần như mọi trang | `public/css/relic.css`, `public/css/relic-magic.css` |

---

## Sàn — khách xem

| Trên giao diện | Giao diện | Controller | Logic kèm |
|---|---|---|---|
| Home | `resources/views/home.blade.php` | `app/Http/Controllers/HomeController.php` | `app/Services/AreaService.php`, `app/Services/GeoService.php` |
| Dải danh mục trên Home | `resources/views/partials/category-filmstrip.blade.php` | cùng Home | `public/css/category-filmstrip.css`, `public/js/category-filmstrip.js` |
| Ô tìm + nút kính | `resources/views/partials/glass-search-button.blade.php` | form gửi tới Chợ | `public/css/glass-ai-button.css`, `public/js/glass-ai-button.js` |
| Chọn khu vực | `resources/views/partials/area-picker.blade.php` | `app/Http/Controllers/LocationController.php` | `app/Services/AreaService.php` |
| Chợ `/cho` | `resources/views/listings/index.blade.php` | `app/Http/Controllers/ListingController.php` method `index` | `app/Services/ListingSearch.php` |
| Thẻ một tin | `resources/views/listings/_card.blade.php` | không có controller riêng | nhúng bởi Home, Chợ, shop, yêu thích |
| Banner promo | `resources/views/listings/_promo.blade.php` | không có controller riêng | |
| Trang tin `/tin/{slug}` | `resources/views/listings/show.blade.php` | `ListingController.php` method `show` | nút giỏ, yêu thích, báo cáo, đánh giá nằm trong file này |
| Chat ngay trên tin | `resources/views/listings/_shop_chat.blade.php` | `app/Http/Controllers/ConversationController.php` method `start` | |
| Gian hàng `/cua-hang/{user}` | `resources/views/shops/show.blade.php` | `app/Http/Controllers/ShopController.php` | |
| Trang tĩnh (Cách hoạt động, Điều khoản, …) | `resources/views/pages/*.blade.php` | `app/Http/Controllers/PageController.php` | slug `how-it-works` → file `pages/how_it_works.blade.php` |

## Mua hàng

| Trên giao diện | Giao diện | Controller | Logic kèm |
|---|---|---|---|
| Giỏ hàng | `resources/views/cart/index.blade.php` | `app/Http/Controllers/CartController.php` | giỏ nằm session, không có bảng cart |
| Thanh toán | `resources/views/orders/checkout.blade.php` | `app/Http/Controllers/User/OrderController.php` method `checkout`, `process` | `app/Services/GHNService.php` |
| Đơn mua (danh sách) | `resources/views/orders/index.blade.php` | `User/OrderController.php` method `history` | |
| Chi tiết đơn, nhận hàng, hủy, trả MoMo | `resources/views/orders/show.blade.php` | `User/OrderController.php` method `show`, `cancel`, `confirmReceived` | `app/Services/EscrowService.php`, `app/Services/WalletService.php` |
| Khiếu nại trên đơn | form trong `orders/show.blade.php` | `app/Http/Controllers/DisputeController.php` | |
| Sang MoMo / callback | không có trang Blade riêng (redirect) | `app/Http/Controllers/User/MomoController.php` | `app/Services/MomoService.php` |
| Tỉnh / quận / phường, phí ship | gọi từ checkout | `app/Http/Controllers/User/GHNController.php` | `app/Services/GHNService.php`, `app/Services/GHNOrderService.php` |

Kênh người bán dùng khung riêng `resources/views/layouts/seller.blade.php` (shop, sản phẩm, ngân hàng, tiền về). Tài khoản mua hàng dùng `resources/views/layouts/account.blade.php`.

## Tài khoản và người bán

| Trên giao diện | Giao diện | Controller | Logic kèm |
|---|---|---|---|
| Đăng ký | `resources/views/auth/register.blade.php` | `app/Http/Controllers/AuthController.php` | |
| Đăng nhập | `resources/views/auth/login.blade.php` | `AuthController.php` method `showLoginForm`, `confirmHuman`, `login` | nút «Tôi là người»; sai mật khẩu thì `app/Services/LoginJail.php` khóa IP theo `config/fail2ban.php` |
| Quên mật khẩu | `resources/views/auth/forgot-password.blade.php` | `AuthController.php` | |
| Đặt lại mật khẩu | `resources/views/auth/reset-password.blade.php` | `AuthController.php` | |
| Xác thực email | `resources/views/auth/verify-email.blade.php` | `AuthController.php` | `app/Services/EmailVerificationService.php` |
| Thông tin hồ sơ | `resources/views/account/profile.blade.php` | `app/Http/Controllers/AccountController.php` method `profile` | |
| Đổi email / SĐT / mật khẩu | `resources/views/account/change/*.blade.php` | `AccountController.php` method `change*` | |
| KYC | `resources/views/account/kyc.blade.php` | `app/Http/Controllers/KycController.php` | `app/Services/MediaService.php` |
| Yêu thích | `resources/views/account/favorites.blade.php` | `app/Http/Controllers/FavoriteController.php` | |
| Tin nhắn (danh sách) | `resources/views/messages/index.blade.php` | `ConversationController.php` method `index` | |
| Một cuộc chat, trả giá | `resources/views/messages/show.blade.php` | `ConversationController.php` method `show`, `reply`, `acceptOffer`, `poll` | |
| Chat với admin | `resources/views/account/support.blade.php` | `app/Http/Controllers/User/SupportChatController.php` | model `app/Models/ChatMessage.php` |
| Tin đang bán | `resources/views/seller/listings/index.blade.php` | `app/Http/Controllers/SellerListingController.php` method `index` | khung `layouts/seller.blade.php` |
| Tài khoản ngân hàng shop | `resources/views/seller/bank.blade.php` | `app/Http/Controllers/SellerBankController.php` | `app/Services/FinanceBook.php` gọi service `routes/finance.php` khi chạy Docker; model `app/Models/Finance/BankAccount.php` |
| Phản hồi người mua | `resources/views/seller/reviews/index.blade.php` | `app/Http/Controllers/SellerReviewController.php` | model `app/Models/Review.php`, hiện câu trả lời ở `resources/views/listings/show.blade.php` |
| Thu chi shop | `resources/views/seller/earnings.blade.php` | `app/Http/Controllers/SellerEarningController.php` | biểu đồ và CSV qua `FinanceBook`; sổ `app/Models/Finance/LedgerEntry.php` |
| Đăng tin / Sửa tin | `resources/views/seller/listings/form.blade.php` | `SellerListingController.php` method `create`, `store`, `edit`, `update` | `app/Services/OriginService.php`, `app/Services/PricingEngine.php`, `app/Services/MediaService.php` |
| AI Care (nút nổi) | `resources/views/ai/care.blade.php`, `resources/views/ai/widget.blade.php` | `app/Http/Controllers/AiController.php` method `care` | `app/Services/RelicCareAi.php`, `app/Services/LlmClient.php` |

## Admin (`/admin`)

Khung menu: `resources/views/layouts/admin.blade.php`. AI Ops: `resources/views/ai/ops.blade.php` → `AiController.php` method `ops` → `app/Services/RelicOpsAi.php`.

| Menu admin | Giao diện | Controller |
|---|---|---|
| Tổng quan | `resources/views/admin/dashboard.blade.php` | `app/Http/Controllers/Admin/AdminController.php` method `dashboard` |
| Doanh thu | `resources/views/admin/analytics/index.blade.php` | `app/Http/Controllers/Admin/AnalyticsController.php` |
| Tin đăng | `resources/views/admin/listings/index.blade.php` | `AdminController.php` method `listings`, `approveListing`, `rejectListing`, `hideListing` |
| Đơn hàng | `resources/views/admin/orders/index.blade.php` | `app/Http/Controllers/Admin/OrderController.php` |
| Chi tiết đơn admin | `resources/views/admin/orders/show.blade.php` | `Admin/OrderController.php` method `show` |
| Chat khách | `resources/views/admin/chat/index.blade.php`, `admin/chat/show.blade.php` | `app/Http/Controllers/Admin/ChatController.php` |
| Người dùng | `resources/views/admin/users/index.blade.php` | `AdminController.php` method `users` |
| Báo cáo tin | `resources/views/admin/reports/index.blade.php` | `AdminController.php` method `reports` |
| KYC | `resources/views/admin/kyc/index.blade.php` | `AdminController.php` method `kyc`, `approveKyc`, `rejectKyc` |
| Khiếu nại | `resources/views/admin/disputes/index.blade.php` | `AdminController.php` method `disputes`, `resolveDispute` |
| Chặn tấn công | `resources/views/admin/traffic/index.blade.php` | `app/Http/Controllers/Admin/TrafficController.php` | giới hạn `config/traffic.php`; khóa đăng nhập `config/fail2ban.php` |
| Dòng tiền | `resources/views/admin/cashflow/index.blade.php` | `app/Http/Controllers/Admin/CashflowController.php` | `FinanceBook` / service ngân hàng, xuất CSV `admin.cashflow.export` |
| Tài chính | `resources/views/admin/finance/index.blade.php` | `app/Http/Controllers/Admin/FinanceController.php` |
| Giao dịch tài chính | `resources/views/admin/finance/transactions.blade.php` | `FinanceController.php` method `transactions` |
| Ví (duyệt nạp / rút) | `resources/views/admin/finance/wallet.blade.php` | `AdminController.php` method `finance`, `approveWallet` |
| Danh mục | `resources/views/admin/categories/index.blade.php` | `AdminController.php` method `categories` |
| Biểu đồ dùng chung | `resources/views/admin/partials/chartjs.blade.php`, `admin/partials/section-insights.blade.php` | `app/Services/AdminAnalyticsService.php` |

## App Flutter (không có Blade)

| Chức năng app | API | Controller |
|---|---|---|
| Danh sách / chi tiết tin | `GET /api/listings` | `app/Http/Controllers/Api/ListingController.php` |
| Đăng nhập, OTP, Google, Apple | `POST /api/auth/*` | `app/Http/Controllers/Api/AuthController.php` |
| Yêu thích, chat | `/api/favorites`, `/api/conversations` | `app/Http/Controllers/Api/AccountController.php` |
| Màn hình app | thư mục `mobile/` | gọi `API_URL` tới các route trên |
