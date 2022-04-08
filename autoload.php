<?php 
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');
    header('Content-Type: application/json');

    session_start();

    define('ROOT', rtrim(__DIR__), '/');
    define('API', ROOT.'/api');
    define('CONFIG', parse_ini_file(ROOT.'/config/Magistraal.ini'));

    include_once(ROOT.'/assets/php/functions.php');

    include_once(ROOT.'/assets/php/classes/Magister.php');
    include_once(ROOT.'/assets/php/classes/Magistraal.php');

    include_once(ROOT.'/assets/php/namespaces/Magistraal/Response.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Api.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Config.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Browser.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Authentication.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Settings.php');

    include_once(ROOT.'/assets/php/namespaces/Magistraal/Models/Absences.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Models/Account.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Models/Appointments.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Models/Grades.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Models/Messages.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Models/Sources.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Models/Studyguides.php');
    include_once(ROOT.'/assets/php/namespaces/Magistraal/Models/Tenants.php');

    $_REQUESTHEADERS = array_change_key_case(getallheaders(), CASE_LOWER);
?>