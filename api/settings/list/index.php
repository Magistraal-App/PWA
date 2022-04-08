<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magistraal\Response\success(\Magistraal\Settings\get_all($_POST['category'] ?? null, $_POST['sub_category'] ?? null));
?>