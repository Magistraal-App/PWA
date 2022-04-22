<?php 
    namespace Magistraal\Courses;

    function get_all() {
        return \Magistraal\Courses\format_all(\Magister\Session::courseList());
    }

    function format($course) {
        $formatted = [
            'id'       => $course['id'],
            'start'    => date_iso(strtotime($course['begin'])),
            'end'      => date_iso(strtotime($course['einde'])),
            'active'   => (strtotime($course['begin']) <= time() && strtotime($course['einde']) >= time()),
            'terms'    => \Magistraal\Terms\format_all($course['terms']),
            'subjects' => \Magistraal\Subjects\format_all($course['subjects']),
            'grades'   => \Magistraal\Grades\format_all_overview($course['grades']),
            'columns'  => []
        ];
        
        // Format columns
        foreach ($formatted['grades'] as $grade) {
            $formatted['columns'][] = [
                'id'          => $grade['column']['id'],
                'description' => $grade['column']['description'],
                'name'        => $grade['column']['name'],
                'number'      => $grade['column']['number'],
                'order'       => $grade['column']['order'],
                'term'        => [
                    'name'        => $grade['term']['name'],
                    'id'          => $grade['term']['id']
                ],
                'type'        => $grade['column']['type'] == '1' ? 'grades' : 'averages',
                'variant'     => $grade['column']['variant']
            ];
        }

        // Sort columns
        // usort($formatted['columns'], function($a, $b) {
        //     return $a['order'] > $b['order'];
        // });

        return $formatted;
    }

    function format_all($courses) {
        $formatted = [];

        foreach ($courses as $course) {
            $formatted[] = \Magistraal\Courses\format($course);
        }

        return $formatted;
    }
?>