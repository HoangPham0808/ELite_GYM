# ELite_GYM (MVC)

**URL chính:** `http://localhost/PHP/ELite_GYM/public/`

Redirect gốc: `http://localhost/PHP/ELite_GYM/` → `public/`

---

## Cấu trúc

```
ELite_GYM/
├── app/
│   ├── config/          config.php, database.php
│   ├── core/            App, Router, Controller, Model, Database, AuthMiddleware
│   ├── controllers/     *Controller.php
│   ├── models/          Model theo bảng
│   ├── views/           Giao diện (auth, home, admin, staff, layouts)
│   ├── api/             Endpoint xử lý POST/AJAX (gọi qua Controller::api)
│   └── helpers/
├── public/
│   ├── index.php        Front controller
│   ├── .htaccess
│   └── assets/          css, js, images, uploads
├── routes/web.php
├── storage/logs, cache
├── vendor/PHPMailer/
└── SETUP_DATABASE.sql
```

---

## URL

| Chức năng | URL |
|-----------|-----|
| Trang chủ | `/public/` |
| Đăng nhập | `/public/login` |
| Đăng ký | `/public/register` |
| Hồ sơ | `/public/profile` |
| Thanh toán | `/public/payment` |
| Lịch tập | `/public/schedule` |
| Đánh giá | `/public/review` |
| Admin | `/public/admin` |
| Module admin | `/public/admin/module/{slug}` |
| Đăng xuất | `/public/logout` |
| API lịch tập | `/public/api/schedule` |
| API thông báo | `/public/api/notification` |
| Webhook | `/public/webhook/payment` |

Upload ảnh: `/public/assets/uploads/image_package/`, `image_panel/`

---

## Luồng request

1. `public/index.php` → `App::run()`
2. `routes/web.php` → Controller
3. View: `$this->view('home/index')` → `app/views/home/index.php`
4. API: `$this->api('Payment_function.php')` → `app/api/Payment_function.php`

---

## Thư mục legacy

`Home/`, `Internal/`, `Database/db.php` có thể còn để tham chiếu — **không dùng trực tiếp**. Chạy app chỉ qua `public/`.

Migration: `scripts/migrate_mvc_structure.php`
