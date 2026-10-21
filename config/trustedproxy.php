<?php

return [
    // The UAT server binds to loopback; only its local domain proxy is trusted.
    'proxies' => env('APP_ENV') === 'uat' ? ['127.0.0.1', '::1'] : [],
];
