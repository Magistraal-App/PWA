<?php include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php"); header('Content-Type: text/html;'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Inloggen | Magistraal</title>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#000000">

    <link rel="manifest" href="/magistraal/manifest.json">
    <link rel="icon" type="image/ico" href="/magistraal/client/favicon.ico">
   
    <?php echo(\Magistraal\Frontend\assetsHTML()); ?>
   
    <link rel="stylesheet" href="/magistraal/client/assets/js/login.min.js">
    <link rel="stylesheet" href="/magistraal/client/assets/css/login.min.css">

    <script>
        $(document).ready(function() {
            magistraal.load({
                version: '<?php echo(VERSION); ?>',
                doPreCache: false
            });
            magistraal.element.get('version').text('v<?php echo(VERSION); ?>');
        })
    </script>
</head>
<body data-settings="<?php echo(http_build_query(\Magistraal\User\Settings\get_all(null), '', ',')); ?>">
    <main>
        <form action="/magistraal/api/login/" method="post" onsubmit="magistraal.login.login($(this).formSerialize());" class="pt-5 container justify-content-center">
            <div class="row mb-3">
                <div class="col-12">
                    <h4 data-translation="login.hint.tenant"></h4>
                    <div class="input-search-wrapper">
                        <input type="text" data-magistraal-search-api="tenants" data-translation="login.placeholder.tenant" name="tenant" id="tenant" class="form-control input-search" data-error="login_incorrect_tenant field_empty.tenant">
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <h4 data-translation="login.hint.username"></h4>
                    <input type="text" data-translation="login.placeholder.username" name="username" id="username" class="form-control" data-error="login_incorrect_username field_empty.username">
                </div>
                <div class="col-12 col-md-6">
                    <h4 data-translation="login.hint.password"></h4>
                    <input type="password" data-translation="login.placeholder.password" name="password" id="password" class="form-control" data-error="login_incorrect_passsword field_empty.password">
                </div>
            </div>
            <button type="submit" class="btn btn-secondary btn-with-icon" data-error="login_incorrect_passsword field_empty.password">
                <i class="btn-icon fal fa-arrow-right"></i>
                <span class="btn-text" data-translation="generic.action.login"></span>
            </button>
        </form>
        <div id="login-footer">
            <p class="text-muted mb-2 pt-4 pb-3">
                Made with <span style="font-family: 'Segoe UI Emoji';">❤</span> by Tjalling
            </p>
            <p class="text-muted">
                <span class="d-block d-md-none">Magistraal is niet verbonden met Iddink Group.</span>
                <span class="d-none d-md-block">Magistraal is niet verbonden met of onderdeel van Iddink Group.</span>
            </p>
        </div>
    </main>
    <footer class="scrollbar-hidden">
        <div class="col-auto px-0 mr-auto d-flex align-items-center text-muted">
            <span data-magistraal="version"></span>
        </div>
        <div class="col px-0 ml-auto d-flex align-items-center justify-content-end" style="width: 0px;">
            <span data-magistraal="console"></span>
        </div>
    </footer>
</body>
</html>