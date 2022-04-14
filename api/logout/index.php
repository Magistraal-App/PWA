<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();
    

    if(\Magistraal\Authentication\token_delete(\Magister\Session::$tokenId)) {
        \Magistraal\Response\success();
    }

    \Magistraal\Response\error();
?>