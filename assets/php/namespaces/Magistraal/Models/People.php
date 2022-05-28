<?php 
    namespace Magistraal\People;

    function search($query, $filter = null) {
        return \Magistraal\People\format_all(\Magister\Session::peopleList($query) ?? [], $filter);
    }

    function format_all($people, $filter = null) {
        $formatted = [];
        
        foreach ($people as $person) {
            $formatted[] = \Magistraal\People\format($person, $filter);
        }

        return $formatted;
    }

    function format($person, $filter = null) {
        $formatted = [
            'code'       => $person['code'],
            'id'         => $person['id'],
            'initials'   => $person['voorletters'],
            'first_name' => $person['roepnaam'] ?? $person['voorletters'] ?? '',
            'infix'      => $person['tussenvoegsel'] ?? '',
            'last_name'  => $person['achternaam'],
            'course'     => $person['klas'] ?? null,
            'type'       => \Magistraal\People\remap_type($person['type'])
        ];

        return filter_items($formatted, $filter);
    }

    function remap_type($str) {
        switch($str) {
            case 'leerling': return 'student';
            case 'medewerker': return 'employee';
        }
    }
?>