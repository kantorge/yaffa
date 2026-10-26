<?php

return [

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'serve' => true,
            'throw' => false,
            'report' => false,
            'permissions' => [
                'file' => [
                    'public' => 0664,
                    'private' => 0660,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0770,
                ],
            ],
        ],
    ],

];
