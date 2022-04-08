<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['id'])) {
        \Magistraal\Response\error('parameter_id_mising');
    }
    $id       = $_POST['id'];
    
    $finished = strtobool($_POST['finished'] ?? true);

    if(\Magistraal\Appointments\finish($id, $finished)) {
        \Magistraal\Response\success();
    } else {
        \Magistraal\Response\error('error_generic');
    }
?>