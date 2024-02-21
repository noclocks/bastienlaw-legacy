<?php

/****************************************************
 * PRIME MOVE GLOBAL FUNCTIONS
 * Globally accessible by Prime Mover clases/scripts
 * **************************************************
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( !function_exists( 'pm_fs' ) ) {
    // Create a helper function for easy SDK access.
    function pm_fs()
    {
        global  $pm_fs ;
        
        if ( !isset( $pm_fs ) ) {
            // Activate multisite network integration.
            if ( !defined( 'WP_FS__PRODUCT_3826_MULTISITE' ) ) {
                define( 'WP_FS__PRODUCT_3826_MULTISITE', true );
            }
            // Include Freemius SDK.
            require_once PRIME_MOVER_MAINDIR . '/freemius/start.php';
            $pm_fs = fs_dynamic_init( array(
                'id'             => '3826',
                'slug'           => 'prime-mover',
                'premium_slug'   => 'prime-mover-pro',
                'type'           => 'plugin',
                'public_key'     => 'pk_a69fd5401be20bf46608b1c38165b',
                'is_premium'     => false,
                'premium_suffix' => 'Pro',
                'has_addons'     => false,
                'has_paid_plans' => true,
                'trial'          => array(
                'days'               => 14,
                'is_require_payment' => true,
            ),
                'menu'           => array(
                'slug'    => 'migration-panel-settings',
                'network' => true,
            ),
                'is_live'        => true,
            ) );
        }
        
        return $pm_fs;
    }
    
    // Init Freemius.
    pm_fs();
    // Signal that SDK was initiated.
    do_action( 'pm_fs_loaded' );
}

if ( !function_exists( 'primeMoverGetConfigurationPath' ) ) {
    function primeMoverGetConfigurationPath()
    {
        
        if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
            return ABSPATH . 'wp-config.php';
        } elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && !@file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
            return dirname( ABSPATH ) . '/wp-config.php';
        } else {
            return '';
        }
    
    }

}
if ( !function_exists( 'primeMoverGetUploadsDirectoryInfo' ) ) {
    function primeMoverGetUploadsDirectoryInfo()
    {
        $main_site_blog_id = 0;
        $multisite = false;
        if ( is_multisite() ) {
            $multisite = true;
        }
        if ( $multisite ) {
            $main_site_blog_id = get_main_site_id();
        }
        if ( $multisite ) {
            switch_to_blog( $main_site_blog_id );
        }
        $upload_dir = wp_upload_dir();
        if ( $multisite ) {
            restore_current_blog();
        }
        return $upload_dir;
    }

}
if ( !function_exists( 'primeMoverIsShaString' ) ) {
    function primeMoverIsShaString( $string = '', $mode = 256 )
    {
        if ( !$string ) {
            return false;
        }
        $lengths = [
            256 => 64,
            512 => 128,
        ];
        $length = $lengths[$mode];
        return (bool) preg_match( '/^[0-9a-f]{' . $length . '}$/i', $string );
    }

}
if ( !function_exists( 'primeMoverLanguageToLocale' ) ) {
    function primeMoverLanguageToLocale()
    {
        return array(
            'af'      => 'af_ZA',
            'ar'      => 'ar',
            'az'      => 'az',
            'be'      => 'be_BY',
            'bg'      => 'bg_BG',
            'bn'      => 'bn_BD',
            'bs'      => 'bs_BA',
            'ca'      => 'ca',
            'cs'      => 'cs_CZ',
            'cy'      => 'cy_GB',
            'da'      => 'da_DK',
            'de'      => 'de_DE',
            'el'      => 'el',
            'en'      => 'en_US',
            'eo'      => 'eo_UY',
            'es'      => 'es_ES',
            'et'      => 'et',
            'eu'      => 'eu_ES',
            'fa'      => 'fa_IR',
            'fi'      => 'fi',
            'fo'      => 'fo_FO',
            'fr'      => 'fr_FR',
            'ga'      => 'ga_IE',
            'gl'      => 'gl_ES',
            'he'      => 'he_IL',
            'hi'      => 'hi_IN',
            'hr'      => 'hr',
            'hu'      => 'hu_HU',
            'hy'      => 'hy_AM',
            'id'      => 'id_ID',
            'is'      => 'is_IS',
            'it'      => 'it_IT',
            'ja'      => 'ja',
            'ka'      => 'ge_GE',
            'km'      => 'km_KH',
            'ko'      => 'ko_KR',
            'ku'      => 'ckb',
            'lt'      => 'lt_LT',
            'lv'      => 'lv_LV',
            'mg'      => 'mg_MG',
            'mk'      => 'mk_MK',
            'mn'      => 'mn_MN',
            'ms'      => 'ms_MY',
            'mt'      => 'mt_MT',
            'nb'      => 'nb_NO',
            'ne'      => 'ne',
            'no'      => 'nb_NO',
            'nn'      => 'nn_NO',
            'ni'      => 'ni_ID',
            'nl'      => 'nl_NL',
            'pa'      => 'pa_IN',
            'pl'      => 'pl_PL',
            'pt-br'   => 'pt_BR',
            'pt-pt'   => 'pt_PT',
            'qu'      => 'quz_PE',
            'ro'      => 'ro_RO',
            'ru'      => 'ru_RU',
            'si'      => 'si_LK',
            'sk'      => 'sk_SK',
            'sl'      => 'sl_SI',
            'so'      => 'so_SO',
            'sq'      => 'sq_AL',
            'sr'      => 'sr_RS',
            'su'      => 'su_ID',
            'sv'      => 'sv_SE',
            'ta'      => 'ta_IN',
            'tg'      => 'tg_TJ',
            'th'      => 'th',
            'tr'      => 'tr_TR',
            'ug'      => 'ug_CN',
            'uk'      => 'uk',
            'ur'      => 'ur',
            'uz'      => 'uz_UZ',
            'vi'      => 'vi_VN',
            'zh-hans' => 'zh_CN',
            'zh-hant' => 'zh_TW',
        );
    }

}
if ( !function_exists( 'is_php_version_compatible' ) ) {
    function is_php_version_compatible( $required )
    {
        return empty($required) || version_compare( phpversion(), $required, '>=' );
    }

}
if ( !function_exists( 'primeMoverAutoDeactivatePlugin' ) ) {
    function primeMoverAutoDeactivatePlugin()
    {
        
        if ( defined( 'PRIME_MOVER_MAINPLUGIN_FILE' ) ) {
            $input_get = filter_input_array( INPUT_GET, array(
                'activate' => FILTER_VALIDATE_BOOLEAN,
            ) );
            
            if ( isset( $input_get['activate'] ) ) {
                unset( $_GET['activate'] );
                $_GET['deactivate'] = true;
            }
            
            $plugin_basename = plugin_basename( PRIME_MOVER_MAINPLUGIN_FILE );
            deactivate_plugins( $plugin_basename );
        }
    
    }

}