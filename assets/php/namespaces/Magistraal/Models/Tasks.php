<?php 
    namespace Magistraal\Tasks;

    function get_all($date_from = null, $date_to = null, $top = null, $skip = null, $filter = null) {
        return \Magistraal\Tasks\format_all(\Magister\Session::taskList($date_from, $date_to, $top, $skip) ?? [], $filter);
    }

    function get($id) {
        return \Magistraal\Tasks\format(\Magister\Session::taskGet($id) ?? []);
    }

    function format($task, $filter = null, $subjects = null) {
        $formatted = $task;

        var_dump($subjects);

        return filter_items($formatted, $filter);
    }

    function format_all($tasks, $filter = null) {
        $formatted = [];

        $subjects = \Magistraal\Subjects\get_all();

        foreach ($tasks as $task) {
            $formatted[] = \Magistraal\Tasks\format($task, $filter, $subjects);
        }

        return $formatted;
    }
?>