<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    \Magistraal\Response\success(
        array_merge(\Magistraal\User\Settings\get_all(\Magister\Session::$userUuid), ['system.user_uuid' => \Magister\Session::$userUuid])
    );
?>