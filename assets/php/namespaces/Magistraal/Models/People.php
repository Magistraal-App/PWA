<?php 
    namespace Magistraal\People;

    function search($query) {
        return \Magistraal\People\format_all(\Magister\Session::peopleList($query));
    }

    function format_all($people) {
        $result = [];
        
        foreach ($people as $person) {
            $result[] = \Magistraal\People\format($person);
        }

        return $result;
    }

    function format($person) {
        return [
            'abbr'       => $person['code'],
            'id'         => $person['id'],
            'initials'   => $person['voorletters'],
            'first_name' => $person['roepnaam'] ?? $person['voorletters'] ?? '',
            'infix'      => $person['tussenvoegsel'] ?? '',
            'last_name'  => $person['achternaam'],
            'course'     => $person['klas'] ?? null,
            'type'       => \Magistraal\People\remap_type($person['type'])
        ];
    }

    function remap_type($str) {
        switch($str) {
            case 'leerling': return 'student';
            case 'medewerker': return 'employee';
        }
    }
?>