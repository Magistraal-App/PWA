<?php 
    namespace Magistraal\Studyguides;

    function get_all($filter = null) {
        return \Magistraal\Studyguides\format_all(\Magister\Session::studyguideList() ?? [], $filter);
    }

    function get($id) {
        return \Magistraal\Studyguides\format(\Magister\Session::studyguideGet($id));
    }

    function get_sources($id, $detail_id) {
        return \Magistraal\Sources\format_all(\Magister\Session::studyguideSourceList($id, $detail_id));
    }

    function format($studyguide) {
        $formatted = [
            'id'          => $studyguide['Id'] ?? null,
            'start'       => $studyguide['Van'] ?? null,
            'end'         => $studyguide['TotEnMet'] ?? null,
            'description' => $studyguide['Titel'] ?? null,
            'location'    => array_column($studyguide['Links'] ?? [], 'Href', 'Rel')['Self'] ?? null,
            'details'     => []
        ];

        foreach ($studyguide['Onderdelen']['Items'] ?? [] as $asset) {
            $formatted['details'][] = [
                'id'          => $asset['Id'] ?? null,
                'name'        => $asset['Titel'] ?? null,
                'description' => $asset['Omschrijving'] ?? null,
                'color'       => \Magistraal\Studyguides\remap_color($asset['Kleur'] ?? null)
            ];
        }

        return $formatted;
    }

    function format_all($studyguides) {
        $formatted = [];

        foreach ($studyguides as $studyguide) {
            $formatted[] = \Magistraal\Studyguides\format($studyguide);
        }

        return $formatted;
    }

    function remap_color($id) {
        switch($id) {
            case 0:  return 'var(--blue)';
            case 3:  return 'var(--red)';
            case 9:  return 'var(--purple)';
            case 11: return 'var(--yellow)';
            case 12: return 'var(--green)';
        }

        return 'var(--blue)';
    }
?>