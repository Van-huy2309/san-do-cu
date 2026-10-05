<?php

return [
    // Một người đang xem chợ và chat (poll 3 giây) nằm dưới các mức này.
    // Vượt mức là gửi dồn, không phải thao tác tay.
    'account' => ['max' => 240, 'seconds' => 60],
    'account_burst' => ['max' => 50, 'seconds' => 10],
    'guest' => ['max' => 300, 'seconds' => 60],
    'guest_burst' => ['max' => 60, 'seconds' => 10],
];
