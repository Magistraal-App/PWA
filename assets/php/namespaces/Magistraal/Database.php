<?php 
    namespace Magistraal\Database;

    function connect() {       
        return new \mysqli(
            \Magistraal\Config\get('mysql_hostname'),
            \Magistraal\Encryption\decrypt(\Magistraal\Config\get('mysql_username')),
            \Magistraal\Encryption\decrypt(\Magistraal\Config\get('mysql_password')),
            \Magistraal\Config\get('mysql_database')
        );
    }

    function query(string $query, $parameters = []) {
        if(!is_array($parameters)) {
            $parameters = [$parameters];
        }

        $mysqli = \Magistraal\Database\connect();

        $parameter_types_string = '';
        foreach ($parameters as $parameter) {
            if(is_string($parameter)) {
                $parameter_types_string .= 's';
            } else if(is_integer($parameter)) {
                $parameter_types_string .= 'i';
            } else if(is_float($parameter)) {
                $parameter_types_string .= 'd';
            } else {
                $parameter_types_string .= 'b';
            }
        }

        $stmt = $mysqli->prepare($query);

        if(is_bool($stmt)) {
            return false;
        }
        
        if(count($parameters) > 0) {
            $stmt->bind_param($parameter_types_string, ...$parameters);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        if(is_bool($result)) {
            return true;
        }

        $rows = [];
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            // Remove min() and max() from column names
            foreach ($row as $key => $value) {
                unset($row[$key]);
                $key = str_ireplace(['min(', 'max(', ')'], '', $key);
                $row[$key] = $value;
            }

            $rows[] = $row;
        }
        
        return $rows;
    }
?>