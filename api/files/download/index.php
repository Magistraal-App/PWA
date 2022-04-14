<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['location'])) {
        \Magistraal\Response\error('parameter_location_mising');
    }
    
    \Magistraal\Response\success(\Magister\Session::fileLocationGet($_POST['location']));
?>