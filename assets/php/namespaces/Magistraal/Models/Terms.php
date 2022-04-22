<?php 
    namespace Magistraal\Terms;

    function format($term) {
        return [
            'id'          => $term['Id'],
            'name'        => $term['Naam'],
            'description' => $term['Omschrijving'],
            'order'       => $term['VolgNummer'],
            'start'       => $term['Start'],
            'end'         => $term['Einde']
        ];
    }

    function format_all($terms) {
        $formatted = [];

        foreach ($terms as $term) {
            $formatted[] = \Magistraal\Terms\format($term);
        }

        uasort($formatted, function($a, $b) {
            return $a['order'] > $b['order'];
        });

        return $formatted;
    }
?>