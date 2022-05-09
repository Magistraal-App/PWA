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
        $version    = str_replace('.', '-', \Magistraal\Config\get('version'));
        $production = \Magistraal\Config\get('production');
        $infix      = ($production === false ? '' : '.min');

        return '
            <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/bootstrap.min.css?v='.$version.'">
            <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/app'.$infix.'.css?v='.$version.'">

            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/fontawesome.min.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-sm'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/themes/dark'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/themes/silver'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/themes/light'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-md'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-lg'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-xl'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/js/froala-editor/css/themes/magistraal'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/js/froala-editor/css/froala_editor.pkgd.min.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            
            <noscript>
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/bootstrap.min.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/fontawesome.min.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/themes/dark'.$infix.'.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/themes/silver'.$infix.'.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/themes/light'.$infix.'.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/app'.$infix.'.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-sm'.$infix.'.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-md'.$infix.'.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-lg'.$infix.'.css?v='.$version.'">
                <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/media-xl'.$infix.'.css?v='.$version.'">
            </noscript>
           
            <script type="text/javascript" src="/magistraal/client/assets/js/froala-editor/js/froala_editor.pkgd.min.js?v='.$version.'" defer></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/froala-editor/js/languages/nl_NL.js?v='.$version.'" defer></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/jquery.min.js?v='.$version.'"></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/fullpage.min.js?v='.$version.'"></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/app'.$infix.'.js?v='.$version.'"></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/magistraal'.$infix.'.js?v='.$version.'"></script>
            
            <script>
                if(\'serviceWorker\' in navigator) {
                        navigator.serviceWorker.register(\'../../service-worker.js.php?v='.$version.'\').then(function(registration) {
                        console.log(\'Service worker registered. Scope is:\', registration.scope);
                    }).catch(function(error) {
                        console.error(\'Service worker registration failed, error:\', error);
                    });
                }
            </script>';
    }
?>