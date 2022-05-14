<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");

    if(!isset($_POST['token'])) {
        \Magistraal\Response\error('parameter_token_mising');
    }

    if(!isset($_POST['user_uuid'])) {
        \Magistraal\Response\error('parameter_user_uuid_mising');
    }

    // Store the FCM messages token
    \Magistraal\Response\success(\Magistraal\Notifications\register_fcm_token(
        basename($_POST['user_uuid']), basename($_POST['token'])
    ));
?>