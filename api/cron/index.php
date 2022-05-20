<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    define('ALLOW_EXIT', false);
    set_time_limit(7200);

    if((!isset($_SERVER['HTTP_X_CRON_RUNNER_TOKEN']) || $_SERVER['HTTP_X_CRON_RUNNER_TOKEN'] != \Magistraal\Config\get('cron_runner_token')) && \Magistraal\Config\get('debugging') !== true) {
        exit();
    }

    if((!isset($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR'] != \Magistraal\Config\get('cron_runner_ip')) && \Magistraal\Config\get('debugging') !== true) {
        exit();
    }

    echo("running\n");

    $microtime_start = microtime(true);

    // Get tokens per user_uuid
    $tokens = \Magistraal\Database\query("SELECT max(`token_expires`), `token_id`, `user_uuid`, `access_token_expires` FROM `magistraal_tokens` GROUP BY `user_uuid`");

    $iso_from = date_iso(strtotime('today'));               // Start of today
    $iso_to   = date_iso(strtotime($iso_from) + 86400 * 2); // Start of the day after tomorrow

    foreach ($tokens as $token_data) {
        if(!isset($token_data['token_id']) || empty($token_data['token_id']) || !isset($token_data['user_uuid']) || empty($token_data['user_uuid'])) {
            continue;
        }

        $timestamp1 = \Magistraal\Debug\get_timestamp();

        $timestamp2 = \Magistraal\Debug\get_timestamp();
        // Start session without checking if the token has expired
        if(!\Magister\Session::start($token_data['token_id'], false)) {
            // Failed to start session, delete token and continue
            \Magistraal\Authentication\token_delete($token_data['token_id']);
            continue;
        }

        $timestamp3 = \Magistraal\Debug\get_timestamp();

        // Grab appointment ids, message ids and grade ids for this user_uuid
        $notification_data = \Magistraal\Database\query("SELECT `notification_data` FROM `magistraal_userdata` WHERE `user_uuid`=?", [$token_data['user_uuid']])[0]['notification_data'] ?? null;
        
        $timestamp4 = \Magistraal\Debug\get_timestamp();


        // Decode notification data
        $notification_data = json_decode($notification_data, true) ?? ['appointments' => [], 'grades' => [], 'messages' => []];

        $timestamp5 = \Magistraal\Debug\get_timestamp();

        // Search for new appointments, grades and messages
        $changes = [
            'appointments' => \Magistraal\Cron\Appointments\find_changes($notification_data['appointments'] ?? [], $iso_from, $iso_to),
            'grades'       => \Magistraal\Cron\Grades\find_changes($notification_data['grades'] ?? []),
            'messages'     => \Magistraal\Cron\Messages\find_changes($notification_data['messages'] ?? [])
        ];

        // Get new notication data
        $new_notification_data = json_encode([
            'appointments' => $changes['appointments']['new_entry'],
            'grades'       => $changes['grades']['new_entry'],
            'messages'     => $changes['messages']['new_entry']
        ]);

        $timestamp6 = \Magistraal\Debug\get_timestamp();

        // Store the new notification data
        \Magistraal\Database\query("UPDATE `magistraal_userdata` SET `notification_data`=? WHERE `user_uuid`=?", [$new_notification_data, $token_data['user_uuid']]);

        $timestamp7 = \Magistraal\Debug\get_timestamp();

        // Notify the user of any changes
        foreach ($changes as $category => $items) {
            foreach ($items['new_items'] as $change) {
                if($category == 'messages') {
                    $fcm_notification = [
                        'title' => sprintf('Nieuw bericht van %s', trim(strtok($change['sender']['name'], '('))), 
                        'body' => sprintf('Je hebt een bericht ontvangen van %s betreffende %s.', $change['sender']['name'], trim(ucfirst($change['subject'])))
                    ];
                } else if($category == 'appointments') {
                    $fcm_notification = [
                        'title' => sprintf('%de uur vervalt', $change['start']['lesson']),
                        'body' => sprintf('%s vervalt het %de uur (%s).', (strtotime(date('d-m-Y', strtotime($change['start']['time']))) - time() <= 86400 ? 'Vandaag' : 'Morgen'), $change['start']['lesson'], trim(implode(', ', $change['subjects'])))
                    ];
                } else if($category == 'grades') {
                    $fcm_notification = [
                        'title' => sprintf('%s gehaald voor %s', $change['value_str'], $change['subject']['description']),
                        'body' => sprintf('Je hebt een %s gehaald voor %s (%s).', $change['value_str'], trim($change['subject']['description']), trim($change['description']))
                    ];
                }

                if(!isset($fcm_notification)) {
                    continue;
                }

                // Create new notification
                $notification = new \Magistraal\Notifications\Notification($token_data['user_uuid']);

                // Setup noficiation
                $notification->notification = $fcm_notification;

                // Send the notification
                $notification->send();
            }
        }

        $timestamp8 = \Magistraal\Debug\get_timestamp();

        
        if(\Magistraal\Config\get('debugging') === true) { 
            \Magistraal\Debug\print_heading('NEW REPORT', 0, 2);
            \Magistraal\Debug\print_heading('Information', 1, 1);
            \Magistraal\Debug\print_value('Token expires at', 1, date_iso($token_data['token_expires']));
            \Magistraal\Debug\print_value('Tenant', 1, str_replace(['https://', 'http://', '.magister.net', '/'], '', \Magister\Session::$domain));
            \Magistraal\Debug\print_value('User', 1, substr(\Magister\Session::$userUuid, 0, 8));
            \Magistraal\Debug\print_heading('Timings', 1, 1);
            \Magistraal\Debug\print_timing('Creating userdata row', 1, $timestamp1, $timestamp2, $timestamp1, $timestamp8);
            \Magistraal\Debug\print_timing('Starting session', 1, $timestamp2, $timestamp3, $timestamp1, $timestamp8);
            \Magistraal\Debug\print_timing('Getting userdata', 1, $timestamp3, $timestamp4, $timestamp1, $timestamp8);
            \Magistraal\Debug\print_timing('Parsing userdata', 1, $timestamp4, $timestamp5, $timestamp1, $timestamp8);
            \Magistraal\Debug\print_timing('Finding changes', 1, $timestamp5, $timestamp6, $timestamp1, $timestamp8);
            \Magistraal\Debug\print_timing('Storing changes', 1, $timestamp6, $timestamp7, $timestamp1, $timestamp8);
            \Magistraal\Debug\print_timing('Sending notifications', 1, $timestamp7, $timestamp8, $timestamp1, $timestamp8);
            \Magistraal\Debug\print_timing('Total', 1, $timestamp1, $timestamp8, $timestamp1, $timestamp8);
            \Magistraal\Debug\print_heading('Results', 1, 1);
            \Magistraal\Debug\print_value('Canceled appointments', 1, count($changes['appointments']['new_items']));
            \Magistraal\Debug\print_value('Grades', 1, count($changes['grades']['new_items']));
            \Magistraal\Debug\print_value('Messages', 1, count($changes['messages']['new_items']));
            echo("\n\n\n");
        }
    }

    // Update status
    \Magistraal\Status\set('notifications.last_update.at', date_iso());
    \Magistraal\Status\set('notifications.last_update.duration', round(microtime(true) - $microtime_start, 2));
    \Magistraal\Status\set('notifications.new_appointments_count', 0);
    \Magistraal\Status\set('notifications.new_grades_count', 0);
    \Magistraal\Status\set('notifications.new_messages_count', 0);

    echo('success');
?>