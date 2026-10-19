<?php

return [
    'name' => env('BUSINESS_NAME', 'Nilambara Electricals'),
    'address' => env('BUSINESS_ADDRESS'),
    'contact_number' => env('BUSINESS_CONTACT_NUMBER'),
    'logo' => env('BUSINESS_LOGO') ?: 'images/nilambara-electrical-logo.png',
    'customer_code_prefix' => env('BUSINESS_CUSTOMER_CODE_PREFIX', 'CUS-'),
    'quotation_number_prefix' => env('BUSINESS_QUOTATION_NUMBER_PREFIX', 'QUO-'),
    'invoice_number_prefix' => env('BUSINESS_INVOICE_NUMBER_PREFIX', 'INV-'),
    'payment_number_prefix' => env('BUSINESS_PAYMENT_NUMBER_PREFIX', 'PAY-'),
    'currency' => env('BUSINESS_CURRENCY', 'LKR'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Colombo'),
    'locales' => ['en' => 'English', 'si' => 'සිංහල'],
    'pagination' => (int) env('BUSINESS_PAGINATION', 25),
    'date_format' => env('BUSINESS_DATE_FORMAT', 'd M Y'),
    'time_format' => env('BUSINESS_TIME_FORMAT', 'h:i A'),
    'printing' => [
        'paper' => env('BUSINESS_PRINT_PAPER', 'A5'),
        'orientation' => env('BUSINESS_PRINT_ORIENTATION', 'portrait'),
        'show_logo' => true,
        'show_duplicate_label' => true,
    ],
];
