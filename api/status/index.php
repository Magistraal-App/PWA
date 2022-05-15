<?php 
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");

    $status = [
        'version'  => \Magistraal\Config\get('version') ?? null,
        'database' => [
            'latency_us' => \Magistraal\Database\latency_us() ?? null
        ],
        'notifications' => [
            'last_update' => [
                'at' => \Magistraal\Status\get('notifications.last_update.at') ?? null,
                'duration' => \Magistraal\Status\get('notifications.last_update.duration') ?? null,
            ],
            'new_appointments' => [
                'count' => \Magistraal\Status\get('notifications.new_appointments_count') ?? null
            ],
            'new_grades' => [
                'count' => \Magistraal\Status\get('notifications.new_grades_count') ?? null
            ],
            'new_messages' => [
                'count' => \Magistraal\Status\get('notifications.new_messages_count') ?? null
            ]
        ]
    ];
    exit(json_encode($status));
?>