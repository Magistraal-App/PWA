<?php 
    namespace Magistraal\Database;

    function connect() {       
        return new \MysqliDb(
            \Magistraal\Config\get('mysql_hostname'),
            \Magistraal\Encryption\decrypt(\Magistraal\Config\get('mysql_username')),
            \Magistraal\Encryption\decrypt(\Magistraal\Config\get('mysql_password')),
            \Magistraal\Config\get('mysql_database'),
            true
        );
    }
?>