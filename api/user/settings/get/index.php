<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['setting'])) {
        \Magistraal\Response\error('parameter_setting_missing');
    }

    \Magistraal\Response\success(\Magistraal\User\Settings\get(\Magister\Session::$userUuid ?? $_COOKIE['magistraal-user-uuid'] ?? null, $_POST['setting']));
?>