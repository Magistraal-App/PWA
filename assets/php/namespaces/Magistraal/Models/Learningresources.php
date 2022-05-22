<?php
    namespace Magistraal\Learningresources;

    function get_all($filter = []) {
        return \Magistraal\Learningresources\format_all(\Magister\Session::learningresourceList(), $filter);
    }

    function get($id) {
        return \Magister\Session::learningresourceGet($id);
    }

    function format($learningresource) {
        return [
            'id'          => $learningresource['EAN'],
            'description' => trim($learningresource['Titel']),
            'publisher'   => trim($learningresource['Uitgeverij']),
            'subject'     => [
                'id'          => $learningresource['Vak']['Id'],
                'description' => $learningresource['Vak']['Omschrijving'],
                'code'        => $learningresource['Vak']['Afkorting']
            ]
        ];
    }

    function format_all($learningresources, $filter) {
        $formatted = [];

        foreach ($learningresources as $learningresource) {
            // Ga verder als het leermiddel verlopen is
            if(!isset($learningresource['Eind']) || strtotime($learningresource['Eind']) < time()) {
                continue;
            }

            $formatted[] = \Magistraal\Learningresources\format($learningresource, $filter);
        }

        // Filter out non-wanted items
        if(!empty($filter)) {
            $formatted = array_filter($formatted, function ($key) use ($filter) {
                return in_array($key, $filter);
            }, ARRAY_FILTER_USE_KEY);
        }

        // Sorteer leermiddelen
        usort($formatted, function($a, $b) {
            return strcmp($a['description'], $b['description']);
        });

        return $formatted;
    }
?>