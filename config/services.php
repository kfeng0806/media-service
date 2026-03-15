<?php

return [

    'tus' => [

        'endpoint' => env('TUS_SERVER_ENDPOINT', 'http://localhost/tus'),
        'upload_directory' => env('TUS_UPLOAD_DIRECTORY', 'tmp/tus/uploads'),

    ],

];
