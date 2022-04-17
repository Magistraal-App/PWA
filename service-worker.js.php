<?php 
    include_once("{$_SERVER['DOCUMENT_ROOT']}/magistraal/autoload.php");
    header('Content-Type: text/javascript;'); 
?>
<?php 
    $assets = \Magistraal\Frontend\assets([
        CLIENT.'/',
        CLIENT.'/login/index.php',
        CLIENT.'/main/index.php',
        CLIENT.'/offline/index.php',
        CLIENT.'/assets/webfonts/patua-one-regular/400.woff2',
        CLIENT.'/assets/webfonts/patua-one-regular/400.woff',
        CLIENT.'/assets/webfonts/rubik-regular/400.woff2',
        CLIENT.'/assets/webfonts/rubik-regular/400.woff'
    ]);

    $app_name = strtolower(\Magistraal\Config\get('name'));
    $version  = \Magistraal\Config\get('version') ?? '0';

    // Print out javascript
    echo("const cacheName = '{$app_name}-{$version}';\n");
    echo("const resources = ".json_encode($assets, JSON_UNESCAPED_SLASHES).";\n");
?>

<?php echo(file_get_contents('service-worker.js')); ?>