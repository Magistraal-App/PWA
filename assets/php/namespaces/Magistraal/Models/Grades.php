<?php 
    namespace Magistraal\Grades;

    function get_all($top = null, $filter = []) {
        return \Magistraal\Grades\format_all(\Magister\Session::gradeList($top), $filter);
    }

    function format($grade, $filter = []) {
        $formatted = [
            'column_id'   => $grade['kolomId'],
            'counts'      => $grade['teltMee'],
            'description' => $grade['omschrijving'],
            'entered_at'  => $grade['ingevoerdOp'] ?? null,
            'exemption'   => $grade['heeftVrijstelling'],
            'make_up'     => $grade['moetInhalen'],
            'passed'      => $grade['isVoldoende'],
            'subject'     => [
                'abbr'        => $grade['vak']['code'],
                'description' => $grade['vak']['omschrijving']
            ],
            'value_str'   => $grade['waarde'],
            'value'       => \Magistraal\Grades\grade_str_to_float($grade['waarde']),
            'weight'      => $grade['weegfactor']
        ];

        // Filter out non-wanted items
        if(!empty($filter)) {
            $formatted = array_filter($formatted, function ($key) use ($filter) {
                return in_array($key, $filter);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $formatted;
    }

    function format_overview($grade, $filter = []) {
        $formatted = [
            'id'            => $grade['CijferId'] ?? null,
            'term'          => [
                'id'            => $grade['CijferPeriode']['Id'] ?? null,
                'description'   => $grade['CijferPeriode']['Naam'] ?? null
            ],
            'subject'       => [
                'id'            => $grade['Vak']['Id'] ?? null,
                'abbr'          => $grade['Vak']['Afkorting'],
                'description'   => $grade['Vak']['Omschrijving'] ?? ''
            ],
            'is_sufficient' => $grade['IsVoldoende'] ?? null,
            'entered_by'    => $grade['IngevoerdDoor'] ?? '',
            'entered_at'    => $grade['DatumIngevoerd'] ?? date_iso(),
            'retake'        => $grade['Inhalen'] ?? null,
            'exemption'     => $grade['Vrijstelling'] ?? null,
            'counts'        => $grade['TeltMee'] ?? null,
            'value'         => \Magistraal\Grades\grade_str_to_float($grade['CijferStr']),
            'value_str'     => $grade['CijferStr'],
            'column'        => [
                'id'            => $grade['CijferKolom']['Id'] ?? null,
                'name'          => $grade['CijferKolom']['KolomKop'] ?? '',
                'number'        => $grade['CijferKolom']['KolomNummer'] ?? null,
                'order'         => $grade['CijferKolom']['KolomVolgNummer'] ?? null,
                'description'   => $grade['CijferKolom']['KolomNaam'] ?? '',
                'type'          => $grade['CijferKolom']['KolomSoort'] ?? null,
                'variant'       => $grade['CijferKolom']['IsPTAKolom'] ? 'pta' : 'normal'
            ]
        ];

        // Filter out non-wanted items
        if(!empty($filter)) {
            $formatted = array_filter($formatted, function ($key) use ($filter) {
                return in_array($key, $filter);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $formatted;
    }
 
    function format_all($grades, $filter = []) {
        $formatted = [];

        foreach ($grades as $grade) {
            $formatted[] = \Magistraal\Grades\format($grade, $filter);
        }

        return $formatted;
    }

    function format_all_overview($grades, $filter = []) {
        $formatted = [];

        foreach ($grades as $grade) {
            $formatted[] = \Magistraal\Grades\format_overview($grade, $filter);
        }

        return $formatted;
    }

    function grade_str_to_float($str) {
        $str = strtolower(trim(str_replace(',', '.', $str)));
        $textual_mapping = [
            'zs' => 1,
            's'  => 2,
            'ro' => 3,
            'o'  => 4,
            'm'  => 5,
            'v'  => 6,
            'rv' => 7,
            'g'  => 8,
            'zg' => 9,
            'u'  => 10
        ];

        if(is_numeric($str)) {
            return round(floatval($str), 1);
        } else if(isset($textual_mapping[$str])) {
            return $textual_mapping[$str];
        }

        return null;
    }
?>