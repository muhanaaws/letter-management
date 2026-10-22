<?php

return [
    // Signature layout per letter type, keyed by letter_types.code, with rows of left/center/right positions.
    'layouts' => [
        // Letters with 1 signer (Kaprodi or academic advisor, right-aligned)
        'STN' => [
            ['right'],
        ],
        'SRM' => [
            ['right'],
        ],
        'SRP' => [
            ['right'],
        ],
        'FD' => [
            ['right'],
        ],

        // Letters with 2 signers (academic advisor left, Kaprodi right)
        'TS' => [
            ['left', 'right'],
        ],

        // Letters with 3 signers (advisor left, supervisor center, Kaprodi right)
        'TD' => [
            ['left', 'center', 'right'],
        ],
    ],

    // Default layout by signer count, used when the letter type code is not defined in 'layouts'.
    'default' => [
        1 => [
            ['right'],
        ],
        2 => [
            ['left', 'right'],
        ],
        3 => [
            ['left', 'center', 'right'],
        ],
    ],
];
