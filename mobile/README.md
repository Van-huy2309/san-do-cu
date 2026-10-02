# Relic Flutter

App mua bán Relic: đăng nhập email / OTP SMS / Google / Apple, tìm tin (Elasticsearch nếu server bật), lọc GPS bán kính, chat (poll + Reverb trên web).

## Chạy

1. API Laravel: `php artisan serve --host=0.0.0.0 --port=8000`
2. Android emulator gọi host máy qua `10.0.2.2`:

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_URL=http://10.0.2.2:8000/api
```

iOS simulator dùng `http://127.0.0.1:8000/api`.

## Backend cần

- `php artisan migrate`
- Google: `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`
- Apple: `APPLE_CLIENT_ID` + key
- SMS thật: `SMS_DRIVER=twilio` hoặc `esms`
- Ảnh S3: điền `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`
- Elasticsearch: `ELASTICSEARCH_HOST=http://127.0.0.1:9200`
- Chat live web: `BROADCAST_CONNECTION=reverb` rồi `php artisan reverb:start` (app Flutter poll REST)
