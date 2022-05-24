<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    \Magistraal\Response\success(\Magistraal\Messages\get_all($_POST['top'] ?? null, $_POST['skip'] ?? null, $_POST['folder'] ?? 'inbox', $_POST['filter'] ?? null));
?>