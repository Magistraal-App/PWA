<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['id'])) {
        \Magistraal\Response\error('parameter_id_mising');
    }

    if(\Magistraal\Appointments\finish($_POST['id'], strtobool($_POST['finished'] ?? true))) {
        \Magistraal\Response\success();
    } else {
        \Magistraal\Response\error('error_generic');
    }
?>