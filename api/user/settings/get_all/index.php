<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset(\Magister\Session::$userUuid)) {
        \Magistraal\Response\error('error_authentication');
    }

    \Magistraal\Response\success(\Magistraal\User\Settings\get_all(\Magister\Session::$userUuid));
?>