<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    $from = $_POST['from'] ?? strtotime('-2 years');
    $to   = $_POST['to']   ?? strtotime('+2 years');

    \Magistraal\Response\success(\Magistraal\Absences\get_all($from, $to));
?>