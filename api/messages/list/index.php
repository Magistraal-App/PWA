<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    $top  = $_GET['top']  ?? 1000;
    $skip = $_GET['skip'] ?? 0;

    \Magistraal\Response\success(\Magistraal\Messages\get_all($top, $skip));
?>