<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['setting'])) {
        \Magistraal\Response\error('parameter_setting_missing');
    }

    if(!isset(\Magister\Session::$userUuid)) {
        \Magistraal\Response\error('error_authentication');
    }

    \Magistraal\Response\success(\Magistraal\User\Settings\get(\Magister\Session::$userUuid, $_POST['setting']));
?>