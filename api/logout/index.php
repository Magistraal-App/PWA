<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    
    if(isset($_COOKIE['magistraal-authorization'])) {
        \Magistraal\Authentication\token_delete($_COOKIE['magistraal-authorization']);
    }

    \Magistraal\Response\success();
?>