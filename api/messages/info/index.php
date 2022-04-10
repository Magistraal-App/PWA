<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['id'])) {
        \Magistraal\Response\error('parameter_id_mising');
    }
    
    $id = $_POST['id'];

    \Magistraal\Response\success(\Magistraal\Messages\get($id));
?>