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
        $infix      = (\Magistraal\Config\get('debugging') === true ? '' : '.min');

        return '   
            <meta charset="UTF-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
            <meta name="theme-color" content="#000000">

            <link rel="manifest" href="/magistraal/manifest.json" />
            <link rel="icon" type="image/ico" href="/magistraal/client/assets/images/app/icons/favicon.ico">
            <link rel="apple-touch-icon" href="/magistraal/client/assets/images/app/icons/apple-touch-icon.png" />
            <link rel="apple-touch-icon" sizes="57x57" href="/magistraal/client/assets/images/app/icons/apple-touch-icon-57x57.png" />
            <link rel="apple-touch-icon" sizes="72x72" href="/magistraal/client/assets/images/app/icons/apple-touch-icon-72x72.png" />
            <link rel="apple-touch-icon" sizes="76x76" href="/magistraal/client/assets/images/app/icons/apple-touch-icon-76x76.png" />
            <link rel="apple-touch-icon" sizes="114x114" href="/magistraal/client/assets/images/app/icons/apple-touch-icon-114x114.png" />
            <link rel="apple-touch-icon" sizes="120x120" href="/magistraal/client/assets/images/app/icons/apple-touch-icon-120x120.png" />
            <link rel="apple-touch-icon" sizes="144x144" href="/magistraal/client/assets/images/app/icons/apple-touch-icon-144x144.png" />
            <link rel="apple-touch-icon" sizes="152x152" href="/magistraal/client/assets/images/app/icons/apple-touch-icon-152x152.png" />
            <link rel="apple-touch-icon" sizes="180x180" href="/magistraal/client/assets/images/app/icons/apple-touch-icon-180x180.png" />

            <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/bootstrap.min.css?v='.$version.'">
            <link rel="stylesheet" type="text/css" href="/magistraal/client/assets/css/app'.$infix.'.css?v='.$version.'">

            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/fontawesome.min.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-sm'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/themes/dark'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/themes/oled'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/themes/light'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-md'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-lg'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/css/media-xl'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/js/froala-editor/css/themes/magistraal'.$infix.'.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            <link rel="preload" type="text/css" href="/magistraal/client/assets/js/froala-editor/css/froala_editor.pkgd.min.css?v='.$version.'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">
            
            <script type="text/javascript" src="/magistraal/client/assets/js/froala-editor/js/froala_editor.pkgd.min.js?v='.$version.'" defer></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/froala-editor/js/languages/nl_NL.js?v='.$version.'" defer></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/jquery.min.js?v='.$version.'"></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/app'.$infix.'.js?v='.$version.'"></script>
            <script type="text/javascript" src="/magistraal/client/assets/js/magistraal'.$infix.'.js?v='.$version.'"></script>
            <script defer type="text/javascript" src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
            <script defer type="text/javascript" src="https://www.gstatic.com/firebasejs/8.10.1/firebase-messaging.js"></script>
            <script defer type="text/javascript" src="/magistraal/client/assets/js/firebase'.$infix.'.js?v='.$version.'"></script>';
    }
?>