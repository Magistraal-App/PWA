<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    \Magistraal\Response\success(\Magistraal\User\Settings\get_all(\Magister\Session::$userUuid ?? $_COOKIE['magistraal-user-uuid'] ?? null));
?>