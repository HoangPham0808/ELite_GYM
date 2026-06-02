# ELITE_GYM

```
ELITE_GYM/
├── app/
│   ├── config/
│   │   ├── config.php
│   │   └── database.php
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
│   │   └── Router.php
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
│   │   ├── Invoice.php
│   │   ├── Package.php
│   │   ├── Payment.php
│   │   ├── Promotion.php
│   │   ├── QRCheckin.php
│   │   ├── Review.php
│   │   ├── Schedule.php
│   │   ├── Statistics.php
│   │   └── SystemSetting.php
│   ├── modules/
│   │   ├── admin/
│   │   │   ├── Account_Management/
│   │   │   │   ├── Account_Management.php
│   │   │   │   ├── Account_Management.js
│   │   │   │   └── Account_Management.css
│   │   │   ├── Customer_Management/
│   │   │   │   ├── Customer_Management.php
│   │   │   │   ├── Customer_Management.js
│   │   │   │   ├── Customer_Management.css
│   │   │   │   └── QZ_Check/
│   │   │   │       ├── QR_CheckIn_function.php
│   │   │   │       └── QR_CheckIn.js
│   │   │   ├── Employee_Management/
│   │   │   │   ├── Employee_Management.php
│   │   │   │   ├── Employee_Management.js
│   │   │   │   ├── Employee_Management.css
│   │   │   │   └── Employee_attendance_tracking/
│   │   │   │       ├── Employee_attendance_tracking.php
│   │   │   │       └── Employee_attendance_tracking.js
│   │   │   ├── Facilities_Management/
│   │   │   │   ├── Facilities_Management.php
│   │   │   │   ├── Facilities_Management.js
│   │   │   │   └── Facilities_Management.css
│   │   │   ├── Gym_Management/
│   │   │   │   ├── Gym_Management.php
│   │   │   │   ├── Gym_Management.js
│   │   │   │   └── Gym_Management.css
│   │   │   ├── Invoice_Management/
│   │   │   │   ├── Invoice_Management.php
│   │   │   │   ├── Invoice_Management.js
│   │   │   │   └── Invoice_Management.css
│   │   │   ├── Management_statistics/
│   │   │   │   ├── management_statistics.php
│   │   │   │   ├── management_statistics.js
│   │   │   │   └── management_statistics.css
│   │   │   ├── overview/
│   │   │   │   ├── overview.php
│   │   │   │   ├── overview.js
│   │   │   │   └── overview.css
│   │   │   ├── Package_Management/
│   │   │   │   ├── Package_Management.php
│   │   │   │   ├── Package_Management.js
│   │   │   │   └── Package_Management.css
│   │   │   ├── Profile/
│   │   │   │   ├── Profile.php
│   │   │   │   ├── Profile.js
│   │   │   │   └── Profile.css
│   │   │   ├── Promotion_Management/
│   │   │   │   ├── Promotion_Management.php
│   │   │   │   ├── Promotion_Management.js
│   │   │   │   └── Promotion_Management.css
│   │   │   ├── Review_Management/
│   │   │   │   ├── Review_Management.php
│   │   │   │   ├── Review_Management.js
│   │   │   │   └── Review_Management.css
│   │   │   ├── Schedule_Management/
│   │   │   │   ├── Schedule_Management.php
│   │   │   │   ├── Schedule_Management.js
│   │   │   │   └── Schedule_Management.css
│   │   │   └── Setting/
│   │   │       ├── GPS/
│   │   │       │   ├── GPS.php
│   │   │       │   ├── GPS.js
│   │   │       │   └── GPS.css
│   │   │       ├── Image_landing/
│   │   │       │   ├── Image_landing.php
│   │   │       │   ├── Image_landing.js
│   │   │       │   └── Image_landing.css
│   │   │       └── System/
│   │   │           ├── System.php
│   │   │           ├── System.js
│   │   │           └── System.css
│   │   ├── admin_shell/
│   │   │   ├── adm.php
│   │   │   ├── adm.js
│   │   │   └── adm.css
│   │   ├── api/
│   │   │   ├── review_handler.php
│   │   │   ├── Review_Management_function.php
│   │   │   ├── notification_handler.php
│   │   │   ├── notification_auto.php
│   │   │   └── notification_ui.php
│   │   ├── auth/
│   │   │   ├── Login/
│   │   │   ├── Register/
│   │   │   └── Forgot_Password/
│   │   ├── home/
│   │   │   ├── Payment/
│   │   │   ├── Profile/
│   │   │   ├── Review/
│   │   │   ├── Schedule/
│   │   │   └── index.php
│   │   └── staff/
│   │       ├── HLV/
│   │       └── Receptionist/
│   └── views/
│       └── layouts/
│           ├── footer.php
│           ├── header.php
│           ├── navbar.php
│           └── sidebar.php
├── public/
│   ├── assets/
│   │   ├── css/
│   │   │   ├── adm.css
│   │   │   ├── Login.css
│   │   │   ├── Landing.css
│   │   │   ├── Review_Management.css
│   │   │   └── ...
│   │   ├── js/
│   │   │   ├── adm.js
│   │   │   ├── legacy-ready.js
│   │   │   ├── Login.js
│   │   │   ├── Landing.js
│   │   │   ├── Review_Management.js
│   │   │   ├── ai_stream.js
│   │   │   └── ...
│   │   ├── images/
│   │   │   └── ELITY.png
│   │   └── uploads/
│   │       ├── image_package/
│   │       └── image_panel/
│   ├── index.php
│   └── .htaccess
├── routes/
│   └── web.php
├── Database/
│   └── db.php
├── Internal/
│   ├── Index/
│   ├── Layout/
│   ├── Auth/
│   ├── Admin/
│   └── ...
├── Home/
│   ├── index.php
│   ├── Landing.css
│   ├── Landing.js
│   ├── Payment/
│   ├── Profile/
│   ├── Review/
│   └── Schedule/
├── PHPMailer/
│   ├── Exception.php
│   ├── PHPMailer.php
│   └── SMTP.php
├── gym-ai/
├── QLY_NV/
├── storage/
│   ├── logs/
│   └── cache/
├── upload/
│   ├── image_package/
│   └── image_panel/
├── vendor/
├── scripts/
├── README.md
├── SETUP_DATABASE.sql
├── .gitattributes
├── index.php
├── start
└── stop
```

