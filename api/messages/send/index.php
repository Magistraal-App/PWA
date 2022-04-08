<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    $Magister->loginHeaderToken();

    if(!isset($_POST['to']) || empty($_POST['to'])) {
        \Magistraal\Response\error('parameter_to_missing.');
    }

    $Magister->messageSend([
        'to'      => $_POST['to'],
        'cc'      => $_POST['cc'] ?? null,
        'bcc'     => $_POST['bcc'] ?? null,
        'subject' => $_POST['subject'] ?? null,
        'content' => $_POST['content'] ?? null,
    ]);
?>