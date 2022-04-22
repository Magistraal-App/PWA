<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    $parent_id = $_POST['parent_id'] ?? null;

    \Magistraal\Response\success(\Magistraal\Sources\get_all($parent_id));
?>