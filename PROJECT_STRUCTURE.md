# ELITE_GYM

```
ELITE_GYM/
├── .gitattributes
├── README.md
├── PROJECT_STRUCTURE.md
├── SETUP_DATABASE.sql
├── index.php
├── review_api_response.html
├── start
├── stop
├── temp_admin.html
├── temp_review.html
├── temp_review_check.php
├── app/
│   ├── config/
│   │   ├── config.php
│   │   ├── database.php
│   │   └── mail.php
│   ├── controllers/
│   │   ├── AdminController.php
│   │   ├── AuthController.php
│   │   ├── CustomerController.php
│   │   ├── EmployeeController.php
│   │   ├── FacilityController.php
│   │   ├── HomeController.php
│   │   ├── InvoiceController.php
│   │   ├── PackageController.php
│   │   ├── PaymentController.php
│   │   ├── ProfileController.php
│   │   ├── PromotionController.php
│   │   ├── QRController.php
│   │   ├── ReviewController.php
│   │   ├── ScheduleController.php
│   │   ├── SettingController.php
│   │   └── StatisticsController.php
│   ├── core/
│   │   ├── App.php
│   │   ├── AuthMiddleware.php
│   │   ├── Controller.php
│   │   ├── Database.php
│   │   ├── Model.php
│   │   ├── Router.php
│   │   └── SecurityMiddleware.php
│   ├── helpers/
│   │   ├── format_helper.php
│   │   ├── qr_helper.php
│   │   ├── session_helper.php
│   │   └── upload_helper.php
│   ├── models/
│   │   ├── Account.php
│   │   ├── Customer.php
│   │   ├── Employee.php
│   │   ├── Facility.php
│   │   ├── Gym.php
│   │   ├── Invoice.php
│   │   ├── output_MailService.php
│   │   ├── output_NotificationService.php
│   │   ├── Package.php
│   │   ├── Payment.php
│   │   ├── Promotion.php
│   │   ├── QRCheckin.php
│   │   ├── Review.php
│   │   ├── Schedule.php
│   │   ├── Statistics.php
│   │   └── SystemSetting.php
│   ├── services/
│   │   ├── MailService.php
│   │   └── NotificationService.php
│   ├── storage/
│   │   └── qr_bridge_store.json
│   └── views/
│       ├── admin/
│       │   ├── account_management.php
│       │   ├── customer_management.php
│       │   ├── dashboard.php
│       │   ├── employee_attendance.php
│       │   ├── employee_management.php
│       │   ├── facilities_management.php
│       │   ├── gym_management.php
│       │   ├── invoice_management.php
│       │   ├── overview.php
│       │   ├── package_management.php
│       │   ├── profile.php
│       │   ├── promotion_management.php
│       │   ├── review_management.php
│       │   ├── schedule_management.php
│       │   ├── setting_gps.php
│       │   ├── setting_landing.php
│       │   ├── setting_system.php
│       │   └── statistics.php
│       ├── auth/
│       │   ├── forgot_password.php
│       │   ├── login.php
│       │   └── register.php
│       ├── home/
│       │   ├── index.php
│       │   ├── partials/
│       │   │   ├── notification_ui.php
│       │   │   └── reviews_section.php
│       │   ├── payment.php
│       │   ├── profile.php
│       │   ├── review.php
│       │   ├── schedule.php
│       │   └── Schedule_view_backup.php
│       ├── layouts/
│       │   ├── footer.php
│       │   ├── header.php
│       │   ├── navbar.php
│       │   └── sidebar.php
│       └── staff/
│           ├── hlv.php
│           └── receptionist.php
├── public/
│   ├── .htaccess
│   ├── index.php
│   └── assets/
│       ├── css/
│       │   ├── Account_Management.css
│       │   ├── adm.css
│       │   ├── Customer_Management.css
│       │   ├── Employee_attendance_tracking.css
│       │   ├── Employee_Management.css
│       │   ├── Facilities_Management.css
│       │   ├── Forgot_Password.css
│       │   ├── GPS.css
│       │   ├── Gym_Management.css
│       │   ├── HLV.css
│       │   ├── Image_landing.css
│       │   ├── Invoice_Management.css
│       │   ├── landing.css
│       │   ├── Login.css
│       │   ├── management_statistics.css
│       │   ├── notification.css
│       │   ├── overview.css
│       │   ├── Package_Management.css
│       │   ├── Payment.css
│       │   ├── Profile.css
│       │   ├── Profile_employee.css
│       │   ├── Promotion_Management.css
│       │   ├── QR_CheckIn.css
│       │   ├── Receptionist.css
│       │   ├── Register.css
│       │   ├── Review_Management.css
│       │   ├── reviews_section.css
│       │   ├── Schedule.css
│       │   ├── Schedule_Management.css
│       │   └── System.css
│       ├── images/
│       │   └── ELITY.png
│       ├── js/
│       │   ├── Account_Management.js
│       │   ├── adm.js
│       │   ├── ai_stream.js
│       │   ├── Customer_Management.js
│       │   ├── Employee_attendance_tracking.js
│       │   ├── Employee_Management.js
│       │   ├── Facilities_Management.js
│       │   ├── Forgot_Password.js
│       │   ├── GPS.js
│       │   ├── Gym_Management.js
│       │   ├── HLV.js
│       │   ├── Image_landing.js
│       │   ├── Invoice_Management.js
│       │   ├── landing.js
│       │   ├── legacy-ready.js
│       │   ├── Login.js
│       │   ├── management_statistics.js
│       │   ├── overview.js
│       │   ├── Package_Management.js
│       │   ├── Payment.js
│       │   ├── Profile.js
│       │   ├── Profile_customer.js
│       │   ├── Promotion_Management.js
│       │   ├── QR_CheckIn.js
│       │   ├── qrcode.min.js
│       │   ├── Receptionist.js
│       │   ├── Register.js
│       │   ├── Review_Management.js
│       │   ├── Schedule.js
│       │   ├── Schedule_Management.js
│       │   └── System.js
│       └── uploads/
│           ├── image_package/
│           │   ├── pkg_1_69bfa0ce4a3b0.jpg
│       │   │   ├── pkg_1_69bfa11032e87.jpg
│       │   │   ├── pkg_1_69bfa15c531c6.jpg
│       │   │   ├── pkg_2_69bfa0d54d722.jpg
│       │   │   └── pkg_3_69bfa0dc6608f.jpg
│           └── image_panel/
│               ├── ELITY_69e6776f49af6.png
│               ├── thiet-ke-phong-gym-10__1__69c12ec1e392a.jpg
│               ├── thiet-ke-phong-gym-10_69c12eb4c58f8.jpg
│               ├── thiet-ke-phong-gym-21_69c12ebca67fd.jpg
│               └── thiet-ke-phong-gym-9_69c12f04e6274.jpg
├── routes/
│   └── web.php
├── scripts/
│   ├── fix_js_quotes.php
│   ├── migrate_mvc_structure.php
│   ├── notification_cron.php
│   ├── patch_admin_js_vars.php
│   ├── patch_api_js.php
│   ├── patch_dom_ready.php
│   ├── patch_module_assets.php
│   ├── patch_modules.php
│   ├── patch_mvc_paths.php
│   ├── patch_sessions.php
│   └── patch_urls.php
├── storage/
│   ├── cache/
│   │   └── security/
│   └── logs/
│       ├── notification_cron.log
│       ├── smtp_debug.log
│       └── webhook_log.txt
└── vendor/
    └── PHPMailer/
        ├── Exception.php
        ├── PHPMailer.php
        └── SMTP.php
```
