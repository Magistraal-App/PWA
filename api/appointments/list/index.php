<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    $from = $_POST['from'] ?? strtotime('today') - 86400 * 4;
    $to   = $_POST['to']   ?? $from + 86400 * 6;

    \Magistraal\Response\success(\Magistraal\Appointments\get_all($from, $to));
?>