<?php
/**
 * Mail configuration
 * Vị trí: app/config/mail.php
 *
 * HƯỚNG DẪN CẤU HÌNH GMAIL:
 * 1. Vào Google Account → Security → 2-Step Verification (bật nếu chưa có)
 * 2. Vào Google Account → Security → App passwords
 * 3. Chọn "Mail" + "Other" → đặt tên "Elite Gym" → Copy mật khẩu 16 ký tự
 * 4. Điền vào 'password' bên dưới (KHÔNG dùng mật khẩu Gmail thường)
 *
 * Ví dụ App Password: abcd efgh ijkl mnop → điền: 'abcdefghijklmnop'
 */

return [
    // ── SMTP Server ──────────────────────────────────────────
    'host'      => 'smtp.gmail.com',   // Gmail | 'smtp.office365.com' | 'smtp.brevo.com'
    'port'      => 587,                // 587 = STARTTLS (khuyến nghị) | 465 = SSL
    'username'  => 'pvhoang08082004@gmail.com',   // ← ĐỔI thành Gmail của gym
    'password'  => 'erxrnzkohmsezmsy',    // ← ĐỔI thành App Password 16 ký tự

    // ── Người gửi hiển thị ───────────────────────────────────
    'from'      => 'pvhoang08082004@gmail.com',   // ← Phải trùng với username Gmail
    'from_name' => 'Elite Fitness Gym',
];
