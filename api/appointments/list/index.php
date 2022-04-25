<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    $from = $_POST['from'] ?? date_iso(strtotime('-1 week'));
    $to   = $_POST['to']   ?? date_iso(strtotime($from) + 86400 * 6);

    \Magistraal\Response\success(\Magistraal\Appointments\get_all($from, $to));
?>