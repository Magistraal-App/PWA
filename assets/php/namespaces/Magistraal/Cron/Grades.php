<?php 
    namespace Magistraal\Cron\Grades;

    function find_changes(array $current_entry) {
        $new_entry = $current_entry;
        $new_items = [];

        // Load top 2 grades
        $grades = \Magistraal\Grades\get_all(2);

        // Return if grades could not be loaded
        if(!isset($grades)) {
            return $new_items;
        }
        
        foreach ($grades as $grade) {
            // Continue if grade is invalid
            if(!isset($grade['column_id'])) {
                continue;
            }

            // Continue if this grade was already discovered
            if(isset($current_entry[$grade['column_id']])) {
                continue;
            }

            // Store the grade since it was not yet discovered
            $new_items[] = $grade;
            $new_entry[$grade['column_id']] = true;
        }

        return [
            'new_entry' => array_slice($new_entry, -2, 2, true),
            'new_items' => $new_items
        ];
    }
?>