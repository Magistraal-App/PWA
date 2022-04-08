<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");

    \Magistraal\Response\success(\Magistraal\Tenants\get_all());
?>