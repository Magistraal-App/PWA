<?php 
    namespace Magistraal\Response;

    function error($info = '', $http_code = 400) {
        if(defined('ALLOW_EXIT') && ALLOW_EXIT != true) {
            return false;
        }

        http_response_code($http_code);
        if(!headers_sent()) {
            header('content-type: application/json');
        }

        exit(json_encode(['success' => false, 'info' => $info, 'took' => round(microtime(true) - REQUEST_START_US, 3)]));
    }

    function success($data = [], $http_code = 200) {
        if(defined('ALLOW_EXIT') && ALLOW_EXIT != true) {
            return false;
        }

        http_response_code($http_code);
        if(!headers_sent()) {
            header('content-type: application/json');
        }

        exit(json_encode(['success' => true, 'data' => $data, 'took' => round(microtime(true) - REQUEST_START_US, 3)]));
    }
?>