<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    \Magistraal\Response\success(\Magistraal\Courses\get_all());
?>