<?php 
    namespace Magistraal\Terms;

    function get_all($course_id = null, $filter = null) {
        return \Magistraal\Terms\format_all(\Magister\Session::termList($course_id), $filter);
    }

    function format($term, $filter = null) {
        $formatted = [
            'id'          => $term['Id'],
            'name'        => $term['Naam'],
            'description' => $term['Omschrijving'],
            'order'       => $term['VolgNummer'],
            'start'       => $term['Start'],
            'end'         => $term['Einde']
        ];

        return filter_items($formatted, $filter);
    }

    function format_all($terms, $filter = null) {
        $formatted = [];

        foreach ($terms as $term) {
            $formatted[] = \Magistraal\Terms\format($term, $filter);
        }

        uasort($formatted, function($a, $b) {
            return $a['order'] > $b['order'];
        });

        return $formatted;
    }
?>