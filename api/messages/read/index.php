<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['id'])) {
        \Magistraal\Response\error('parameter_id_mising');
    }

    $id   = $_POST['id'];
    $read = $_POST['read'] ?? null;

    if(\Magistraal\Messages\mark_read($id, $read)) {
        \Magistraal\Response\success();
    } else {
        \Magistraal\Response\error('error_generic');
    }
?>