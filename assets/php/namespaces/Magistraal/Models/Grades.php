<?php 
    namespace Magistraal\Grades;

    function get_all($top = null) {
        return \Magistraal\Grades\format_all(\Magister\Session::gradeList($top));
    }

    function format_all($grades) {
        $result = [];

        foreach ($grades as $grade) {
            $result[] = [
                'column_id'   => $grade['kolomId'],
                'counts'      => $grade['teltMee'],
                'description' => $grade['omschrijving'],
                'entered_at'  => \strtotime($grade['behaaldOp'] ?? $grade['ingevoerdOp'] ?? null),
                'exemption'   => $grade['heeftVrijstelling'],
                'got_at'      => \strtotime($grade['behaaldOp'] ?? $grade['ingevoerdOp'] ?? null),
                'make_up'     => $grade['moetInhalen'],
                'passed'      => $grade['isVoldoende'],
                'subject'     => [
                    'code'        => $grade['vak']['code'],
                    'description' => $grade['vak']['omschrijving']
                ],
                'value_str'   => $grade['waarde'],
                'value'       => \Magistraal\Grades\grade_str_to_float($grade['waarde']),
                'weight'      => $grade['weegfactor']
            ];
        }

        return $result;
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