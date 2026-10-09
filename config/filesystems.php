<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        // Database backup dumps — deliberately not `serve`-able like the disk
        // above; a dump contains everything in the database, so it's only
        // ever reached through the admin-gated Backups controller.
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'throw' => false,
        ],

        // An optional second, physically separate copy of every backup —
        // protects against the primary drive itself failing, which a
        // single-disk backup can't. Deliberately has no built-in default:
        // what a "second location" even means is different on every
        // machine this app runs on (a second internal drive on one PC, an
        // external drive or mounted network share on another, nothing at
        // all on a single-disk server) — see BACKUP_SECOND_DRIVE_PATH in
        // .env.example. config/backup.php only wires this disk in when it's
        // actually set, so an unconfigured system just runs the one-disk
        // backup and never touches this. 'throw' stays false so a
        // temporarily missing drive doesn't error out, it's just skipped
        // (continue_on_failure in config/backup.php keeps the primary copy
        // succeeding regardless).
        'backups_secondary' => [
            'driver' => 'local',
            'root' => env('BACKUP_SECOND_DRIVE_PATH'),
            'throw' => false,
        ],

        // Uploaded bank transfer slips (see BankQrPaymentController::scanSlip
        // / ::slip()) — deliberately not `serve`-able, same reasoning as
        // 'backups': a slip can show real account/transaction details, so
        // it's only ever reached through the admin/manager-gated controller.
        'bank-slips' => [
            'driver' => 'local',
            'root' => storage_path('app/bank-slips'),
            'throw' => false,
        ],

    ],

];
