<?php

return [
    // Đúng ba thông số của một jail fail2ban.
    // 5 lần sai trong 10 phút thì khóa IP đó 15 phút.
    'maxretry' => (int) env('FAIL2BAN_MAXRETRY', 5),
    'findtime' => (int) env('FAIL2BAN_FINDTIME', 600),
    'bantime' => (int) env('FAIL2BAN_BANTIME', 900),
    'ignoreip' => ['127.0.0.1', '::1'],
];
