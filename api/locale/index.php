<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");

    if(!isset($_GET['locale'])) {
        \Magistraal\Response\error('parameter_locale_missing');
    }

    $file = ROOT."/assets/locale/{$_GET['locale']}.json";
    if(!file_exists($file)) {
        \Magistraal\Response\error('error_finding_file');
    }

    if(!$translations = @json_decode(@file_get_contents($file), true)) {
        \Magistraal\Response\error('error_decoding');
    }

    \Magistraal\Response\success($translations);
?>