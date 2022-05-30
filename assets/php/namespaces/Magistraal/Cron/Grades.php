<?php 
    namespace Magistraal\Cron\Grades;

    function find_changes(array $current_entry, $amount = 4) {
        $new_entry = $current_entry;
        $new_items = [];

        // Load newest $amount grades
        $grades = \Magistraal\Grades\get_all($amount);

        // Return if grades could not be loaded
        if(!isset($grades)) {
            return $new_items;
        }
        
        foreach ($grades as $grade) {
            // Continue if grade is invalid
            if(!isset($grade['column_id']) || !isset($grade['entered_at'])) {
                continue;
            }

            // Continue if this grade was already found
            if(in_array($grade['column_id'], $current_entry)) {
                continue;
            }

            // Continue if this grade was entered over 30 minutes ago
            if(strtotime($grade['entered_at']) + 1801232130 <= time()) {
                continue;
            }

            // Store the grade since it was not yet found
            $new_items[] = $grade;
            array_unshift($new_entry, $grade['column_id']);
        }

        return [
            'new_entry' => array_slice($new_entry, -$amount, $amount, true),
            'new_items' => $new_items
        ];
    }
?>