<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['designation']) || empty($_POST['designation'])) {
        \Magistraal\Response\error('appointment_field_empty.designation');
    }

    if(!isset($_POST['content']) || empty($_POST['content'])) {
        \Magistraal\Response\error('appointment_field_empty.content');
    }

    $result = \Magistraal\Appointments\create($_POST);
    if($result['success'] == true) {
        sleep(1); // Sleep one second
        \Magistraal\Response\success();
    } else {
        if(isset($result['data']['Message'])) {
            $error = $result['data']['Message'];

            if(stripos($error, 'Begin') !== false || stripos($error, 'Einde') !== false) {
                \Magistraal\Response\error('appointment_field_empty.date');
            }
        }
    }
?>