<?php 
    namespace Magistraal\Notifications;

    function update($token_id) {
        \Magistraal\Appointments\find_changes($_COOKIE['magistraal-authorization']);
    }
?>