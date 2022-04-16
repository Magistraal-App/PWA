<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['setting'])) {
        \Magistraal\Response\error('parameter_setting_missing');
    }

    if(!isset($_POST['value'])) {
        \Magistraal\Response\error('parameter_value_missing');
    }

    \Magistraal\User\Settings\set(\Magister\Session::$userUuid ?? $_COOKIE['magistraal-user-uuid'] ?? null, $_POST['setting'], $_POST['value']);

    \Magistraal\Response\success(\Magistraal\User\Settings\get_all());
?>