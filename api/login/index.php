<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    
    // Get tenant
    if(!isset($_POST['tenant']) || empty($_POST['tenant'])) {
        \Magistraal\Response\error('login_field_empty_tenant');
    }
    $tenant = trim($_POST['tenant']);

    // Get username
    if(!isset($_POST['username']) || empty($_POST['username'])) {
        \Magistraal\Response\error('login_field_empty_username');
    }
    $username = trim($_POST['username']);
    
    // Get password
    if(!isset($_POST['password']) || empty($_POST['password'])) {
        \Magistraal\Response\error('login_field_empty_password');
    }
    $password = trim($_POST['password']);

    $result = \Magister\Session::login($tenant, $username, $password);

    if($result['success'] === true) {
        header("X-Auth-Token: {$result['token_id']}");
        \Magistraal\Response\success();
    } else {
        \Magistraal\Response\error($result['info']);
    }
?>