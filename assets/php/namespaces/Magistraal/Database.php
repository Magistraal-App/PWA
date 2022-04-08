<?php 
    namespace Magistraal\Database;

    function connect() {
        return new \MysqliDatabase(
            \Magistraal\Config\get('mysql_hostname'),
            \Magistraal\Config\get('mysql_username'),
            \Magistraal\Config\get('mysql_password'),
            \Magistraal\Config\get('mysql_database')
        );
    }
?>