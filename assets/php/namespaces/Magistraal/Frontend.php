<?php 
    namespace Magistraal\Frontend;

    function assets($assets = []) {
        $scripts     = glob(CLIENT.'/assets/js/*.min.js');
        $stylesheets = glob(CLIENT.'/assets/css/*.min.css');
        $themes = glob(CLIENT.'/assets/css/themes/*.min.css');

        $assets = array_merge($assets, $scripts, $stylesheets, $themes);

        array_walk($assets, function(&$asset) {
            $asset = '.'.str_replace(ROOT, '', $asset);
        });

        return $assets;
    }

    function assetsHTML() {
        return '
            <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/bootstrap.min.css">
            <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/app.css">

            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/fontawesome.min.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-sm.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/themes/dark.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-md.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-lg.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-xl.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/froala.min.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            
            <noscript>
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/bootstrap.min.css">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/fontawesome.min.css">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/themes/dark.css">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/app.css">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-sm.css">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-md.css">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-lg.css">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-xl.css">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/froala.min.css">
            </noscript>

            <script type="text/javascript" src="/magistraal/client/assets/js/jquery.min.js"></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/app.js" defer></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/magistraal.js"></script>
            
            <script>
                if(\'serviceWorker\' in navigator) {
                    navigator.serviceWorker.register(\'../../service-worker.js.php\').then(function(registration) {
                        console.log(\'Registration successful, scope is:\', registration.scope);
                    }).catch(function(error) {
                        console.log(\'Service worker registration failed, error:\', error);
                    });
                }
            </script>
        ';
    }
?>