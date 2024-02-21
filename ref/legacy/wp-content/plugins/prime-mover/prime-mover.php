<?php

/*
Plugin Name: Prime Mover
Plugin URI: https://codexonics.com/
Description: The simplest all-around WordPress migration tool/backup plugin. These support multisite backup/migration or clone WP site/multisite subsite.
Version: 1.9.6
Author: Codexonics
Author URI: https://codexonics.com/
Text Domain: prime-mover
Network: True
*/
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !defined( 'PRIME_MOVER_MAINPLUGIN_FILE' ) ) {
    define( 'PRIME_MOVER_MAINPLUGIN_FILE', __FILE__ );
}
if ( !defined( 'PRIME_MOVER_MAINDIR' ) ) {
    define( 'PRIME_MOVER_MAINDIR', dirname( PRIME_MOVER_MAINPLUGIN_FILE ) );
}

if ( function_exists( 'pm_fs' ) ) {
    pm_fs()->set_basename( true, PRIME_MOVER_MAINPLUGIN_FILE );
} else {
    require_once PRIME_MOVER_MAINDIR . '/global/PrimeMoverGlobalFunctions.php';
    if ( defined( 'PRIME_MOVER_PLUGIN_PATH' ) || defined( 'PRIME_MOVER_PLUGIN_UTILITIES_PATH' ) || defined( 'PRIME_MOVER_PLUGIN_CORE_PATH' ) || defined( 'PRIME_MOVER_THEME_CORE_PATH' ) ) {
        return;
    }
    include PRIME_MOVER_MAINDIR . '/global/PrimeMoverGlobalConstants.php';
    include PRIME_MOVER_MAINDIR . '/dependency-checks/PrimeMoverPHPVersionDependencies.php';
    include PRIME_MOVER_MAINDIR . '/dependency-checks/PrimeMoverWPCoreDepedencies.php';
    include PRIME_MOVER_MAINDIR . '/dependency-checks/PrimeMoverRequirementsCheck.php';
    include PRIME_MOVER_MAINDIR . '/dependency-checks/PrimeMoverPHPCoreFunctionDependencies.php';
    include PRIME_MOVER_MAINDIR . '/dependency-checks/PrimeMoverFileSystemDependencies.php';
    include PRIME_MOVER_MAINDIR . '/dependency-checks/PrimeMoverPluginSlugDependencies.php';
    include PRIME_MOVER_MAINDIR . '/dependency-checks/PrimeMoverCoreSaltDependencies.php';
    include PRIME_MOVER_MAINDIR . '/global/PrimeMoverGlobalDependencies.php';
    $primemover_global_dependencies = new PrimeMoverGlobalDependencies();
    $requisitecheck = $primemover_global_dependencies->primeMoverGetRequisiteCheck();
    if ( is_object( $requisitecheck ) && !$requisitecheck->passes() ) {
        return;
    }
    include PRIME_MOVER_MAINDIR . '/PrimeMoverLoader.php';
    if ( file_exists( PRIME_MOVER_PLUGIN_PATH . '/vendor/autoload.php' ) ) {
        require_once PRIME_MOVER_PLUGIN_PATH . '/vendor/autoload.php';
    }
    include PRIME_MOVER_MAINDIR . '/PrimeMoverFactory.php';
    include PRIME_MOVER_MAINDIR . '/engines/prime-mover-panel/prime-mover-panel.php';
}
