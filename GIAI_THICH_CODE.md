# Relic — giải thích toàn bộ code

Sàn mua bán đồ điện tử cũ (Relic). Người mua tìm tin, chat trả giá, đặt hàng COD hoặc MoMo. Tiền MoMo được sàn giữ (escrow) đến khi người mua xác nhận nhận hàng, rồi giải ngân ví người bán trừ 5% phí. Người bán phải KYC trước khi đăng tin. Admin duyệt KYC, khiếu nại, ví, vận đơn và thống kê.

Stack: **Laravel 12 / PHP 8.2+**, Blade, MySQL, Sanctum (API app Flutter trong `mobile/`). Ảnh có thể nằm đĩa `public` hoặc S3. Tìm kiếm dùng SQL, Elasticsearch chỉ khi có `ELASTICSEARCH_HOST`.

Muốn từ một nút trên giao diện mở đúng file: xem `MO_FILE_GIAO_DIEN.md`.

---

## 1. Bản đồ thư mục

| Thư mục | Vai trò |
|---|---|
| `routes/web.php` | Toàn bộ trang web: chợ, auth, giỏ, đơn, bán, admin |
| `routes/api.php` | API cho app Flutter: tin, auth, yêu thích, chat |
| `app/Http/Controllers` | Điều phối request. `Admin/` và `User/` tách quyền |
| `app/Services` | Nghiệp vụ: escrow, ví, MoMo, GHN, tìm kiếm, AI, media |
| `app/Models` | Eloquent: User, Listing, Order, Conversation, Dispute, … |
| `app/Mcp` | Máy chủ MCP (JSON-RPC) gọi Relic Care / Relic Ops |
| `resources/views` | Giao diện Blade. Layout chính: `layouts/store.blade.php` |
| `database/migrations` | Schema sàn + ví + KYC + chat hỗ trợ |
| `database/seeders/DatabaseSeeder.php` | Admin + danh mục + thương hiệu (và tin mẫu lần đầu) |
| `public/` | Document root. CSS/JS tĩnh, shader `threeui/` |
| `docker/`, `Dockerfile`, `render.yaml` | Bản deploy Render |
| `mobile/` | App Flutter, gọi `/api` |

Mọi request web đi vào `public/index.php` → `bootstrap/app.php` → route. Health check deploy là `GET /up`.

Middleware gắn trong `bootstrap/app.php`:

- `SecurityHeaders` — header bảo mật trên mọi response
- `EnsureNotBanned` — user bị khóa không dùng web
- `admin` — chỉ `users.role = admin`
- `deny.admin.shop` — admin không vào giỏ / thanh toán / thêm giỏ
- CSRF bỏ qua `ghn/webhook`, `payment/momo/ipn`, `auth/apple/callback`
- `trustProxies('*')` — tin header proxy của Render

---

## 2. Dữ liệu chính

**User** (`app/Models/User.php`): `role` (`user` / `admin`), email bắt buộc xác thực (`MustVerifyEmail`), KYC (`kyc_status`: none / pending / verified / rejected), ví `wallet_balance` + `wallet_frozen`, cờ `is_banned`, tọa độ, Google/Apple id.

**Listing**: một máy một tin. Trạng thái: `pending_review`, `active`, `hidden`, `reserved`, `sold`, `rejected`. Đăng mới hiện **lên `active` ngay** (không chờ duyệt). Có `areas` (JSON khu vực), GPS, `extras` (hộp, bảo hành), ảnh, hồ sơ nguồn gốc `listing_origins` (serial/IMEI chỉ lưu 4 số cuối + hash, mã niêm phong `seal_code`).

**Order**: mã `RLC…`. Trạng thái đơn: `pending` → `paid` (MoMo) hoặc `cod_ordered` / `cod_paid` → `completed` / `refunded` / `cancelled`. Escrow riêng: `none`, `held`, `released`, `refunded`, `disputed`, `cod`. Phí ship GHN nằm `ghn_total_fee`, mã vận đơn `ghn_order_code`.

**OrderItem**: gắn tin + người bán + giá chốt (có thể là giá đã chấp nhận trong chat, không phải giá niêm yết).

**PaymentTransaction**: cổng `momo` hoặc `cod`, trạng thái pending / initiated / paid / failed.

**WalletTransaction**: `topup`, `withdraw`, `payout`, `refund`, hoa hồng, phí đẩy tin. Admin duyệt nạp/rút.

**Conversation + Message**: chat mua–bán theo tin, có `offer_amount` và `accepted_price`.

**ChatMessage**: kênh hỗ trợ khách ↔ admin (`/ho-tro`), khác chat mua bán.

**Dispute**: khiếu nại đơn đang `held` hoặc `cod`.

**SearchAlert**: người dùng lưu từ khóa; khi có tin khớp thì tăng `hits` (không gửi email).

---

## 3. Luồng chính

### 3.1. Xem chợ

1. `GET /` → `HomeController`: danh mục cache `relic.categories.active`, tin nổi bật / đã xác minh nguồn gốc, lọc theo khu vực session.
2. `GET /cho` → `ListingController` + `ListingSearch`: lọc danh mục, hãng, giá, tình trạng, khu vực, GPS (bounding box rồi tính km).
3. Có `ELASTICSEARCH_HOST` thì tìm id trên index `relic_listings`, không thì `LIKE` SQL. Elasticsearch lỗi thì rơi về SQL.
4. `GET /tin/{slug}` chi tiết. `GET /cua-hang/{user}` gian hàng.
5. `POST /vi-tri` và `POST /khu-vuc` lưu GPS / tỉnh vào session (`AreaService`, `GeoService`).

Mỗi lần `Listing` save/delete, model gọi `ElasticsearchService` trong `try/catch`. Không có host thì bỏ qua.

### 3.2. Tài khoản

- Đăng ký / đăng nhập / quên mật khẩu: `AuthController`. Login throttle 8 lần/phút.
- Xác thực email: mã OTP (`EmailVerificationService` + `OtpCode`), route `verification.confirm`. Link ký `verification.verify` vẫn có. Mail local mặc định ghi log (`MAIL_MAILER=log`), không gửi SMTP thật.
- Đổi email/SĐT/mật khẩu qua `/ho-so/thay-doi`: gửi mã, xác nhận, áp dụng (`AccountController`).
- API (`routes/api.php`): register/login, OTP SMS (`SmsService`, driver `log` | `twilio` | `esms`), Google/Apple, Sanctum cho `/me`, yêu thích, chat.
- Web không có đăng nhập Google/Apple. App Flutter gọi `POST /api/auth/google` và `POST /api/auth/apple`.

### 3.3. Đăng bán

Điều kiện: đã đăng nhập, **email đã verify**, **KYC verified**. Admin bị chặn.

`POST /ban/dang-tin` (`SellerListingController@store`):

1. Validate, đánh dấu user `is_seller`.
2. Tạo listing `status = active`, `published_at = now()`.
3. `PricingEngine` ước giá nếu có giá gốc + hãng (hệ số `brands.price_multiplier`).
4. `MediaService` lưu ảnh. `OriginService` lưu hồ sơ nguồn gốc (ảnh hóa đơn/hộp).
5. `pingAlerts`: quét `search_alerts`, tăng hit nếu khớp.

Sửa tin bị từ chối / chờ duyệt sẽ đưa lại `active`. Ẩn, hiện, đánh dấu đã bán, đẩy tin (`boost`, trừ ví `WalletService::BOOST_FEE` = 50.000₫).

Admin vẫn có duyệt / từ chối / ẩn / xác minh nguồn gốc (`AdminController`). Các nút đó có tác dụng với tin `pending_review` hoặc tin admin chủ động ẩn, không phải cổng bắt buộc của tin mới.

### 3.4. Chat và trả giá

`POST /tin/{slug}/chat` mở `Conversation`. Trang `/tin-nhan/{id}` poll tin mới qua `GET .../moi` (không phụ thuộc Reverb).

`acceptOffer`: người bán chấp nhận giá → `conversations.accepted_price`. Lúc checkout, nếu giá trong giỏ khác giá tin, hệ thống chỉ cho qua khi đúng `accepted_price` của buyer đó.

Reverb có trong `composer.json` và `config/reverb.php`, nhưng Docker **không** chạy `php artisan reverb:start`. Trên web, chat thực tế là poll. App Flutter cũng poll REST.

### 3.5. Giỏ → thanh toán → vận chuyển → escrow

Giỏ nằm **session** (`session('cart')`), không có bảng cart. Mỗi tin số lượng phải là 1.

`POST /thanh-toan` (`OrderController@process`):

1. Bắt buộc tên, SĐT `0` + 9 số, địa chỉ, quận/phường GHN, `payment_method` = `cod` | `momo`.
2. `GHNService::calculateFee`. API GHN không trả `code = 200` thì **phí ship = 0** (đơn vẫn tạo).
3. Transaction: khóa listing, từ chối tin không `active`, từ chối lệch giá (trừ deal chat), tạo `Order` + `OrderItem`, status `pending`.
4. Xóa giỏ khỏi session **trước** khi gọi cổng thanh toán. Nếu MoMo/GHN lỗi sau đó, giỏ đã mất; đơn vẫn còn trong `/don-hang`.

**COD**

- Giao dịch `cod` / pending.
- `GHNOrderService::create`. Thành công → `cod_ordered` + mã vận đơn + `shipping_status = picking`.
- Thất bại → vẫn `cod_ordered`, `shipping_status = pending`, flash cảnh báo, ghi log.
- `EscrowService::markCod`: `escrow_status = cod`, không giữ tiền ví.

**MoMo**

- Tạo `PaymentTransaction` pending → redirect `GET /don-hang/{order}/momo/start`.
- `MomoService::createPayment` ký HMAC, gọi endpoint (mặc định **sandbox** `test-payment.momo.vn`), lưu `payUrl`.
- Người mua thanh toán xong, MoMo gọi:
  - `GET /payment/momo/callback` (trình duyệt)
  - `POST /payment/momo/ipn` (server MoMo, không CSRF)
- Cả hai đi `completePayment`: khóa transaction, đối chiếu số tiền và chữ ký, đơn → `paid`, escrow `held` (chỉ tiền hàng, **không** gồm phí ship), rồi tạo vận đơn GHN. Callback và IPN dùng chung lock để không tạo hai vận đơn.

**Nhận hàng** `POST /don-hang/{order}/nhan-hang`

- MoMo (`held`): `releaseToSellers` cộng ví người bán = giá hàng − 5%, ghi giao dịch hoa hồng, đơn `completed`, escrow `released`.
- COD: không cộng ví, chỉ đánh `completed`.

**Hủy**: hoàn ví nếu đang giữ escrow; đơn COD/chưa trả thì hủy. **Khiếu nại** (`DisputeController`) chuyển escrow `disputed`, admin `resolveDispute` chọn hoàn buyer hoặc giải ngân seller.

**Webhook GHN** `POST /ghn/webhook`: map status → `shipping_status`. Giao COD thành công (`delivered`) đẩy đơn `cod_ordered`/`pending` sang `cod_paid`. Hủy vận đơn thì hủy đơn nếu chưa giữ/giải ngân tiền MoMo.

### 3.6. Admin `/admin`

Dashboard, duyệt tin, user (khóa, xóa, bật seller), đơn, chat hỗ trợ, báo cáo tin, danh mục, KYC, tài chính + export CSV, analytics, khiếu nại. AI vận hành: `POST /admin/ai` → `RelicOpsAi` (không tự duyệt tin: `config/ai.php` `auto_approve = false`).

### 3.7. Relic AI

- `POST /ai/care` (đã login): `RelicCareAi` + `RelicCareBrain` + kiến thức `RelicCareKnowledge`. Có API key thì `LlmClient` thử Gemini → Groq → OpenRouter → OpenAI. Không key thì trả template cục bộ.
- `POST /mcp`: JSON-RPC cho client MCP, tool theo vai trò user/admin.
- `php artisan relic:ai-train`: huấn NLU cục bộ (`app/Ai/Trainer.php`).

---

## 4. Cách các chức năng được triển khai

| Chức năng | Cách làm |
|---|---|
| Tìm tin | SQL là đường chính. Elasticsearch tùy chọn, lỗi thì fallback |
| Ảnh | `MediaService`: có đủ key+bucket S3 thì `s3:…`, không thì `storage/…` trên đĩa `public` |
| Tiền | Ví nội bộ (số nguyên VND), không rút tự động ra ngân hàng. Nạp/rút chờ admin |
| Phí sàn | 5% trên **giá hàng** lúc giải ngân MoMo, không trên phí ship |
| Định danh bán | Upload CCCD, admin duyệt. Serial/IMEI không lưu full |
| Vận chuyển | GHN tính phí + tạo đơn + webhook. Thiếu token/shop thì phí 0 hoặc tạo đơn thất bại nhưng COD vẫn ghi nhận |
| Thanh toán online | MoMo ATM (`payWithATM`). Chữ ký HMAC-SHA256 |
| Chat | Poll HTTP. Reverb có package, không chạy trong container |
| Giỏ | Session file, mất khi hết session hoặc đổi máy chủ |
| App mobile | REST + Sanctum. GPS, OTP, Google/Apple nằm ở API |
| Bảo vệ | Throttle login/AI, cấm admin mua, khóa user, CSRF, header bảo mật |

---

## 5. Máy local (XAMPP) và bản đã deploy

Code trên `main` **trùng** `origin/main` (commit `b64ea7c`: seed admin khi container lên, vì Render Free không có shell). Render build Docker từ `Dockerfile`, chạy `docker/entrypoint.sh`.

### 5.1. Chạy trên máy

- Thư mục `D:\xampp\htdocs\san_giao_dịch_do_cu`. Apache/XAMPP trỏ document root tới `public`, hoặc `php artisan serve` (`.env.example`: `APP_URL=http://127.0.0.1:8000`).
- MySQL local: `127.0.0.1:3306`, database `relic_market`, user `root`, không SSL (`MYSQL_ATTR_SSL_CA` trống).
- `APP_ENV=local`, `APP_DEBUG=true`, log `debug`, mail `log`, SMS `log`, queue `database` (cần `php artisan queue:listen` nếu dùng job; hiện gần như không có Job class).
- Ảnh nằm `storage/app/public` + `php artisan storage:link`.
- MoMo/GHN trên localhost: MoMo **không gọi được IPN** vào máy nhà. Callback trình duyệt cũng cần URL công khai. Tên thư mục có dấu (`san_giao_dịch_do_cu`) được `MomoService::asciiUrl` encode path trước khi gửi MoMo.
- Elasticsearch, Reverb, S3, SMTP: tắt nếu để trống trong `.env`.

### 5.2. Đã deploy (Render + Aiven)

`render.yaml` + entrypoint:

| Hạng mục | Local | Deploy |
|---|---|---|
| Runtime | PHP XAMPP / `artisan serve` | Image `php:8.3-apache`, `composer install --no-dev` |
| `APP_ENV` / debug | local / true | production / false |
| Database | MySQL XAMPP `relic_market` | Aiven `san-do-cu-lab10.i.aivencloud.com:11279` / `defaultdb`, SSL CA `/etc/secrets/ca.pem` |
| Queue | `database` | `sync` (xử lý ngay trong request, không worker) |
| Cache | database | database |
| Session | file | file trên đĩa container (mất khi redeploy / sleep) |
| Log | file `storage/logs` | `stderr` (entrypoint đặt `LOG_CHANNEL`) |
| Mỗi lần container start | migrate tay | `migrate --force`, `db:seed --force`, `config:cache`, `view:cache`, `storage:link` |
| Health | không bắt buộc | `GET /up` |
| Gói dev (Pint, PHPUnit, Pail) | có | không cài |

Biến **không** khai trong `render.yaml` (phải set tay trên Render, không thì dùng default trong code): `GHN_*`, `MOMO_*`, `MAIL_*`, `AWS_*`, `GOOGLE_*`, `APPLE_*`, `AI`/`GEMINI`/`GROQ`, `ELASTICSEARCH_HOST`, `SMS_DRIVER`. Hệ quả: bản deploy dễ đang dùng **MoMo sandbox** (default trong `config/services.php`) và **không có GHN** nếu chưa điền env. Ảnh không có S3 thì ghi đĩa container — **mất khi Render thay instance**.

`APP_KEY`, `APP_URL`, `DB_PASSWORD` đánh `sync: false`: điền trên dashboard Render, không commit.

### 5.3. File đang có trên máy nhưng chưa nằm trên `main`

Git đang lệch working tree so với commit đã đẩy:

- `app/Models/SearchAlert.php` (thay đổi local)
- `public/threeui/sign-up-button.html`, `src/shaders/sign-up-button/…` (chưa commit)
- `_glass_find.py` (script phụ, chưa commit)

Render build từ Git, nên các file này **không có trên bản deploy** cho đến khi commit và redeploy.

---

## 6. Vấn đề trọng tâm

1. **Seed mỗi lần container khởi động.** `entrypoint.sh` gọi `db:seed --force`. Seeder luôn `updateOrCreate` admin `admin@relic.test` với mật khẩu cứng trong `DatabaseSeeder.php`. Đổi mật khẩu trên web sẽ bị ghi lại lần deploy/restart sau. Danh mục chỉ seed khi bảng `categories` trống, nên không nhân đôi tin, nhưng tài khoản admin thì bị reset.

2. **MoMo mặc định là sandbox, key nằm trong `config/services.php`.** Production phải set `MOMO_PARTNER_CODE`, `MOMO_ACCESS_KEY`, `MOMO_SECRET_KEY`, `MOMO_ENDPOINT`, `MOMO_REDIRECT_URL`, `MOMO_IPN_URL` trỏ domain Render (https). IPN phải public; localhost và URL có dấu không nhận được IPN.

3. **Đơn COD được ghi nhận cả khi GHN tạo vận đơn thất bại.** Phí ship cũng thành 0 nếu API phí lỗi. Người mua thấy “đặt hàng thành công” trong khi chưa có mã vận đơn.

4. **Giỏ session bị xóa trước khi thanh toán xong.** MoMo fail hoặc mất session (Render sleep, đĩa ephemeral, `SESSION_DRIVER=file`) làm giỏ biến mất; đơn `pending` vẫn còn để trả lại qua `/don-hang/{id}/momo`.

5. **Ảnh và session không bền trên Render Free** nếu không chuyển S3 và session/cache sang database hoặc Redis. `storage:link` mỗi lần boot không giữ file upload cũ.

6. **Tin đăng là `active` ngay**, trong khi UI admin vẫn có hàng chờ duyệt. `pending_review` gần như không còn là cổng vào chợ.

7. **Escrow không giữ phí ship, và ví không phải tiền MoMo thật.** `holdPaidOrder` chỉ cộng giá các `order_items`. Giải ngân cộng số vào `wallet_balance`. Hoàn khiếu nại cũng cộng ví, không gọi API hoàn MoMo. Rút tiền là admin duyệt tay (`approveWithdraw`), không có chuyển khoản tự động.

8. **Webhook GHN không kiểm tra chữ ký.** Biết `ghn_order_code` là có thể đẩy trạng thái (kể cả `cod_paid`).

9. **Chat realtime không chạy trên deploy.** Package Reverb có, process không có. Poll đủ dùng, nhưng cấu hình `REVERB_*` trong `.env` không làm chat sống trên Render.

10. **Hai database khác nhau.** Local `relic_market` và Aiven `defaultdb` không đồng bộ. Sửa data máy nhà không xuất hiện trên site deploy, và ngược lại.

11. **Admin không mua được** (`DenyAdminShop`). Test giỏ/MoMo trên production phải dùng user thường đã verify email.

12. **Queue production = sync, local = database.** Không có worker trên Render. Tác vụ nặng (nếu thêm job sau này) sẽ chạy trong request và dễ timeout.

---

## 7. Thứ tự đọc code khi cần sửa

1. Route: `routes/web.php` (trang) hoặc `routes/api.php` (app).
2. Controller tương ứng trong `app/Http/Controllers`.
3. Rule tiền / vận đơn: `EscrowService`, `WalletService`, `MomoService`, `GHNOrderService`, `User/OrderController`, `User/MomoController`.
4. Tin và tìm kiếm: `SellerListingController`, `ListingSearch`, `Listing`.
5. Môi trường: `.env` local so với `render.yaml` + `docker/entrypoint.sh` + `config/services.php`.
