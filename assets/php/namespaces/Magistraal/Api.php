<?php 
    namespace Magistraal\Api;

    function call_anonymous($url, $payload = []) {
        return \Magistraal\Browser\Browser::request($url, [
            'payload' => $payload,
            'anonymous' => true
        ]);
    }

    function call($url, $payload = []) {
        return \Magistraal\Browser\Browser::request($url, [
            'payload' => $payload
        ]);
    }
?>