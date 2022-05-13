<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    define('ALLOW_EXIT', false);

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
    $tokens = \Magistraal\Database\query("SELECT max(token_expires), token_id, user_uuid FROM magistraal_tokens GROUP BY user_uuid");

    $iso_start = date_iso(strtotime('today'));
    $iso_end   = date_iso(strtotime('today') + 86400 * 6);

    foreach ($tokens as $token) {
        if(!isset($token['token_id']) || empty($token['token_id']) || !isset($token['user_uuid']) || empty($token['user_uuid']) || $token['token_expires'] <= time()) {
            continue;
        }        
        // Start session without checking if the token has expired
        \Magister\Session::start($token['token_id'], false);

        echo('PASSED!');

        // Grab appointment ids, message ids and grade ids for this user_uuid
        $userdata = \Magistraal\Database\query("SELECT appointments, messages, grades FROM magistraal_userdata WHERE user_uuid=?", $token['user_uuid']);
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

        $changes = [
            'appointments' => \Magistraal\Cron\Appointments\find_changes($userdata['appointments'] ?? [], $iso_start, $iso_end),
            'grades'       => \Magistraal\Cron\Grades\find_changes($userdata['grades'] ?? []),
            'messages'     => \Magistraal\Cron\Messages\find_changes($userdata['messages'] ?? [])
        ];

        $new_userdata = [
            'appointments' => json_encode($changes['appointments']['new_entry']),
            'grades'       => json_encode($changes['grades']['new_entry']),
            'messages'     => json_encode($changes['messages']['new_entry'])
        ];

        \Magistraal\Database\query("REPLACE INTO magistraal_userdata (user_uuid, appointments, grades, messages) VALUES (?, ?, ?, ?)", [$token['user_uuid'], $new_userdata['appointments'], $new_userdata['grades'], $new_userdata['messages']]);
    }

    \Magistraal\Response\success(['count' => count($tokens)]);
?>