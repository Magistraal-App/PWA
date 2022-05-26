<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    header('Content-Type: text/html;'); 
    define('ALLOW_EXIT', false);

    // Start session to get user uuid
    \Magister\Session::start();

    if(!isset(\Magister\Session::$userUuid)) {
        header('Location: ../login/');
        exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Home | Magistraal</title>

    <?php echo(\Magistraal\Frontend\assetsHTML()); ?>
    
    <script>
        // Werk de UI bij gebaseerd op de instellingen van de gebruiker
        magistraal.settings.updateClient(<?php echo(json_encode(\Magistraal\User\Settings\get_all(\Magister\Session::$userUuid ?? null))); ?>);
        
        // Stuur gebruiker naar loginpagina als er geen token is
        if(!magistraal.token.isSet()) {
            magistraal.page.load({
                page: 'login'
            });
        }

        // Laad Magistraal
        $(document).ready(function() {
            magistraal.load({
                version: '<?php echo(VERSION); ?>',
                doPreCache: true
            });
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
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'appointments/list'});" data-magistraal="nav-item-appointments">
                <i class="fal fa-calendar-alt"></i>
                <span data-translation="generic.page.appointments/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'grades/list'});" data-magistraal="nav-item-grades">
                <i class="fal fa-award"></i>
                <span data-translation="generic.page.grades/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'absences/list'});" data-magistraal="nav-item-absences">
                <i class="fal fa-calendar-times"></i>
                <span data-translation="generic.page.absences/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'sources/list'});" data-magistraal="nav-item-sources">
                <i class="fal fa-folder"></i>
                <span data-translation="generic.page.sources/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'learningresources/list'});" data-magistraal="nav-item-learningresources">
                <i class="fal fa-books"></i>
                <span data-translation="generic.page.learningresources/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse text-muted-inverse" #onclick="magistraal.page.load({'studyguides'});" data-magistraal="nav-item-studyguides">
                <i class="fal fa-map-signs"></i>
                <span data-translation="generic.page.studyguides/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse text-muted-inverse" #onclick="magistraal.page.load({'tasks'});" data-magistraal="nav-item-tasks">
                <i class="fal fa-pencil-alt"></i>
                <span data-translation="generic.page.tasks/list.title"></span>
            </li>
        </ul>
        <ul class="nav-items mt-auto">
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'messages/list'});" data-magistraal="nav-item-messages">
                <i class="fal fa-envelope"></i>
                <span data-translation="generic.page.messages/list.title"></span>
                <span class="nav-item-badge btn btn-sm btn-square btn-primary" data-magistraal="unread-messages-amount-badge" style="display: none;"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse" onclick="magistraal.page.load({page: 'account/list'});" data-magistraal="nav-item-account">
                <i class="fal fa-user"></i>
                <span data-translation="generic.page.account/list.title"></span>
            </li>
            <li tabindex="0" class="nav-item text-inverse online-only" onclick="magistraal.page.load({page: 'settings/list'});" data-magistraal="nav-item-settings">
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
        <div class="ml-auto d-flex flex-row col col-md-8 col-lg-8 col-xl-7 px-0" data-magistraal="header-items">
            <div class="page-search-wrapper w-100">
                <input type="text" name="page-search" data-translation="generic.action.search" data-magistraal="page-search" data-magistraal-search-target="main" class="form-control">
            </div>
            <div data-magistraal="page-buttons-container" class="d-none d-md-flex flex-row"></div>
        </div>
    </header>
    <main data-magistraal="main"></main>
    <div data-magistraal="sidebar">
        <h3 data-magistraal="sidebar-title" class="mb-1 text-ellipsis"></h3>
        <p data-magistraal="sidebar-subtitle" class="text-muted"></p>
        <div data-magistraal="sidebar-table" class="sidebar-table"></div>
        <div data-magistraal="sidebar-actions" class="flex-row d-none d-md-flex scrollbar-hidden"></div>
    </div>
    <footer class="scrollbar-hidden">
        <div data-magistraal="page-buttons-container" class="flex-row d-md-none mr-auto"></div>
        <div data-magistraal="sidebar-actions" class="flex-row d-md-none mr-auto scrollbar-hidden"></div>
        <div class="col px-0 ml-auto d-flex align-items-center justify-content-end" style="width: 0px;">
            <span data-magistraal="console"></span>
        </div>
    </footer>
    <div data-magistraal="templates" class="d-none" aria-hidden="true">
        <!-- ABSENCES -->
            <!-- Group -->
            <div data-magistraal-template="absences-group" class="absences-group list-item-group">
                <h4 class="absences-group-title"></h4>
            </div>

            <!-- List item -->
            <div data-magistraal-template="absence-list-item" tabindex="0" class="absence-list-item list-item" onclick="magistraal.sidebar.selectFeed($(this));">
                <div class="list-item-icon font-heading"></div>
                <p class="list-item-title"></p>
                <p class="list-item-content text-muted"></p>
            </div>
        
        <!-- ACCOUNT -->
            <!-- Group -->
            <div data-magistraal-template="account-group" class="account-group list-item-group">
                <h4 class="account-group-title"></h4>
            </div>

            <!-- List item -->
            <div data-magistraal-template="account-list-item" tabindex="0" class="account-list-item list-item">
                <div class="list-item-icon"></div>
                <p class="list-item-title"></p>
                <p class="list-item-content text-muted"></p>
            </div>

        <!-- APPOINTMENTS -->
            <!-- Group -->
            <div data-magistraal-template="appointments-group" class="appointments-group list-item-group">
                <h4 class="appointments-group-title"></h4>
            </div>

            <!-- List item -->
            <div data-magistraal-template="appointment" tabindex="0" class="appointment-list-item list-item" onclick="magistraal.sidebar.selectFeed($(this)); magistraal.appointments.view($(this).attr('data-id'));">
                <div class="list-item-icon font-heading"></div>
                <p class="list-item-title">
                    <span class="appointment-description"></span>
                    <span class="bullet"></span>
                    <span class="appointment-time"></span>
                </p>
                <span class="list-item-content text-muted"></span>
                <div class="list-item-actions">
                    <span class="list-item-action list-item-action-primary appointment-info-type"></span>
                </div>
            </div>

        <!-- GRADES -->
            <!-- Group -->
            <div data-magistraal-template="grades-group" class="grades-group list-item-group">
                <h4 class="grades-group-title"></h4>
            </div>

            <!-- List item -->
            <div data-magistraal-template="grade-list-item" tabindex="0" class="grade-list-item list-item list-item-flex" onclick="magistraal.sidebar.selectFeed($(this));">
                <div class="grade-value list-item-icon font-heading"></div>
                <div class="list-item-title mx-n1">
                    <span class="col-9 col-sm-6 col-md-7 col-lg-5 text-ellipsis px-1 grade-subject"></span>
                    <span class="col-3 col-sm-2 col-md-2 col-lg-3 text-ellipsis px-1" data-translation="grades.grade.info.weight"></span>
                    <span class="col-4 col-sm-4 col-md-3 col-lg-4 text-ellipsis px-1 d-none d-sm-block" data-translation="grades.grade.info.entered_at"></span>
                </div>
                <div class="list-item-content mx-n1 text-muted">
                    <div class="col-9 col-sm-6 col-md-7 col-lg-5 grade-description text-ellipsis px-1"></div>
                    <div class="col-3 col-sm-2 col-md-2 col-lg-3 grade-weight text-ellipsis px-1"></div>
                    <div class="col-4 col-sm-4 col-md-3 col-lg-4 grade-entered-at text-ellipsis px-1 d-none d-sm-block"></div>
                </div>
            </div>

            <!-- Grade overview list item -->
            <div data-magistraal-template="grade-overview-list-item" tabindex="0" class="grade-overview-list-item list-item list-item-flex" onclick="magistraal.sidebar.selectFeed($(this));">
                <div class="grade-value list-item-icon font-heading"></div>
                <div class="list-item-title mx-n1">
                    <span class="col-9 col-sm-6 col-md-7 col-lg-5 text-ellipsis px-1 grade-subject"></span>
                    <span class="col-5 col-sm-6 col-md-5 col-lg-7 text-ellipsis px-1 d-none d-sm-block" data-translation="grades.grade.info.entered_at"></span>
                </div>
                <div class="list-item-content mx-n1 text-muted">
                    <div class="col-9 col-sm-6 col-md-7 col-lg-5 grade-description text-ellipsis px-1"></div>
                    <div class="col-5 col-sm-6 col-md-5 col-lg-7 grade-entered-at text-ellipsis px-1 d-none d-sm-block"></div>
                </div>
            </div>

            <!-- Grade overview list item average -->
            <div data-magistraal-template="grade-overview-list-item-average" tabindex="0" class="grade-overview-list-item grade-overview-list-item-average list-item">
                <div class="grade-value list-item-icon font-heading"></div>
                <div class="list-item-title mx-n1">
                    <span class="col-9 col-sm-6 col-md-7 col-lg-5 text-ellipsis px-1 grade-subject"></span>
                </div>
                <div class="list-item-content mx-n1 text-muted">
                    <div class="col-9 col-sm-6 col-md-7 col-lg-5 grade-description text-ellipsis px-1"></div>
                </div>
            </div>

            <!-- Grade calculator list item -->
            <!-- <div data-magistraal-template="grade-calculator-list-item">
                <input type="text" data-translation="grades.calculator.weight">
                <input type="text" data-translation="grades.calculator.grade">
            </div> -->

        <!-- LEARNING RESOURCES -->
            <!-- List item -->
            <div data-magistraal-template="learning-resource" tabindex="0" class="learning-resource-list-item list-item" onclick="magistraal.sidebar.selectFeed($(this));">
                <div class="list-item-icon font-heading"></div>
                <p class="list-item-title"></p>
                <span class="list-item-content text-muted"></span>
            </div>

        <!-- MESSAGES -->
            <!-- List item -->
            <div data-magistraal-template="message-list-item" tabindex="0" class="message-list-item list-item list-item-flex" onclick="magistraal.sidebar.selectFeed($(this)); magistraal.messages.view($(this).attr('data-id'), ($(this).attr('data-read') == 'true' ? true : false));">
                <div class="list-item-icon" data-message-read="true" data-message-has-attachments="false">
                    <i class="fal fa-envelope"></i>
                </div>
                <div class="list-item-icon" data-message-read="false" data-message-has-attachments="false">
                    <i class="fal fa-envelope-open"></i>
                </div>
                <div class="list-item-icon" data-message-has-attachments="true">
                    <i class="fal fa-paperclip"></i>
                </div>
                <p class="list-item-title"></p>
                <p class="list-item-content text-muted"></p>
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
                        <i class="btn-icon fal fa-check-circle"></i>
                        <span class="btn-text" data-translation="generic.bool.true"></span>
                    </button>
                    <button class="btn btn-danger btn-with-icon" data-dialog-action="no">
                        <i class="btn-icon fal fa-times-circle"></i>
                        <span class="btn-text" data-translation="generic.bool.false"></span>
                    </button>
                </div>
            </div>
        
        <!-- PAGE BUTTONS -->
            <!-- Absences -->
            <div data-magistraal-template="page-buttons-absences/list">
                <!-- <button class="btn btn-secondary btn-with-icon" onclick="magistraal.popup.open('absences_select_year');">
                    <i class="btn-icon fal fa-calendar-star"></i>
                    <span class="btn-text" data-translation="absences.select_year"></span>
                </button> -->
                <!-- <button class="btn btn-secondary disabled">Vandaag</button> -->
            </div>
        
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
                <!-- <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'grades/calculator'});">
                    <i class="btn-icon fal fa-calculator-alt"></i>
                    <span class="btn-text" data-translation="grades.calculator" ></span>
                </button> -->
                <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'grades/overview'});">
                    <i class="btn-icon fal fa-columns"></i>
                    <span class="btn-text" data-translation="grades.overview" ></span>
                </button>
            </div>

            <!-- Grades overview -->
            <div data-magistraal-template="page-buttons-grades/overview">
                <!-- <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'grades/calculator'});">
                    <i class="btn-icon fal fa-calculator-alt"></i>
                    <span class="btn-text" data-translation="grades.calculator" ></span>
                </button> -->
                <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'grades/list'});">
                    <i class="btn-icon fal fa-list"></i>
                    <span class="btn-text" data-translation="grades.list" ></span>
                </button>                
            </div>

            <!-- Grades calculator -->
            <div data-magistraal-template="page-buttons-grades/calculator">
                <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'grades/list'});">
                    <i class="btn-icon fal fa-list"></i>
                    <span class="btn-text" data-translation="grades.list" ></span>
                </button>      
                <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'grades/overview'});">
                    <i class="btn-icon fal fa-columns"></i>
                    <span class="btn-text" data-translation="grades.overview" ></span>
                </button>
            </div>

            <!-- Messages -->
            <div data-magistraal-template="page-buttons-messages/list">
                <button class="btn btn-secondary btn-with-icon online-only" onclick="magistraal.popup.open('messages_write_message');">
                    <i class="btn-icon fal fa-pencil-alt"></i>
                    <span class="btn-text" data-translation="messages.write_message" ></span>
                </button>
                <!-- <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'messages/sent'});">
                    <i class="btn-icon fal fa-paper-plane"></i>
                    <span class="btn-text" data-translation="messages.sent" ></span>
                </button>
                <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'messages/bin'});">
                    <i class="btn-icon fal fa-trash"></i>
                    <span class="btn-text" data-translation="messages.bin" ></span>
                </button> -->
            </div>

            <!-- <div data-magistraal-template="page-buttons-messages/sent">
                <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'messages/list'});">
                    <i class="btn-icon fal fa-envelope"></i>
                    <span class="btn-text" data-translation="messages.inbox" ></span>
                </button>
                <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'messages/bin'});">
                    <i class="btn-icon fal fa-trash"></i>
                    <span class="btn-text" data-translation="messages.bin" ></span>
                </button>
            </div> -->

            <!-- <div data-magistraal-template="page-buttons-messages/bin">
                <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'messages/list'});">
                    <i class="btn-icon fal fa-envelope"></i>
                    <span class="btn-text" data-translation="messages.inbox" ></span>
                </button>
                <button class="btn btn-secondary btn-with-icon" onclick="magistraal.page.load({page: 'messages/sent'});">
                    <i class="btn-icon fal fa-paper-plane"></i>
                    <span class="btn-text" data-translation="messages.sent" ></span>
                </button>
            </div> -->

            <!-- Responsive Carousel Indicator Item -->
            <div data-magistraal-template="responsive-carousel-indicator-item" class="responsive-carousel-indicator-item"></div>
        </div>
    </div>
    <div data-magistraal="popups">
        <!-- ABSENCES -->
        <!-- Select year popup -->
        <div data-magistraal-popup="absences_select_year" class="popup popup-small">
            <form data-magistraal="form-absences_select_year">
                <div class="popup-main">
                    <h3 class="popup-title" data-translation="absences.popup.select_year.title"></h3>
                    <div class="popup-item">
                        <h5 class="popup-item-key">
                            <span data-translation="absences.popup.select_year.item.year.title"></span>
                        </h5>
                        <div class="popup-item-value">
                            <input type="text" name="year_to" class="input-search form-control w-100" data-magistraal-search-target="absences-select_year-years">
                            <script>
                                // Add search results to input
                                $(document).on('magistraal.ready', function() {
                                    const $input = $('[data-magistraal-search-target="absences-select_year-years"]');

                                    let results     = [];
                                    let currentYear = parseInt(new Date().getFullYear());
                                    
                                    for (let i = currentYear; i > 2012; i--) {
                                        results.push({
                                            title: `${i-1}/${i}`,
                                            value: i
                                        });
                                    }

                                    $input.data('searchInput').results.set(results);
                                })
                            </script>
                        </div>
                    </div>
                </div>
                <div class="popup-footer">
                    <button type="button" class="btn btn-danger btn-with-icon" data-popup-action="cancel">
                        <i class="btn-icon fal fa-times-circle"></i>
                        <span class="btn-text" data-translation="generic.action.cancel"></span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-with-icon" data-popup-action="confirm">
                        <i class="btn-icon fal fa-check-circle"></i>
                        <span class="btn-text" data-translation="generic.action.save"></span>
                    </button>
                </div>
            </form>
        </div>

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
                        <i class="btn-icon fal fa-times-circle"></i>
                        <span class="btn-text" data-translation="generic.action.cancel"></span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-with-icon" data-popup-action="confirm" onclick="magistraal.appointments.create($(this).parents('form').formSerialize(), $(this).parents('form'));">
                        <i class="btn-icon fal fa-check-circle"></i>
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
                        <i class="btn-icon fal fa-times-circle"></i>
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
    <!-- Reponsive Carousel indicator -->
    <div data-magistraal="responsive-carousel-indicator" class="responsive-carousel-indicator"></div>
    <span data-magistraal="tooltip" style="display: none;"></span>
</body>
</html>