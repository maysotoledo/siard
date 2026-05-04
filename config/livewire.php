<?php

return [
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => [
            'required',
            'file',
            'mimes:pdf,txt,log,csv,json,html,htm,zip',
            'max:153600',
        ],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
            'pdf',
        ],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],
];
