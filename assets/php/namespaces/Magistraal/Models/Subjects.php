<?php 
    namespace Magistraal\Subjects;

    function get_all($course_id = null, $filter = null) {
        return \Magistraal\Subjects\format_all(\Magister\Session::subjectList($course_id), $filter);
    }

    function format($subject, $filter = null) {
        $formatted =  [
            'id'          => $subject['id'] ?? null,
            'code'        => $subject['afkorting'] ?? null,
            'description' => $subject['omschrijving'] ?? null,
            'exemption'   => ($subject['vrijstelling'] ?? null || $subject['heeftOntheffing'] ?? null) ?? false,
            'teacher'     => $subject['docent'] ?? null,
            'start'       => $subject['begindatum'] ?? null,
            'end'         => $subject['einddatum'] ?? null
        ];

        return filter_items($formatted, $filter);
    }
    
    function format_all($subjects) {
        $formatted = [];

        foreach ($subjects as $subject) {
            $formatted[] = \Magistraal\Subjects\format($subject);
        }

        return $formatted;
    }
?>