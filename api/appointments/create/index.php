<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['start']) || empty($_POST['start'])) {
        \Magistraal\Response\error('appointment_field_empty.start');
    }

    if(!isset($_POST['end']) || empty($_POST['end'])) {
        \Magistraal\Response\error('appointment_field_empty.end');
    }

    if(strtotime($_POST['start']) > strtotime($_POST['end'])) {
        \Magistraal\Response\error('appointment_field_invalid.time');
    }

    if(!isset($_POST['designation']) || empty($_POST['designation'])) {
        \Magistraal\Response\error('appointment_field_empty.designation');
    }

    if(!isset($_POST['content']) || empty($_POST['content'])) {
        \Magistraal\Response\error('appointment_field_empty.content');
    }

    if(isset($_POST['id']) && empty($_POST['id'])) {
        unset($_POST['id']);
    }

    $result = \Magistraal\Appointments\create([
        'id'          => $_POST['id'] ?? 0,
        'start'       => $_POST['start'],
        'end'         => $_POST['end'],
        'designation' => $_POST['designation'] ?? '',
        'facility'    => $_POST['facility'] ?? '',
        'content'     => $_POST['content'] ?? ''
    ]);

    if($result['success'] == true) {
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