<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    $top = $_POST['top'] ?? null;

    \Magistraal\Response\success(\Magistraal\Grades\get_all($top));
?>