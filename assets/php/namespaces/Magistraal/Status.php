<?php 
    namespace Magistraal\Status;

    function get($key) {
        $status = \Magistraal\Status\get_status();
        return $status[$key] ?? null;
    }

    function set($key, $value) {
        $status = \Magistraal\Status\get_status();
        $status[$key] = $value;
        return \Magistraal\Status\set_status($status);
    }
    
    function get_status() {
        $file = ROOT.'/status.json';
        return @json_decode(file_get_contents($file), true) ?? [];
    }

    function set_status($status) {
        $file = ROOT.'/status.json';
        return file_put_contents($file, json_encode($status));
    }
?>