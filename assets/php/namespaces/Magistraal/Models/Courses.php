<?php 
    namespace Magistraal\Courses;

    function get_all($date_from = null, $date_to = null, $elaborate = null, $filter = null) {
        return \Magistraal\Courses\format_all(\Magister\Session::courseList($date_from, $date_to, $elaborate) ?? [], $filter);
    }

    function format($course, $filter = null) {
        $formatted = [
            'id'       => $course['id'],
            'start'    => date_iso(strtotime($course['begin'])),
            'end'      => date_iso(strtotime($course['einde'])),
            'active'   => (strtotime($course['begin']) <= time() && strtotime($course['einde']) >= time()),
            'columns'  => []
        ];

        return filter_items($formatted, $filter);
    }

    function format_all($courses, $filter = null) {
        $formatted = [];

        foreach ($courses as $course) {
            $formatted[] = \Magistraal\Courses\format($course, $filter);
        }

        return $formatted;
    }
?>