<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['to']) || empty($_POST['to'])) {
        \Magistraal\Response\error('parameter_to_missing');
    }

    if(!isset($_POST['cc']) || $_POST['cc'] == '') {
        $_POST['cc'] = [];
    }

    if(!isset($_POST['bcc']) || $_POST['bcc'] == '') {
        $_POST['bcc'] = [];
    }

    if(\Magistraal\Messages\send(
        $_POST['to'],
        $_POST['cc'] ?? null,
        $_POST['bcc'] ?? null,
        $_POST['subject'] ?? null,
        $_POST['content'] ?? null,
        $_POST['priority'] ?? null
    )) {
        \Magistraal\Response\success();
    }

    \Magistraal\Response\error();
?>