<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");

    if(!isset($_POST['query'])) {
        \Magistraal\Response\error('parameter_query_missing');
    }

    \Magistraal\Response\success(\Magistraal\Tenants\search($_POST['query']));
?>