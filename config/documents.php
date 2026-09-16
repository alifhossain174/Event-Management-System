<?php

use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;

return [
    'disk' => env('DOCUMENT_DISK', 'local'),
    'allowed_disks' => ['local'],
    'max_kilobytes' => (int) env('DOCUMENT_MAX_KILOBYTES', 25600),
    'extensions' => [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'mp4' => ['video/mp4'],
        'mov' => ['video/quicktime'],
    ],
    'linkable_types' => [
        'company' => Company::class,
        'branch' => Branch::class,
        'client' => Client::class,
        'user' => User::class,
    ],
];
