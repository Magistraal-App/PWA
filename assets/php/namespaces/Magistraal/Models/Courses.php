<?php 
    namespace Magistraal\Courses;

    function get_all() {
        return \Magistraal\Courses\format_all(\Magister\Session::coursesList());
    }

    function format_all($courses) {
        return $courses;
    }
?>