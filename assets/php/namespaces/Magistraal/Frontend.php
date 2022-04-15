<?php 
    namespace Magistraal\Frontend;

    function assets($assets = []) {
        $scripts     = glob(CLIENT.'/assets/js/*.min.js');
        $stylesheets = glob(CLIENT.'/assets/css/*.min.css');
        $themes = glob(CLIENT.'/assets/css/themes/*.min.css');

        $assets = array_merge($assets, $scripts, $stylesheets, $themes);

        array_walk($assets, function(&$asset) {
            $asset = WEBROOT.str_replace(ROOT, '', $asset);
        });

        return $assets;
    }

    function assetsHTML() {
        $version = str_replace('.', '-', \Magistraal\Config\get('version'));
        return '
            <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/bootstrap.min.css?v='.$version.'">
            <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/app.css?v='.$version.'">

            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/fontawesome.min.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-sm.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/themes/dark.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-md.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-lg.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-xl.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/js/froala-editor/css/themes/magistraal.min.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/js/froala-editor/css/froala_editor.pkgd.min.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            
            <noscript>
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/bootstrap.min.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/fontawesome.min.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/themes/dark.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/app.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-sm.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-md.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-lg.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-xl.css?v='.$version.'">
            </noscript>
           
            <script type="text/javascript" src="/magistraal/client/assets/js/froala-editor/js/froala_editor.pkgd.min.js?v='.$version.'"></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/froala-editor/js/languages/nl_NL.js?v='.$version.'"></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/jquery.min.js?v='.$version.'"></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/app.js?v='.$version.'" defer></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/magistraal.js?v='.$version.'"></script>
            
            <script>
                if(\'serviceWorker\' in navigator) {
                    navigator.serviceWorker.register(\'../../service-worker.js.php\').then(function(registration) {
                        console.log(\'Service worker registered. Scope is:\', registration.scope);
                    }).catch(function(error) {
                        console.error(\'Service worker registration failed, error:\', error);
                    });
                }
            </script>
        ';
    }
?>