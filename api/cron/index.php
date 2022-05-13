<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");

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
    $tokens = \Magistraal\Database\query("SELECT token_expires, token_id, user_uuid FROM magistraal_tokens GROUP BY user_uuid");

    $iso_start = date_iso(strtotime('today'));
    $iso_end   = date_iso(strtotime('today') + 86400 * 6);

    foreach ($tokens as $token) {
        if(!isset($token['token_id']) || empty($token['token_id']) || !isset($token['user_uuid']) || empty($token['user_uuid'])) {
            continue;
        }
        
        // Start session
        \Magister\Session::start($token['token_id']);

        // Grab appointment ids, message ids and grade ids for this user_uuid
        $userdata = array_values(\Magistraal\Database\query("SELECT appointments, messages, grades FROM magistraal_userdata WHERE user_uuid=?", $token['user_uuid']))[0];

        // Continue if userdata was not found
        if(!$userdata) {
            continue;
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

        \Magistraal\Database\query("UPDATE magistraal_userdata SET appointments=?, grades=?, messages=? WHERE user_uuid=?", [$new_userdata['appointments'], $new_userdata['grades'], $new_userdata['messages'], $token['user_uuid']]);
    
        \Magistraal\Response\success($changes);
    }
?>