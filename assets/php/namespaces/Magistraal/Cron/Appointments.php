<?php 
    namespace Magistraal\Cron\Appointments;

    function find_changes(array $current_entry, string $iso_from, string $iso_to, $amount = 24) {
        $new_entry = $current_entry;
        $new_items = [];

        // Load appointments for today and tomorrow
        $appointments = \Magistraal\Appointments\get_all($iso_from, $iso_to) ?? [];
        
        if(isset($appointments)) {
            foreach ($appointments as $day) {
                foreach ($day['items'] as $appointment) {
                    // Continue if appointment is invalid or if it is not canceled
                    if(!isset($appointment['id']) || !isset($appointment['status']) || $appointment['status'] != 'canceled') {
                        continue;
                    }
                    
                    // Continue if the appointment was already found
                    if(in_array($appointment['id'], $current_entry)) {
                        continue;
                    }

                    // Continue if the appointment has already started
                    if(strtotime($appointment['start']['time']) >= time()) {
                        continue;
                    }

                    // Store the appointment since it was not yet found
                    $new_items[] = $appointment;
                    array_unshift($new_entry, $appointment['id']);
                }
            }
        }
    
        return [
            'new_entry' => array_slice($new_entry, -$amount, $amount, true),
            'new_items' => $new_items
        ];
    }
?>