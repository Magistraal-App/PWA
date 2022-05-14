<?php 
    namespace Magistraal\Cron\Messages;

    function find_changes($current_entry) {
        $new_entry = $current_entry;
        $new_items = [];

        // Load top 4 messages
        $messages = \Magistraal\messages\get_all(4);

        // Return if messages could not be loaded
        if(!isset($messages)) {
            return $new_items;
        }
        
        foreach ($messages as $message) {
            // Continue if message is invalid
            if(!isset($message['subject']) || !isset($message['id']) || !isset($message['sender']['name']) || !isset($message['read'])) {
                continue;
            }

            // Continue if user has already read the message
            if($message['read'] == true) {
                continue;
            }

            // Continue if this message was already discovered
            if(isset($current_entry[$message['id']])) {
                continue;
            }

            // Store the message since it was not yet discovered
            $new_items[] = $message;
            $new_entry[$message['id']] = true;
        }

        return [
            'new_entry' => array_slice($new_entry, -4, 4, true),
            'new_items' => $new_items
        ];
    }
?>