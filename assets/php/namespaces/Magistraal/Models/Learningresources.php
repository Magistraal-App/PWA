<?php
    namespace Magistraal\Learningresources;

    function get_all($filter = null) {
        return \Magistraal\Learningresources\format_all(\Magister\Session::learningresourceList() ?? [], $filter);
    }

    function get($id, $filter = null) {
        return \Magistraal\Learningresources\format(\Magister\Session::learningresourceGet($id) ?? [], $filter);
    }

    function format($learningresource, $filter = null) {
        $formatted = [
            'id'          => $learningresource['EAN'],
            'description' => trim($learningresource['Titel']),
            'publisher'   => trim($learningresource['Uitgeverij']),
            'subject'     => [
                'id'          => $learningresource['Vak']['Id'],
                'description' => $learningresource['Vak']['Omschrijving'],
                'code'        => $learningresource['Vak']['Afkorting']
            ]
        ];

        return filter_items($formatted, $filter);
    }

    function format_all($learningresources, $filter = null) {
        $formatted = [];

        foreach ($learningresources as $learningresource) {
            // Ga verder als het leermiddel verlopen is
            if(!isset($learningresource['Eind']) || strtotime($learningresource['Eind']) < time()) {
                continue;
            }

            $formatted[] = \Magistraal\Learningresources\format($learningresource, $filter);
        }

        // Sorteer leermiddelen
        usort($formatted, function($a, $b) {
            return strcmp($a['description'], $b['description']);
        });

        return $formatted;
    }
?>