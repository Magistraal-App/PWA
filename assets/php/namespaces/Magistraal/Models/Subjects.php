<?php 
    namespace Magistraal\Subjects;

    function format($subject) {
        return [
            'id'          => $subject['id'],
            'abbr'        => $subject['afkorting'],
            'description' => $subject['omschrijving'],
            'exemption'   => $subject['vrijstelling'] || $subject['heeftOntheffing'],
            'teacher'     => $subject['docent'],
            'start'       => $subject['begindatum'],
            'end'         => $subject['einddatum']
        ];
    }
    
    function format_all($subjects) {
        $formatted = [];

        foreach ($subjects as $subject) {
            $formatted[] = \Magistraal\Subjects\format($subject);
        }

        return $formatted;
    }
?>