<?php
/** @var Router $router */
$router = new Router();

// ── Trang chủ & khách hàng ───────────────────────────────────
$router->get('/', 'HomeController@index');
$router->get('/profile', 'HomeController@profile');
$router->get('/payment', 'HomeController@payment');
$router->get('/schedule', 'HomeController@schedule');
$router->get('/review', 'HomeController@review');

// ── Auth ─────────────────────────────────────────────────────
$router->get('/login', 'AuthController@loginForm');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@registerForm');
$router->post('/register', 'AuthController@register');
$router->get('/forgot-password', 'AuthController@forgotForm');
$router->post('/forgot-password', 'AuthController@forgot');
$router->get('/logout', 'AuthController@logout');

// ── Staff ──────────────────────────────────────────────────────
$router->get('/staff/hlv', 'HomeController@staffHlv');
$router->get('/staff/receptionist', 'HomeController@staffReceptionist');

// ── Admin dashboard ────────────────────────────────────────────
$router->get('/admin', 'AdminController@dashboard');
$router->get('/admin/module/{page}', 'AdminController@module');

// ── API khách hàng ─────────────────────────────────────────────
$router->any('/api/payment', 'PaymentController@api');
$router->any('/api/profile', 'ProfileController@api');
$router->any('/api/schedule', 'ScheduleController@api');
$router->post('/api/schedule/ai', 'ScheduleController@ai');
$router->any('/api/review', 'ReviewController@api');
$router->any('/api/notification', 'ReviewController@notification');
$router->any('/api/notification/auto', 'ReviewController@notificationAuto');

// ── API Admin ──────────────────────────────────────────────────
$router->any('/api/admin/account', 'AdminController@apiAccount');
$router->any('/api/admin/customer', 'CustomerController@api');
$router->any('/api/admin/employee', 'EmployeeController@api');
$router->any('/api/admin/employee-attendance', 'EmployeeController@apiAttendance');
$router->any('/api/admin/facility', 'FacilityController@api');
$router->any('/api/admin/gym', 'AdminController@apiGym');
$router->any('/api/admin/invoice', 'InvoiceController@api');
$router->any('/api/admin/package', 'PackageController@api');
$router->any('/api/admin/promotion', 'PromotionController@api');
$router->any('/api/admin/review', 'ReviewController@apiAdmin');
$router->any('/api/admin/schedule', 'ScheduleController@apiAdmin');
$router->any('/api/admin/statistics', 'StatisticsController@api');
$router->any('/api/admin/overview', 'StatisticsController@apiOverview');
$router->any('/api/admin/setting/system', 'SettingController@apiSystem');
$router->any('/api/admin/setting/gps', 'SettingController@apiGps');
$router->any('/api/admin/setting/landing', 'SettingController@apiLanding');
$router->any('/api/admin/profile', 'ProfileController@apiAdmin');
$router->any('/api/admin/qr', 'QRController@api');
$router->get('/api/admin/qr/ngrok', 'QRController@ngrok');
$router->any('/api/admin/qr/bridge', 'QRController@bridge');
$router->get('/qr-scanner', 'QRController@scanner');

// Webhook thanh toán
$router->any('/webhook/payment', 'PaymentController@webhook'); // ✅ FIX: post() có thể chưa match đúng → dùng any() để đảm bảo nhận POST từ SePay

return $router;
