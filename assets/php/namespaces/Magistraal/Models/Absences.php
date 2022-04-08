<?php 
    namespace Magistraal\Absences;

    function get_all($from, $to) {
        return \Magistraal\Absences\format_all(\Magister\Session::absencesList($from, $to));
    }

    function format_all($absences) {
        $result = [];
        
        foreach ($absences as $absence) {
            $result[] = [
                'id'          => $absence['Id'],
                'permitted'   => $absence['Geoorloofd'],
                'lesson'      => $absence['Lesuur'],
                'designation' => trim($absence['Omschrijving']),
                'appointment' => \Magistraal\Appointments\format_all([$absence['Afspraak']])
            ];
        }
    }
?>