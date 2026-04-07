<?php

return [

    'image' => [

        'post_cover' => [

            'width' => 400,

            'height' => 568,

        ],

        'media' => [

            'vertical' => [

                'max_width' => 2560,

                'max_height' => 10240,

            ],

            'horizontal' => [

                'max_width' => 5120,

                'max_height' => 2560,

            ],

            'animation' => [

                'max_width' => 1920,

            ],

        ],

        'hosting' => [

            'max_width' => 1600,

            'max_height' => 6400,

        ],

    ],

    'video' => [

        'cover_position_percent' => 50,

        'shaka_binary' => env('SHAKA_PACKAGER_BINARY', 'shaka-packager'),

        'segment_duration' => 6,

        'use_nvenc' => env('VIDEO_USE_NVENC', false),

    ],

];
