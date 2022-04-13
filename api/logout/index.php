<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();
    

    file_put_contents(ROOT."/log.txt", "[SIGNOUT] Deleting login token {$token_id}.\n", FILE_APPEND);
    if(\Magistraal\Authentication\token_delete(\Magister\Session::$tokenId)) {
        \Magistraal\Response\success();
    }

    \Magistraal\Response\error();
?>