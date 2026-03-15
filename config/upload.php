<?php

return [
    'image' => [

        'allowed_extensions' => ['jpg', 'png', 'gif', 'mp4'],
        'max_size' => 20 * 1024 * 1024, // 20MB

    ],

    'video' => [

        'allowed_extensions' => ['mp4', 'webm', 'mkv'],
        'max_size' => 20 * 1024 * 1024 * 1024, // 20GB

    ],

    'audio' => [

        'allowed_extensions' => ['mp3', 'wav', 'flac', 'aac'],
        'max_size' => 20 * 1024 * 1024 * 1024, // 20GB

    ],

    'attachment' => [

        'allowed_extensions' => ['7z', 'zip', 'rar', 'apk', 'torrent'],
        'max_size' => 20 * 1024 * 1024 * 1024, // 20GB

    ],

];
