<?php 
    namespace Magistraal\Sources;

    function get_all($parent_id = null, $filter = null) {
        return \Magistraal\Sources\format_all(\Magister\Session::sourceList($parent_id) ?? [], $filter);
    }

    function format($source, $filter = null) {
        $formatted = [
            'id'           => $source['Id'] ?? null,
            'name'         => $source['Naam'] ?? '',
            'content_type' => $source['ContentType'] ?? null,
            'reference'    => $source['Referentie'] ?? null,
            'size'         => $source['Grootte'] ?? null,
            'type'         => \Magistraal\Sources\remap_type($source['BronSoort'] ?? 0),
            'location'     => \array_item_sibling('Rel', 'Contents', 'Href', $source['Links']),
            'order'        => $source['Volgnr'] ?? null
        ];

        return filter_items($formatted, $filter);
    }

    function format_all($sources, $filter = null) {
        $formatted = [];

        foreach ($sources as $source) {
            $formatted[] = \Magistraal\Sources\format($source, $filter);
        }

        uasort($formatted, function($a, $b) {
            return $a['order'] > $b['order'];
        });

        return $formatted;
    }

    function remap_type($int) {
        switch($int) {
            case 0:
                return 'folder';
            case 1:
                return 'file';
            case 3:
                return 'link';
        }

        return 'unknown';
    }
?>