<?php
    namespace Magistraal\Learningresources;

    function get_all($filter = null) {
        return \Magistraal\Learningresources\format_all(\Magister\Session::learningresourceList() ?? [], $filter);
    }

    function get($id, $filter = null) {
        return \Magister\Session::learningresourceGet($id) ?? [];
    }

    function format($learningresource, $filter = null) {
        $formatted = [
            'id'          => $learningresource['EAN'] ?? null,
            'description' => trim($learningresource['Titel'] ?? null),
            'publisher'   => trim($learningresource['Uitgeverij'] ?? null),
            'subject'     => [
                'id'          => $learningresource['Vak']['Id'] ?? null,
                'description' => $learningresource['Vak']['Omschrijving'] ?? null,
                'code'        => $learningresource['Vak']['Afkorting'] ?? null
            ]
        ];

        return filter_items($formatted, $filter);
    }

    function format_all($learningresources, $filter = null) {
        $formatted = [];

        foreach ($learningresources as $learningresource) {
            $formatted[] = \Magistraal\Learningresources\format($learningresource, $filter);
        }

        usort($formatted, function($a, $b) {
            return strcmp($a['description'], $b['description']);
        });

        return $formatted;
    }
?>