<?php

return [
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
    ],

    'pages' => [
        'ensure_pages_exist' => true,
        'paths' => [
            resource_path('js/react/Pages'),
        ],
        'extensions' => [
            'js',
            'jsx',
            'ts',
            'tsx',
        ],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],
];
