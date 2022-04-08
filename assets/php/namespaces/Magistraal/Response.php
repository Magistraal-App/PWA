<?php 
    namespace Magistraal\Response;

    function error($info = '', $http_code = 400) {
        http_response_code($http_code);
        if(!headers_sent()) {
            header('Content-Type: application/json');
        }
        exit(json_encode(['success' => false, 'info' => $info]));
    }

    function success($data = [], $http_code = 200) {
        http_response_code($http_code);
        if(!headers_sent()) {
            header('Content-Type: application/json');
        }
        exit(json_encode(['success' => true, 'data' => $data]));
    }
?>