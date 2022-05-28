<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    \Magister\Session::start();

    $course_id = \Magister\Session::courseActiveId();

    \Magistraal\Response\success([
        'grades'   => \Magistraal\Grades\get_all_overview($course_id),
        'terms'    => \Magistraal\Terms\get_all($course_id),
        'subjects' => \Magistraal\Subjects\get_all($course_id)
    ]);
?>