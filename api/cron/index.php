<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    define('ALLOW_EXIT', false);
    set_time_limit(100);

    $run_by = $_SERVER['HTTP_X_RUN_BY'] ?? 'N/A';
    file_put_contents('test.txt', '['.date('d-m-Y H:i:s').' - Triggered by flow ('.$run_by.')'."\n", FILE_APPEND);

    // Return if the last process is still active or if
    // it has been more than 1 hour since the current process started,
    // which probably means that an error occured
    $process = @json_decode(file_get_contents('status.json'), true) ?? [];

    if(isset($proces['status']) && isset($process['started'])) {
        if($process['status'] != 'done') {
            // The current process has not yet finished, check if it
            // started over an hour ago. If not, return.
            if(isset($proces['started']) && $process['started'] + 3600 < time()) {
                exit(json_encode(['process_still_active']));
            }
        }
    }

    // Get tokens per user_uuid
    $tokens = \Magistraal\Database\query("SELECT max(`token_expires`), `token_id`, `user_uuid`, `access_token_expires` FROM `magistraal_tokens` GROUP BY `user_uuid`");

    $iso_start = date_iso(strtotime('today'));
    $iso_end   = date_iso(strtotime($iso_start) + 86400);

    foreach ($tokens as $token_data) {
        if(!isset($token_data['token_id']) || empty($token_data['token_id']) || !isset($token_data['user_uuid']) || empty($token_data['user_uuid'])) {
            continue;
        }    

        // Create user data row only if it doesn't exist
        \Magistraal\Database\query("INSERT IGNORE INTO `magistraal_tokens` SET `user_uuid`=?", $token_data['user_uuid']);

        // Start session without checking if the token has expired
        if(!\Magister\Session::start($token_data['token_id'], false)) {
            // Failed to start session, continue
            continue;
        }

        // Grab appointment ids, message ids and grade ids for this user_uuid
        $userdata = \Magistraal\Database\query("SELECT `appointments`, `messages`, `grades` FROM `magistraal_userdata` WHERE `user_uuid`=?", $token_data['user_uuid']);
        
        // Set default if userdata was not found
        if(!isset($userdata) || !is_array($userdata) || empty($userdata)) {
            $userdata = [
                'appointments' => null,
                'messages' => null,
                'grades' => null
            ];
        } else {
            $userdata = array_values($userdata)[0];
        }

        // Decode userdata
        foreach ($userdata as &$entry) {
            $entry = @json_decode($entry, true) ?? [];;
        }

        // Find new appointments, grades and messages
        $changes = [
            'appointments' => \Magistraal\Cron\Appointments\find_changes($userdata['appointments'] ?? [], $iso_start, $iso_end),
            'grades'       => \Magistraal\Cron\Grades\find_changes($userdata['grades'] ?? []),
            'messages'     => \Magistraal\Cron\Messages\find_changes($userdata['messages'] ?? [])
        ];

        // Get new databse values
        $new_userdata = [
            'appointments' => json_encode($changes['appointments']['new_entry']),
            'grades'       => json_encode($changes['grades']['new_entry']),
            'messages'     => json_encode($changes['messages']['new_entry'])
        ];

        // Store the new values
        \Magistraal\Database\query("UPDATE `magistraal_userdata` SET `appointments`=?, `grades`=?, `messages`=? WHERE `user_uuid`=?", [$new_userdata['appointments'], $new_userdata['grades'], $new_userdata['messages'], $token_data['user_uuid']]);

        // Notify the user of any changes
        foreach ($changes as $category => $items) {
            foreach ($items['new_items'] as $change) {
                if($category == 'messages') {
                    $notification = [
                        'title' => sprintf('Nieuw bericht van %s', trim(strtok($change['sender']['name'], '('))), 
                        'body' => sprintf('Je hebt een bericht ontvangen van %s betreffende %s.', $change['sender']['name'], trim(ucfirst($change['subject'])))
                    ];
                } else if($category == 'appointments') {
                    $notification = [
                        'title' => sprintf('%de uur vervalt', $change['start']['lesson']),
                        'body' => sprintf('Vandaag vervalt het %de uur (%s).', $change['start']['lesson'], trim(implode(', ', $change['subjects'])))
                    ];
                } else if($category == 'grades') {
                    $notification = [
                        'title' => sprintf('%s gehaald voor %s', $change['value_str'], $change['subject']['description']),
                        'body' => sprintf('Je hebt een %s gehaald voor %s (%s).', $change['value_str'], trim($change['subject']['description']), trim($change['description']))
                    ];
                }

                if(!isset($notification)) {
                    continue;
                }

                // Create new notification
                $user_notification = new \Magistraal\Notifications\Notification($token_data['user_uuid']);

                // Setup noficiation
                $user_notification->notification = ['data' => $notification];

                // Send the notification
                $user_notification->send();
            }
        }
    }

    exit(json_encode(['count' => count($tokens)]));
?>