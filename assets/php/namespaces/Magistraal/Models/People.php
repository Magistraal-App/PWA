<?php 
    namespace Magistraal\People;

    function search($query) {
        return \Magistraal\People\format_all(\Magister\Session::peopleList($query));
    }

    function format_all($people) {
        $formatted = [];
        
        foreach ($people as $person) {
            $formatted[] = \Magistraal\People\format($person);
        }

        return $formatted;
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