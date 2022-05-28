<?php 
    namespace Magistraal\Grades;

    function get_all($top = null, $filter = null) {
        return \Magistraal\Grades\format_all(\Magister\Session::gradeList($top) ?? [], $filter);
    }

    function get_all_overview($course_id = null) {
        return \Magistraal\Grades\format_all_overview(\Magister\Session::gradeOverview($course_id));
    }

    function format($grade, $filter = null) {
        $value_float = \Magistraal\Grades\grade_str_to_float($grade['waarde']);

        $formatted = [
            'column_id'     => $grade['kolomId'],
            'counts'        => $grade['teltMee'],
            'description'   => $grade['omschrijving'],
            'entered_at'    => $grade['ingevoerdOp'] ?? null,
            'exemption'     => $grade['heeftVrijstelling'],
            'make_up'       => $grade['moetInhalen'],
            'subject'       => [
                'code'          => $grade['vak']['code'],
                'description'   => $grade['vak']['omschrijving']
            ],
            'value_str'     => $grade['waarde'],
            'value'         => $value_float,
            'is_sufficient' => is_null($value_float) || $value_float >= 5.5,
            'weight'        => $grade['weegfactor']
        ];

        return filter_items($formatted, $filter);
    }

    function format_overview($grade, $filter = null) {
        $formatted = [
            'id'            => $grade['CijferId'] ?? null,
            'term'          => [
                'id'            => $grade['CijferPeriode']['Id'] ?? null,
                'description'   => $grade['CijferPeriode']['Naam'] ?? null
            ],
            'subject'       => [
                'id'            => $grade['Vak']['Id'] ?? null,
                'code'          => $grade['Vak']['Afkorting'],
                'description'   => $grade['Vak']['Omschrijving'] ?? ''
            ],
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
                'type'          => $grade['CijferKolom']['KolomSoort'] == 1 ? 'grades' : 'averages',
                'variant'       => $grade['CijferKolom']['IsPTAKolom'] ? 'pta' : 'normal'
            ]
        ];

        return filter_items($formatted, $filter);
    }
 
    function format_all($grades, $filter = null) {
        $formatted = [];

        foreach ($grades as $grade) {
            $formatted[] = \Magistraal\Grades\format($grade, $filter);
        }

        return $formatted;
    }

    function format_all_overview($grades, $filter = null) {
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