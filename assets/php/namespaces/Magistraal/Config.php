<?php 
    namespace Magistraal\Config;

    function get($key) {
        return CONFIG[$key] ?? null;
    }
?>