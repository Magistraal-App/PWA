<?php 
    namespace Magistraal\Cron\Appointments;

    function find_changes(array $current_entry, string $iso_from, string $iso_to) {
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
                    
                    // Continue if the appointment was already discovered as canceled
                    if(isset($current_entry[$appointment['id']]) && $current_entry[$appointment['id']] == 'canceled') {
                        continue;
                    }

                    // Store the appointment since it was not yet discovered
                    $new_items[] = $appointment;
                    $new_entry[$appointment['id']] = $appointment['status'];
                }
            }
        }
    
        return [
            'new_entry' => array_slice($new_entry, -24, 24, true),
            'new_items' => $new_items
        ];
    }
?>