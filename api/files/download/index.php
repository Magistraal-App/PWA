<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    if(!isset($_POST['location'])) {
        \Magistraal\Response\error('parameter_location_mising');
    }
    
    $location = \Magister\Session::fileGetLocation($_POST['location']);
    $response = \Magistraal\Browser\Browser::request($location);

    // Send file headers
    if(!headers_sent()) {
        if(isset($response['headers']['content-type'])) {
            header("Content-Type: {$response['headers']['content-type']}");
        }

        if(isset($response['headers']['content-length'])) {
            header("Content-Length: {$response['headers']['content-length']}");
        }

        if(isset($response['headers']['content-disposition'])) {
            header("Content-Disposition: {$response['headers']['content-disposition']}");
        }
    }

    // Print content (encoded)
    echo($response['body']);
?>