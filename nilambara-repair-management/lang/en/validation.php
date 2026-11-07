<?php

return [
    'phone_number' => 'The :attribute must contain 7 to 15 digits, with an optional leading + and spaces, dots, parentheses or hyphens.',
    'decimal_value' => 'The :attribute must be a decimal number with no more than :places decimal places.',
    'decimal_range' => 'The :attribute is too large for this field.',
    'custom' => [
        'username' => [
            'regex' => 'The username may contain only English letters, numbers, periods, hyphens and underscores; no spaces.',
        ],
    ],
];
