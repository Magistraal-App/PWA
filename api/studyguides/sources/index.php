<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['id'])) {
        \Magistraal\Response\error('parameter_id_mising');
    }

    if(!isset($_POST['detail_id'])) {
        \Magistraal\Response\error('parameter_detail_id_mising');
    }
    
    \Magistraal\Response\success(\Magistraal\Studyguides\get_sources($_POST['id'], $_POST['detail_id']));
?>