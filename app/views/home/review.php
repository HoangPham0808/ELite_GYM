<?php
/** Trang đánh giá khách hàng */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đánh giá — Elite Gym</title>
    <link rel="stylesheet" href="<?= asset('css/reviews_section.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/notification.css') ?>">
    <script>window.ELITE_BASE = '<?= BASE_URL ?>';</script>
</head>
<body style="background:#0a0a0f;color:#fff;padding:2rem;">
    <p><a href="<?= url() ?>" style="color:#d4a017;">← Về trang chủ</a></p>
    <?php include __DIR__ . '/partials/reviews_section.php'; ?>
    <?php include __DIR__ . '/partials/notification_ui.php'; ?>
</body>
</html>
