<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    
    // Get tenant
    if(!isset($_POST['tenant']) || empty($_POST['tenant']) || strlen($_POST['tenant']) < 16) {
        \Magistraal\Response\error('login_field_empty.tenant');
    }
    $tenant = trim($_POST['tenant']);

    // Get username
    if(!isset($_POST['username']) || empty($_POST['username'])) {
        \Magistraal\Response\error('login_field_empty.username');
    }
    $username = trim($_POST['username']);
    
    // Get password
    if(!isset($_POST['password']) || empty($_POST['password'])) {
        \Magistraal\Response\error('login_field_empty.password');
    }
    $password = trim($_POST['password']);

    $result = \Magister\Session::login($tenant, $username, $password);

    if($result['success'] === true && isset(\Magister\Session::$userUuid)) {
        setcookie('magistraal-authorization', $result['token_id'], time()+365*24*60*60, '/magistraal/');
        
        // Create userdata row in database if it doesn't exist already
        \Magistraal\Database\query("INSERT IGNORE INTO `magistraal_userdata` SET `user_uuid`=?", \Magister\Session::$userUuid);
        
        \Magistraal\Response\success(['user_uuid' => $result['user_uuid']]);
    } else {
        \Magistraal\Response\error($result['info']);
    }
?>