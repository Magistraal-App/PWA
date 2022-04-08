<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    $from = $_POST['from'] ?? time();
    $to   = $_POST['to']   ?? time() + 86400 * 6;

    \Magistraal\Response\success(\Magistraal\Appointments\get_all($from, $to));
?>