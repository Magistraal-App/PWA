<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    \Magistraal\Response\success(\Magistraal\Tasks\get_all($_POST['from'] ?? null, $_POST['to'] ?? null, $_POST['skip'] ?? null, $_POST['top'] ?? null, $_POST['filter'] ?? null));
?>