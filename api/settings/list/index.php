<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    \Magistraal\Response\success([
        'user_uuid' => \Magister\Session::$userUuid ?? null, 
        'items' => \Magistraal\Settings\get_all($_POST['category'] ?? null)
    ]);
?>