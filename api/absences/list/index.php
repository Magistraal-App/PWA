<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    $from = $_POST['from'] ?? date_iso(strtotime('01-08-'.(date('Y')-1))); // 1 augustus vorig jaar
    $to   = $_POST['to']   ?? date_iso(strtotime('31-07-'.date('Y')));     // 31 juli dit jaar

    \Magistraal\Response\success(\Magistraal\Absences\get_all($from, $to));
?>