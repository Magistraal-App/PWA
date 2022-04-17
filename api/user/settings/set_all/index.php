<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['settings'])) {
        \Magistraal\Response\error('parameter_settings_missing');
    }

    if(!isset(\Magister\Session::$userUuid)) {
        \Magistraal\Response\error('error_authentication');
    }

    \Magistraal\User\Settings\set_all(\Magister\Session::$userUuid, $_POST['settings']);

    \Magistraal\Response\success(\Magistraal\User\Settings\get_all(\Magister\Session::$userUuid));
?>