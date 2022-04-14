<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['designation'])) {
        \Magistraal\Response\error('parameter_designation_mising');
    }

    \Magistraal\Response\success(\Magistraal\Appointments\create($_POST));
?>