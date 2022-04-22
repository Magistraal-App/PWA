<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    define('ALLOW_EXIT', false);
    \Magister\Session::start();
    header('Content-Type: text/html;'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Home | Magistraal</title>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#000000">

    <link rel="manifest" href="/magistraal/manifest.json" />
    <link rel="icon" type="image/ico" href="/magistraal/client/favicon.ico">

    <?php echo(\Magistraal\Frontend\assetsHTML()); ?>

    <script>
        if(!magistraal.token.isSet()) {
            magistraal.page.load({
                page: 'login'
            });

            magistraal.settings.updateClient(<?php echo(json_encode(\Magistraal\User\Settings\get_all(\Magister\Session::$userUuid ?? null))); ?>);
        }

        $(document).ready(function() {
            magistraal.load({
                version: '<?php echo(VERSION); ?>',
                doPreCache: true
            });

            magistraal.settings.get_all().then(settings => {
                magistraal.settings.updateClient(settings, true);
            })
        })

        $(document).on('magistraal.ready', function() {
            const currentPage = window.location.hash.substring(2);
            magistraal.page.load({
                page: currentPage.length > 0 ? currentPage : 'appointments/list'
            });
        })

        $(window).on('load', function() {
            if(!isSet(window.history.state)) {
                window.history.replaceState('backPage', null, null);
            }
        })
    </script>
</head>
<body data-nav-active="false" data-sidebar-active="false" data-settings="<?php echo(http_build_query(\Magistraal\User\Settings\get_all(\Magister\Session::$userUuid ?? null), '', ',')); ?>" data-online="false">
     <nav onmouseenter="magistraal.nav.open()" onmouseleave="magistraal.nav.close();" class="scrollbar-hidden">
        <ul class="nav-items">
            <li tabindex="0" class="nav-item text-inverse text-muted-inverse" #onclick="magistraal.page.load({'home'});" data-magistraal="nav-item-home">
                <i class="fal fa-home"></i>
                <span data-translation="generic.page.home.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'appointments/list'});" data-magistraal="nav-item-appointments/list">
                <i class="fal fa-calendar-alt"></i>
                <span data-translation="generic.page.appointments/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'grades/list'});" data-magistraal="nav-item-grades/list">
                <i class="fal fa-award"></i>
                <span data-translation="generic.page.grades/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'absences/list'});" data-magistraal="nav-item-absences/list">
                <i class="fal fa-calendar-times"></i>
                <span data-translation="generic.page.absences/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'sources/list'});" data-magistraal="nav-item-sources/list">
                <i class="fal fa-folder"></i>
                <span data-translation="generic.page.sources/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse text-muted-inverse" #onclick="magistraal.page.load({'studyguides'});" data-magistraal="nav-item-studyguides/list">
                <i class="fal fa-map-signs"></i>
                <span data-translation="generic.page.studyguides/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse text-muted-inverse" #onclick="magistraal.page.load({'tasks'});" data-magistraal="nav-item-tasks/list">
                <i class="fal fa-pencil-alt"></i>
                <span data-translation="generic.page.tasks/list.title"></span>
            </li>
        </ul>
        <ul class="nav-items mt-auto">
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'messages/list'});" data-magistraal="nav-item-messages/list">
                <i class="fal fa-envelope"></i>
                <span data-translation="generic.page.messages/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse text-muted-inverse" #onclick="magistraal.page.load({'account'});" data-magistraal="nav-item-account/list">
                <i class="fal fa-user"></i>
                <span data-translation="generic.page.account/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse online-only" onclick="magistraal.page.load({page: 'settings/list'});" data-magistraal="nav-item-settings/list">
                <i class="fal fa-cog"></i>
                <span data-translation="generic.page.settings/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'logout', cachable: false});" data-magistraal="nav-item-logout">
                <i class="fal fa-sign-out"></i>
                <span data-translation="generic.page.logout.title"></span>
            </li>
        </ul>
    </nav>
    <header class="d-flex flex-row flex-nowrap align-items-center">
        <button class="btn btn-square btn-lg btn-secondary d-md-none col px-0" data-magistraal="nav-toggler" onclick="magistraal.nav.open();">
            <i class="fal fa-bars"></i>
        </button>
        <button class="btn btn-square btn-lg btn-secondary d-md-none col px-0" data-magistraal="page-back-button" onclick="magistraal.page.back();">
            <i class="fal fa-arrow-left"></i>
        </button>
        <h2 data-magistraal="page-title" class="col px-1"></h2>
        <div class="ml-auto d-flex flex-row col col-md-6 col-lg-8 col-xl-6 px-0" data-magistraal="header-items">
            <div class="page-search-wrapper w-100">
                <input type="text" name="page-search" data-translation="generic.action.search" data-magistraal="page-search" data-magistraal-search-target="main" class="form-control">
            </div>
            <div data-magistraal="page-buttons-container" class="d-none d-md-flex flex-row-reverse"></div>
        </div>
    </header>
    <main data-magistraal="main"></main>
    <div data-magistraal="sidebar">
        <h3 data-magistraal="sidebar-title" class="mb-1 text-ellipsis"></h3>
        <p data-magistraal="sidebar-subtitle" class="text-muted"></p>
        <div data-magistraal="sidebar-table" class="sidebar-table"></div>
        <div data-magistraal="sidebar-actions" class="d-none d-md-flex"></div>
    </div>
    <footer>
        <div data-magistraal="page-buttons-container" class="flex-row d-md-none mr-auto"></div>
        <div data-magistraal="sidebar-actions" class="flex-row d-md-none mr-auto"></div>
        <div class="col pl-2 pr-0 ml-auto d-flex align-items-center justify-content-end" style="width: 0px;">
            <span data-magistraal="console"></span>
        </div>
    </footer>
    <div data-magistraal="templates" class="d-none" aria-hidden="true">
        <!-- ABSENCES -->
            <!-- Group -->
            <div data-magistraal-template="absences-group" class="absences-group">
                <h4 class="absences-group-title"></h4>
            </div>

            <!-- List item -->
            <div data-magistraal-template="absence-list-item" tabindex="0" class="absence-list-item list-item" onclick="magistraal.sidebar.selectFeed($(this));">
                <div class=" list-item-icon font-heading"></div>
                <p class="list-item-title "></p>
                <p class="list-item-content text-muted"></p>
            </div>
        
        <!-- ACCOUNT -->
            <!-- Account information -->
            <div data-magistraal-template="account-info" class="account-info row mx-n1">
                <div class="col-12 col-lg-6 p-1">
                    <div class="account-info-tile tile account-info-basic">
                        <h4 data-translation="account.info.basic.heading"></h4>
                        <!-- <div class="account-info-item account-info-picture" style="opacity: 0.01;"></div> -->
                        <div class="account-info-item account-info-name">
                            <h5 data-translation="account.info.basic.name"></h5>
                            <span></span>
                        </div>
                        <div class="account-info-item account-info-name-official">
                            <h5 data-translation="account.info.basic.name_official"></h5>
                            <span></span>
                        </div>
                        <div class="account-info-item account-info-birth-date">
                            <h5 data-translation="account.info.basic.birth_date"></h5>
                            <span></span>
                        </div>
                        <div class="account-info-item account-info-address">
                            <h5 data-translation="account.info.basic.address"></h5>
                            <span></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6 p-1">
                    <div class="account-info-tile tile account-info-contact">
                        <h4 data-translation="account.info.contact.heading"></h4>
                        <div class="account-info-item account-info-email">
                            <h5 data-translation="account.info.contact.email"></h5>
                            <span></span>
                        </div>
                        <div class="account-info-item account-info-phone">
                            <h5 data-translation="account.info.contact.phone"></h5>
                            <span></span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6 p-1">
                    <div class="account-info-tile tile account-info-education">
                        <h4 data-translation="account.info.education.heading"></h4>
                        <div class="account-info-item account-info-study">
                            <h5 data-translation="account.info.education.study"></h5>
                            <span></span>
                        </div>
                        <div class="account-info-item account-info-curriculum">
                            <h5 data-translation="account.info.education.curriculum"></h5>
                            <span></span>
                        </div>
                        <div class="account-info-item account-info-class">
                            <h5 data-translation="account.info.education.class"></h5>
                            <span></span>
                        </div>
                    </div>
                </div>
            </div>

        <!-- APPOINTMENTS -->
            <!-- Group -->
            <div data-magistraal-template="appointments-group" class="appointments-group">
                <h4 class="appointments-group-title"></h4>
            </div>

            <!-- List item -->
            <div data-magistraal-template="appointment" tabindex="0" class="appointment-list-item list-item" onclick="magistraal.sidebar.selectFeed($(this)); magistraal.appointments.view($(this).attr('data-id'));">
                <div 
                    data-magistraal-tooltip="appointment_finish"
                    onclick="event.stopPropagation(); if(!magistraalStorage.get('nav_open')) { magistraal.appointments.finish($(event.target).parents('.appointment').attr('data-id'), ($(event.target).parents('.appointment').attr('data-finished') != 'true')); }"
                    class="list-item-icon font-heading">
                </div>
                <p class="list-item-title">
                    <span class="appointment-designation"></span>
                    <span class="bullet"></span>
                    <span class="appointment-time"></span>
                </p>
                <span class="list-item-content text-muted"></span>
                <div class="list-item-actions">
                    <span class="list-item-action list-item-action-primary appointment-info-type" 
                          data-magistraal-tooltip="appointment_finish"
                          onclick="event.stopPropagation(); magistraal.appointments.finish($(this).parents('.appointment').attr('data-id'), ($(this).parents('.appointment').attr('data-finished') != 'true'));">
                    </span>
                    <a class="list-item-action list-item-action-square appointment-meeting-link" 
                       onclick="event.stopPropagation(); magistraal.appointments.joinMeeting($(this).parents('.appointment').attr('data-meeting'));"
                       data-magistraal-tooltip="appointment_join_ms_teams"
                       target="_blank" 
                       rel="noopener norefferer nofollow">
                        <img src="../assets/images/join_ms_teams_meeting.svg" alt="Online les in Microsoft Teams">
                    </a>
                </div>
            </div>

        <!-- GRADES -->
            <!-- List item -->
            <div data-magistraal-template="grade-list-item" tabindex="0" class="grade-list-item list-item list-item-flex" onclick="magistraal.sidebar.selectFeed($(this));">
                <div class="grade-value list-item-icon font-heading"></div>
                <div class="list-item-title mx-n1">
                    <span class="col-9 col-sm-6 col-md-7 col-lg-5 text-ellipsis px-1 grade-subject"></span>
                    <span class="col-3 col-sm-2 col-md-2 col-lg-3 text-ellipsis px-1" data-translation="grades.grade.info.weight"></span>
                    <span class="col-4 col-sm-4 col-md-3 col-lg-4 text-ellipsis px-1 d-none d-sm-block" data-translation="grades.grade.info.entered_at"></span>
                    <!-- <div class="d-none d-lg-block px-1  col-lg-2 text-ellipsis" data-translation="grades.grade.info.subject_average"></div> -->
                </div>
                <div class="list-item-content mx-n1 text-muted">
                    <div class="col-9 col-sm-6 col-md-7 col-lg-5 grade-description text-ellipsis px-1"></div>
                    <div class="col-3 col-sm-2 col-md-2 col-lg-3 grade-weight text-ellipsis px-1"></div>
                    <div class="col-4 col-sm-4 col-md-3 col-lg-4 grade-entered-at text-ellipsis px-1 d-none d-sm-block"></div>
                    <!-- <div class="d-none d-lg-block px-1  col-lg-2 grade-subject-average text-ellipsis"></div> -->
                </div>
            </div>

            <!-- Grade overview -->
            <div data-magistraal-template="grade-overview" data-magistraal="grade-overview" class="grade-overview">
                <div class="grade-overview-terms"></div>
                <div class="grade-overview-subjects"></div>
                <div class="grade-overview-main"></div>
            </div>

            <!-- Grade overview subject -->
            <div data-magistraal-template="grade-overview-subject" class="grade-overview-subject text-ellipsis"></div>

             <!-- Grade overview main row -->
            <div data-magistraal-template="grade-overview-main-row" class="grade-overview-main-row d-flex flex-row flex-nowrap"></div>

            <!-- Grade overview term header -->
            <div data-magistraal-template="grade-overview-term" class="grade-overview-term">
                <span class="grade-overview-term-header"></span>
                <div class="grade-overview-term-columns"></div>
            </div>

            <!-- Grade overview column -->
            <div data-magistraal-template="grade-overview-term-column" class="grade-overview-term-column">
                <span class="grade-overview-term-column-name"></span>
            </div>

        <!-- MESSAGES -->
            <!-- List item -->
            <div data-magistraal-template="message-list-item" tabindex="0" class="message-list-item list-item list-item-flex" onclick="magistraal.sidebar.selectFeed($(this)); magistraal.messages.view($(this).attr('data-id'), ($(this).attr('data-read') == 'true' ? true : false));">
                <div class="list-item-icon message-list-item-icon"></div>
                <p class="list-item-title message-list-item-title"></p>
                <p class="list-item-content message-list-item-content text-muted"></p>
                <div class="message-list-item-actions list-item-actions">
                    <button 
                        class="message-list-item-action list-item-action list-item-action-square message-action-reply" 
                        data-magistraal-tooltip="message_reply">
                        <i class="fal fa-reply"></i>
                    </button>
                    <button 
                        class="message-list-item-action list-item-action list-item-action-square message-action-reply-all" 
                        data-magistraal-tooltip="message_reply_all">
                        <i class="fal fa-reply-all"></i>
                    </button>
                    <button 
                        class="message-list-item-action list-item-action list-item-action-square message-action-forward" 
                        data-magistraal-tooltip="message_forward">
                        <i class="fal fa-arrow-alt-right"></i>
                    </button>
                </div>
            </div>

        <!-- SETTINGS -->
            <!-- Setting category -->
            <div data-magistraal-template="setting-category" tabindex="0" class="setting-category list-item">
                <div class="list-item-icon setting-category-icon"></div>
                <p class="list-item-title setting-category-title"></p>
                <p class="list-item-content setting-category-content text-muted"></p>
            </div>

            <!-- Setting list item -->
            <div data-magistraal-template="setting-list-item" class="list-item list-item-with-input setting-list-item">
                <div class="list-item-icon"></div>
                <p class="list-item-title"></p>
                <div class="list-item-content text-muted" style="white-space: normal;"></div>
            </div>

        <!-- SOURCES -->
            <!-- Source list item -->
            <div data-magistraal-template="source-list-item" tabindex="0" class="source-list-item list-item">
                <div class="list-item-icon"></div>
                <p class="list-item-title"></p>
                <p class="list-item-content text-muted"></p>
            </div>

        <!-- SIDEBAR -->
            <!-- Table key -->
            <h5 data-magistraal-template="sidebar-table-key" class="sidebar-table-cell sidebar-table-key"></h5>
            
            <!-- Table value -->
            <div data-magistraal-template="sidebar-table-value" class="sidebar-table-cell sidebar-table-value"></div>

              <!-- Action -->
            <div data-magistraal-template="sidebar-action" class="sidebar-action btn btn-with-icon"></div>

        <!-- DIALOG -->
            <div data-magistraal-template="dialog" class="dialog">
                <div class="dialog-main">
                    <h3 class="dialog-title"></h3>
                    <span class="dialog-description text-muted"></span>
                </div>
                <div class="dialog-footer">
                    <button class="btn btn-secondary btn-with-icon" data-dialog-action="yes">
                        <i class="btn-icon fal fa-check"></i>
                        <span class="btn-text" data-translation="generic.bool.true"></span>
                    </button>
                    <button class="btn btn-danger btn-with-icon" data-dialog-action="no">
                        <i class="btn-icon fal fa-times"></i>
                        <span class="btn-text" data-translation="generic.bool.false"></span>
                    </button>
                </div>
            </div>
        
        <!-- PAGE BUTTONS -->
            <!-- Appointments -->
            <div data-magistraal-template="page-buttons-appointments/list">
                <button class="btn btn-secondary btn-with-icon online-only" onclick="magistraal.popup.open('appointments_create_appointment');">
                    <i class="btn-icon fal fa-calendar-plus"></i>
                    <span class="btn-text" data-translation="appointments.create_appointment"></span>
                </button>
                <!-- <button class="btn btn-secondary disabled">Vandaag</button> -->
            </div>

            <!-- Grades list -->
            <div data-magistraal-template="page-buttons-grades/list">
                <!-- <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'grades/overview'});">
                    <i class="btn-icon fal fa-table"></i>
                    <span class="btn-text" data-translation="grades.overview" ></span>
                </button> -->
                <!-- <button class="btn btn-secondary" data-translation="grades.to_grade_calculator"></button> -->
            </div>

            <!-- Grade overview -->
            <div data-magistraal-template="page-buttons-grades/overview">
                <!-- <button class="btn btn-secondary" data-translation="grades.to_list" onclick="magistraal.page.load({page: 'grades');"></button> -->
                <!-- <button class="btn btn-secondary" data-translation="grades.to_grade_calculator"></button> -->
            </div>

            <!-- Messages -->
            <div data-magistraal-template="page-buttons-messages/list">
                <button class="btn btn-secondary btn-with-icon online-only" onclick="magistraal.popup.open('messages_write_message');">
                    <i class="btn-icon fal fa-pencil-alt"></i>
                    <span class="btn-text" data-translation="messages.write_message" ></span>
                </button>
            </div>
        </div>
    </div>
    <div data-magistraal="popups">
        <!-- APPOINTMENTS -->
        <!-- Create appointment popup -->
        <div data-magistraal-popup="appointments_create_appointment" class="popup">
            <form data-magistraal="form-appointments_create_appointment">
                <div class="popup-main">
                    <input type="hidden" name="id">
                    <h3 class="popup-title" data-translation="appointments.popup.create_appointment.title"></h3>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="appointments.popup.create_appointment.item.date.title"></span>
                        </h5>
                        <div class="popup-item-value">
                            <input name="date" type="date" class="form-control">
                        </div>
                    </div>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="appointments.popup.create_appointment.item.time.title"></span>
                        </h5>
                        <div class="popup-item-value d-flex flex-row">
                            <div class="form-check form-check-inline mr-0">
                                <input name="start" type="time" id="appointment-start-time" class="form-control w-auto" step="60" value="08:00">
                            </div>
                            <div class="form-check form-check-inline mr-0">
                                <label class="form-check-label mr-2" for="appointment-end-time" data-translation="appointments.popup.create_appointment.item.time_end.title"></label>
                                <input name="end" type="time" id="appointment-end-time" class="form-control w-auto" step="60" value="09:00">
                            </div>
                        </div>
                    </div>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="appointments.popup.create_appointment.item.facility.title"></span>
                        </h5>
                        <div class="popup-item-value">
                            <input name="facility" data-translation="appointments.popup.create_appointment.item.facility.title" type="text" class="form-control">
                        </div>
                    </div>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="appointments.popup.create_appointment.item.description.title"></span>
                        </h5>
                        <div class="popup-item-value">
                            <input name="description" data-translation="appointments.popup.create_appointment.item.description.title" type="text" class="form-control">
                        </div>
                    </div>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="appointments.popup.create_appointment.item.content.title"></span>
                        </h5>
                        <div class="popup-item-value">
                            <textarea name="content" data-translation="appointments.popup.create_appointment.item.content.title" class="rich-editor"></textarea>
                        </div>
                    </div>
                </div>
                <div class="popup-footer">
                    <button type="button" class="btn btn-danger btn-with-icon" data-popup-action="cancel">
                        <i class="btn-icon fal fa-times"></i>
                        <span class="btn-text" data-translation="generic.action.cancel"></span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-with-icon" data-popup-action="confirm" onclick="magistraal.appointments.create($(this).parents('form').formSerialize(), $(this).parents('form'));">
                        <i class="btn-icon fal fa-check"></i>
                        <span class="btn-text" data-translation="generic.action.save"></span>
                    </button>
                </div>
            </form>
        </div>
        <!-- MESSAGES -->
        <!-- Write message popup -->
        <div data-magistraal-popup="messages_write_message" class="popup">
            <form data-magistraal="form-messages_write_message">
                <div class="popup-main">
                    <h3 class="popup-title" data-translation="messages.popup.write_message.title"></h3>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="messages.popup.write_message.item.to.title"></span>
                        </h4>
                        <div class="popup-item-value">
                            <input name="to" data-magistraal-search-api="people" data-translation="messages.popup.write_message.item.to.title" type="text" class="form-control input-search input-tags">
                        </div>
                    </div>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="messages.popup.write_message.item.cc.title"></span>
                        </h5>
                        <div class="popup-item-value">
                            <input name="cc" data-magistraal-search-api="people" data-translation="messages.popup.write_message.item.cc.title" type="text" class="form-control input-search input-tags" data-magistraal-search-api="people">
                        </div>
                    </div>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="messages.popup.write_message.item.bcc.title"></span>
                        </h5>
                        <div class="popup-item-value">
                            <input name="bcc" data-magistraal-search-api="people" data-translation="messages.popup.write_message.item.bcc.title" type="text" class="form-control input-search input-tags" data-magistraal-search-api="people">
                        </div>
                    </div>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="messages.popup.write_message.item.subject.title"></span>
                        </h5>
                        <div class="popup-item-value">
                            <input name="subject" data-translation="messages.popup.write_message.item.subject.title" type="text" class="form-control">
                        </div>
                    </div>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="messages.popup.write_message.item.content.title"></span>
                        </h5>
                        <div class="popup-item-value">
                            <textarea name="content" data-translation="messages.popup.write_message.item.content.title" class="rich-editor"></textarea>
                        </div>
                    </div>
                </div>
                <div class="popup-footer">
                    <button type="button" class="btn btn-danger btn-with-icon" data-popup-action="cancel">
                        <i class="btn-icon fal fa-times"></i>
                        <span class="btn-text" data-translation="generic.action.cancel"></span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-with-icon" data-popup-action="confirm" onclick="magistraal.messages.send($(this).parents('form').formSerialize(), $(this).parents('form'));">
                        <i class="btn-icon fal fa-paper-plane"></i>
                        <span class="btn-text" data-translation="generic.action.send"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div data-magistraal="popup-backdrop" class="popup-backdrop"></div>
    <div data-magistraal="dialog-backdrop" class="dialog-backdrop"></div>
    <div data-magistraal="page-search-no-matches" class="page-search-no-matches full-page">
        <i class="fal fa-search fa-8x text-secondary"></i>
        <h2 data-translation="tooltip.hint.search_no_matches" class="mt-4 text-center"></h2>
    </div>
    <span data-magistraal="tooltip" style="display: none;"></span>
</body>
</html>