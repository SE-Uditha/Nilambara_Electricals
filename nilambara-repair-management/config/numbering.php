<?php

return [
    'repair_jobs' => [
        // The database ID and manually entered job number are separate values.
        // This policy records the BRD ambiguity; it does not generate numbers.
        'mode' => 'manual_default',

        // Automatic display numbers require an approved numbering policy and
        // an eventual transaction-safe sequence allocator before activation.
        'automatic_display_enabled' => false,
        'prefix' => 'JOB-',
        'padding' => 5,
    ],
];
