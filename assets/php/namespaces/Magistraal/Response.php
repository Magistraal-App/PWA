<?php 
    namespace Magistraal\Response;

    function error($info = '', $http_code = 400) {
        http_response_code($http_code);
        if(!headers_sent()) {
            header('content-type: application/json');
        }
        exit(json_encode(['success' => false, 'info' => $info, 'took' => microtime(true) - REQUEST_START_US]));
    }

    function success($data = [], $http_code = 200) {
        http_response_code($http_code);
        if(!headers_sent()) {
            header('content-type: application/json');
        }
        exit(json_encode(['success' => true, 'data' => $data, 'took' => microtime(true) - REQUEST_START_US]));
    }
?>