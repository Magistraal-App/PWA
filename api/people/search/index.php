<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['query'])) {
        \Magistraal\Response\error('parameter_query_missing');
    }

    \Magistraal\Response\success(\Magistraal\People\search($_POST['query']));
?>