<?php include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php"); header('Content-Type: text/html;'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Inloggen | Magistraal</title>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#202124">

    <link rel="manifest" href="../../manifest.json" />
   
    <?php echo(\Magistraal\Frontend\assetsHTML()); ?>

    <link rel="stylesheet" href="../assets/css/login.min.css">

    <script>
        $(document).ready(function() {
            magistraal.load(); 
        })

        $(document).on('magistraal.ready', function() {
            if(typeof magistraalPersistentStorage.getItem('token') != 'undefined') {
                magistraal.page.load('logout', {}, false)
            }
        })
    </script>
</head>
<body>
    <main>
        <form data-magistraal="login-form" action="login" onsubmit="magistraal.login.login($(this)); return false;" class="pt-5 container justify-content-center">
            <div class="row mb-3">
                <div class="col-12">
                    <h4 data-translation="login.hint.tenant"></h4>
                    <div class="input-search-wrapper">
                        <input type="text" data-magistraal-search-target="tenants-list" data-translation="login.placeholder.tenant" name="tenant" id="tenant" class="form-control" data-error="login_incorrect_tenant field_empty_tenant">
                        <div data-magistraal="tenants-list" class="input-search-results"></div>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <h4 data-translation="login.hint.username"></h4>
                    <input type="text" data-translation="login.placeholder.username" name="username" id="username" class="form-control" data-error="login_incorrect_username field_empty_username">
                </div>
                <div class="col-12 col-md-6">
                    <h4 data-translation="login.hint.password"></h4>
                    <input type="password" data-translation="login.placeholder.password" name="password" id="password" class="form-control" data-error="login_incorrect_passsword field_empty_password">
                </div>
            </div>
            <button data-translation="login.hint.submit" role="submit" class="btn btn-secondary" data-error="login_incorrect_passsword field_empty_password"></button>
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
    <footer class="bg-primary w-100 d-flex flex-row">
        <div class="ml-auto text-right d-flex flex-row h-100 align-items-center">
            <span data-magistraal="console"></span>
        </div>
    </footer>
</body>
</html>