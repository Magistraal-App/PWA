<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    header('Content-Type: text/html;'); 
    define('ALLOW_EXIT', false);

    // Start session to get user uuid
    \Magister\Session::start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Offline | Magistraal</title>

    <?php echo(\Magistraal\Frontend\assetsHTML()); ?>
    
    <script>
        if(typeof magistraal != 'undefined') {
            // Werk de UI bij gebaseerd op de instellingen van de gebruiker
            magistraal.settings.updateClient(<?php echo(json_encode(\Magistraal\User\Settings\get_all(\Magister\Session::$userUuid ?? null))); ?>);
        }
    </script>
</head>
<body data-settings="<?php echo(http_build_query(\Magistraal\User\Settings\get_all(\Magister\Session::$userUuid ?? null), '', ',')); ?>">
    <main class="h-100 w-100 d-flex flex-column justify-content-center align-items-center">
        <h1 data-translation="offline.header">Offline</h1>
        <p class="mb-4" data-translation="offline.content">Het lijkt erop dat je op dit moment geen internetverbinding hebt.</p>
        <button class="btn btn-secondary" data-translation="generic.action.retry" onclick="retryConnect();">Opnieuw proberen</button>
    </main>
    <script>
        // Stuur de gebruiker door als deze weer internet heeft
        document.addEventListener('online', function() {
            retryConnect();
        })

        function retryConnect() {
            window.location.replace('../main/');
        }
    </script>
    <style>
        html, body, main {
            height: 100%;
            width: 100%;
            overflow: hidden !important;
            border: 0px !important;
        }

        body {
            background: #212830;
            color: #ffffff;
            display: block !important;
        }

        main {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        h1, p {
            margin: 0 0 0.5rem 0;
            text-align: center;
        }

        p {
            margin-bottom: 2rem;
        }

        button {
            color: #ffffff;
            background: #0058db;
            padding: 0.5rem;
            border: 0;
            border-radius: 0.5rem;
            cursor: pointer;
        }
    </style>
</body>