<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    $Magister->loginHeaderToken();

    \Magister\Response\success($Magister->subjects_list());
?>