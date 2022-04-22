<?php 
    namespace Magistraal\Absences;

    function get_all($iso_from, $iso_to) {
        return \Magistraal\Absences\format_all(\Magister\Session::absencesList($iso_from, $iso_to), $iso_from, $iso_to);
    }

    function format_all($absences, $iso_from, $iso_to) {
        $unix_from = strtotime($iso_from);
        $unix_to   = strtotime($iso_to);

        $formatted = [];

        // Generate an array with months between from and to as keys
        for ($unix=$unix_from; $unix <= $unix_to;) { 
            $formatted[date('Y-m', $unix)] = ['time' => date_iso($unix), 'unix' => $unix, 'absences' => []];
            $unix = strtotime('+1 month', $unix);
        }
        
        foreach ($absences as $absence) {
            $yearmonth = date('Y-m', strtotime($absence['Afspraak']['Start']));

            // Skip if absence does not fit in the desired timespan
            if(!isset($formatted[$yearmonth])) {
                continue;
            }

            $formatted[$yearmonth]['absences'][] = \Magistraal\Absences\format($absence);
        }

        uasort($formatted, function($a, $b) {
            return ($a['unix'] < $b['unix'] ? 1 : -1);
        });


        return $formatted;
    }

    function format($absence) {
        return [
            'abbr'        => $absence['Code'],
            'appointment' => \Magistraal\Appointments\format($absence['Afspraak']),
            'description' => trim($absence['Omschrijving']),
            'id'          => $absence['Id'],
            'lesson'      => $absence['Lesuur'],
            'type'        => \Magistraal\Absences\remap_type($absence['Verantwoordingtype']),
            'permitted'   => $absence['Geoorloofd']
        ];
    }

    function remap_type($int) {
        switch($int) {
            case 1:  return 'absent';
            case 2:  return 'late';
            case 3:  return 'sick';
            case 4:  return 'suspended';
            case 6:  return 'exemption';
            case 7:  return 'forgot_books';
            case 8:  return 'forgot_homework';
        }

        return 'unknown';
    }
?>