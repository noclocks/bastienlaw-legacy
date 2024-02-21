<?php
namespace Codexonics\PrimeMoverFramework\compatibility;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Codexonics\PrimeMoverFramework\classes\PrimeMover;
use WP_Screen;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Compatibility Class
 * Helper class for interacting with third party plugins
 *
 */
class PrimeMoverCompatibility
{     
    private $prime_mover;
    private $import_utilities;
    private $backup_utilities;
    private $screen_options;
    
    const PRIMEMOVER_SETTINGS_MIGRATED = 'prime_mover_settings_migrated';
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->import_utilities = $utilities['import_utilities'];
        $this->backup_utilities = $utilities['backup_utilities'];
        $this->screen_options = $utilities['screen_options'];
    }
    
    /**
     * Get system checks
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks
     */
    public function getSystemChecks()
    {
        return $this->getPrimeMover()->getSystemChecks();
    }
    
    /**
     * Get screen options object
     * @return array
     */
    public function getScreenOptions()
    {
        return $this->screen_options;
    }
    
    /**
     * Get backup utilities
     * @return array
     */
    public function getBackupUtilities()
    {
        return $this->backup_utilities;
    }
    
    /**
     * Get Prime Mover instance
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     */
    public function getPrimeMover()
    {
        return $this->prime_mover;
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getPrimeMover()->getSystemFunctions();
    }
    
    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getPrimeMover()->getSystemAuthorization();
    }
    
    /**
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getPrimeMover()->getSystemInitialization();
    }
    
    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->getPrimeMover()->getHookedMethods()->getProgressHandlers();
    }
    
    /**
     * Initialize hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itAddsInitHooks()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itChecksIfHooksAreOutdated()
     */
    public function initHooks()
    {
        add_action('prime_mover_after_db_processing', [$this, 'deactivateReallySimpleSSLNonSecureSite'], 0, 2);
        add_action('prime_mover_load_module_apps', [$this, 'ensureCorrectSchemeIsUsed'], 0);
        add_action('init', [$this, 'destroyInCompatibleSessionOnAjax'], 999999);
        
        add_filter('wp_auth_check_load', [$this, 'removeInterimLoginOnMigrationToolsScreen'], 10, 2);
        add_filter('prime_mover_footprint_keys', [$this, 'primeMoverGenerateFootPrintKeys'], 99999, 2);
        add_filter('prime_mover_validate_site_footprint_data', [$this, 'primeMoverGenerateFootprintConfiguration'], 99999, 2);
        
        add_filter('prime_mover_filter_sql_data', [$this, 'rootsPersonaFixedColumnn'], 10, 2);
        add_filter('prime_mover_filter_sql_data', [$this, 'legacyMySQLCollateAdjustment'], 15, 2);
        add_action('wp_loaded', [$this, 'maybeMigratePrimeMoverSettings'],1);
        
        add_filter('prime_mover_before_copying_plugin', [$this, 'deactivatePluginBeforeRestore'], 10, 2);
        add_filter('prime_mover_after_copying_plugin', [$this, 'reactivatePluginAfterRestore'], 10, 5);
        
        add_filter('prime_mover_before_theme_restore', [$this, 'maybeDeactivateThemeOnMainSite'], 10, 2);
        add_filter('prime_mover_after_theme_restore', [$this, 'maybeRestoreThemeOnMainSite'], 10, 1);
        
        add_filter('prime_mover_before_theme_restore', [$this, 'maybePutMainSiteToMaintenance'], 99, 1);
        add_filter('prime_mover_after_theme_restore', [$this, 'maybeRemoveMainSiteMaintenance'], 99, 1);
        
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setIfZipExtensionEnabled'], 100, 1 );
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setNoZipExtensionError'], 110, 1 );
        add_filter('prime_mover_has_zip_extension', [$this, 'maybeZipExtensionEnabled'], 10, 1);
        
        add_filter('prime_mover_filter_content_type_check', [$this, 'maybeBlockZipContentType'], 10, 2);
        add_action('prime_mover_before_activating_plugins', [$this, 'maybeNetworkActivatePlugins'], 10, 1);
        
        add_filter('prime_mover_filter_export_footprint', [$this, 'addThirdPartyPluginsProcessorToFootPrint'], 2500, 3);        
        add_action('prime_mover_after_db_processing', [$this, 'maybeScheduleThirdPartyProcessingOnImport'], 10, 2);
    } 
    
    /**
     * Maybe schedule third party processing later on
     * This is used for third party plugins user adjustments
     * @param array $ret
     * @param number $blogid_to_import
     */
    public function maybeScheduleThirdPartyProcessingOnImport($ret = [], $blogid_to_import = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !$blogid_to_import) {
            return;
        }
        
        if (empty($ret['imported_package_footprint']['thirdparty_callback_plugins'])) {
            return;
        }
        
        $process_id = $this->getProgressHandlers()->generateTrackerId($blogid_to_import, 'import');
        $option = '_thirdpartyplugins_' . $process_id;
        $thirdparty_data = $ret['imported_package_footprint']['thirdparty_callback_plugins'];
        
        $this->getSystemFunctions()->updateSiteOption($option, $thirdparty_data, true);      
    }
  
    /**
     * Add third party plugins processor in footprint config
     * @param array $footprint
     * @param array $ret
     * @param number $blog_id
     * @return array
     */
    public function addThirdPartyPluginsProcessorToFootPrint($footprint = [], $ret = [], $blog_id = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !$blog_id) {
            return $footprint;
        }
        if (!is_array($footprint)) {
            return $footprint;
        }
        if (!empty($ret['thirdparty_callback_plugins'])) {
            $footprint['thirdparty_callback_plugins'] = $ret['thirdparty_callback_plugins'];
        }  
        
        return $footprint;
    }
    
    /**
     * Maybe network activate plugins
     * @param array $ret
     */
    public function maybeNetworkActivatePlugins($ret = [])
    {
        if ( ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        if (!is_multisite()) {
            return;
        }
        if (empty($ret['deactivated_network_plugins'])) {
            return;
        }        
        $deactivated = $ret['deactivated_network_plugins'];
        if (!is_array($deactivated)) {
            return;
        }
        
        foreach ($deactivated as $plugin_info) {
            list($blog_id, $plugin_path_target, $plugin, $network_activated) = $plugin_info;
            $this->getSystemFunctions()->activatePlugin($blog_id, $plugin_path_target, $plugin, $network_activated);
        }       
    }
    
    /**
     * Block zip content type when server does not support it
     * @param boolean $is_content_valid
     * @param string $content_type
     * @return string|\WP_Error
     */
    public function maybeBlockZipContentType($is_content_valid = true, $content_type = '')
    {
        if (!$is_content_valid || !$content_type) {
            return $is_content_valid;
        }
        
        $verified_zip_mimes = ['application/x-zip', 'application/zip', 'application/x-zip-compressed'];
        if (in_array($content_type, $verified_zip_mimes) && !extension_loaded('zip')) {
            return new WP_Error( 'prime_mover_block_zip_content_type', esc_html__('Remote URL migration requires PHP zip extension enabled if you are restoring a zip package.', 'prime-mover'));
        }       
        
        return $is_content_valid;
    }
    
    /**
     * When checking for zip file name extension
     * Make sure the PHP Zip Extension is also enabled
     * @param boolean $enabled
     * @return string|boolean
     */
    public function maybeZipExtensionEnabled($enabled = false)
    {
        if (!$enabled) {
            return $enabled;
        }
        if (extension_loaded('zip')) {
            $enabled = true;
        } else {
            $enabled = false;
        }
        
        return $enabled;
    }
    
    /**
     * Set if zip extension enabled
     * @param array $args
     * @return string
     */
    public function setIfZipExtensionEnabled(array $args)
    {
        $args['is_zip_extension_installed'] = 'no';
        if (extension_loaded('zip')) {
            $args['is_zip_extension_installed'] = 'yes';
        }
        
        return $args;
    }
    
    /**
     * Set no zip extension error
     * @return string
     */
    public function setNoZipExtensionError(array $args)
    {
        $args['no_zip_extension_error'] = esc_js(__('You are restoring a zip package but your server does not have PHP ZIP extension installed. Please install and refresh this page to try again.', 'prime-mover'));
        
        return $args;
    }
    
    /**
     * Maybe remove maintenance
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itRemovesMainSiteMaintenance() 
     */
    public function maybeRemoveMainSiteMaintenance($ret = [])
    {        
        if (!is_multisite() || !isset($ret['mainsite_maintenance_mode_enabled'])) {
            return $ret;
        }
        
        if (true === $ret['mainsite_maintenance_mode_enabled']) {
            $mainsite_id = $this->getSystemInitialization()->getMainSiteBlogId(true);
            $this->getProgressHandlers()->deleteMaintenanceOption($mainsite_id);
            unset($ret['mainsite_maintenance_mode_enabled']);
        }
        
        return $ret;        
    }
    
    /**
     * Maybe put main site to maintenance
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itPutMainSiteMaintenance()
     */
    public function maybePutMainSiteToMaintenance($ret = [])
    {
        if (!is_multisite() || empty($ret['mainsite_deactivated_template']) || empty($ret['mainsite_deactivated_stylesheet'])) {
            return $ret;
        }
        
        $mainsite_id = $this->getSystemInitialization()->getMainSiteBlogId(true);
        $this->getProgressHandlers()->putPublicSiteToMaintenance($mainsite_id);
        
        return $ret;
    }
    
    /**
     * Maybe deactivate theme on main site
     * So it cannot cause fatal errors when theme is restored on subsite
     * @param array $ret
     * @param number $blog_id
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itDeactivatesThemeOnMainSite()
     */
    public function maybeDeactivateThemeOnMainSite($ret = [], $blog_id = 0)
    {
        if (!is_multisite() || !$blog_id || empty($ret)) {
            return $ret;
        }
        
        $processed = 0;
        if (!empty($ret['copydir_processed'])) {
            $processed = (int)$ret['copydir_processed'];
        }
        if ($processed) {
            return $ret;
        }
        
        $source_template = '';
        if (!empty($ret['imported_package_footprint']['template'])) {
            $source_template = key($ret['imported_package_footprint']['template']);
        }
        
        $source_stysheet = '';
        if (!empty($ret['imported_package_footprint']['stylesheet'])) {
            $source_stysheet = key($ret['imported_package_footprint']['stylesheet']);
        }
        
        if (!$source_template) {
            return $ret;
        }
        
        if ($this->getSystemFunctions()->isMultisiteMainSite($blog_id, false, true)) {
            return $ret;
        }
        
        $mainsite_id = $this->getSystemInitialization()->getMainSiteBlogId(true);
        if (!$mainsite_id) {
            return $ret;
        }
        
        list($mainsite_template, $mainsite_stylesheet) = $this->getSystemChecks()->getThemeOnSpecificSite($mainsite_id);
        if (!$mainsite_template || !$mainsite_stylesheet) {
            return $ret;
        }
        
        $deactivate = false;
        if ($mainsite_template === $source_template || $source_stysheet === $mainsite_stylesheet) {
            $deactivate = true;
        }
        if (!$deactivate) {
            return $ret;
        }        
        
        do_action('prime_mover_log_processed_events', "DEACTIVATE MAIN SITE THEME BECAUSE IT CAN CONFLICT WITH RESTORE", $blog_id, 'import', __FUNCTION__, $this);       
        $this->getSystemChecks()->deactivateThemeOnSpecificSite($mainsite_id);
       
        $ret['mainsite_deactivated_template'] = $mainsite_template;
        $ret['mainsite_deactivated_stylesheet'] = $mainsite_stylesheet;
        
        return $ret;
    }
    
    /**
     * Maybe restore theme on main site
     * @param array $ret
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itMaybeRestoreThemeOnMainSite()
     */
    public function maybeRestoreThemeOnMainSite($ret = [])
    {
        if (!is_multisite()) {
            return $ret;
        }
        
        if (empty($ret['mainsite_deactivated_template']) || empty($ret['mainsite_deactivated_stylesheet'])) {
            return $ret;
        }
        
        $mainsite_id = $this->getSystemInitialization()->getMainSiteBlogId(true);
        $template = $ret['mainsite_deactivated_template'];
        $stylesheet = $ret['mainsite_deactivated_stylesheet'];     
        
        $this->getSystemChecks()->updateThemeOnSpecificSite($mainsite_id, $template, $stylesheet);
        
        unset($ret['mainsite_deactivated_template']);
        unset($ret['mainsite_deactivated_stylesheet']);
       
        $ret['mainsite_maintenance_mode_enabled'] = true;
        return $ret;
    }
    
    /**
     * Reactivate plugins after being restored
     * @param array $import_data
     * @param string $plugin
     * @param number $blog_id
     * @param array $plugins
     * @param string $plugin_path_target
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itReactivatesPluginAfterRestore() 
     */
    public function reactivatePluginAfterRestore($import_data = [], $plugin = '', $blog_id = 0, $plugins = [], $plugin_path_target = '')
    {
        if (!is_multisite() || !$plugin_path_target) {
            return $import_data;
        }
        
        if (empty($import_data['deactivated_plugin'])) {
            return $import_data;
        }
       
        $plugin_deactivated = $import_data['deactivated_plugin']['plugin'];        
        if (!$plugin_deactivated) {
            return $import_data;
        }
        
        if ($plugin_deactivated !== $plugin) {
            return $import_data;
        }
        
        $plugin = $plugin_deactivated;
        $network_activated = $import_data['deactivated_plugin']['network']; 
        $mainsite_activated = $import_data['deactivated_plugin']['mainsite'];     
        $main_site_id = $this->getSystemInitialization()->getMainSiteBlogId(true);        
        $activated = false;               
        
        if ($mainsite_activated && !$this->getSystemFunctions()->isMultisiteMainSite($blog_id, false, true)) {
            $activated = $this->getSystemFunctions()->activatePlugin($main_site_id, $plugin_path_target, $plugin, $network_activated);
        }
        
        if ($activated) {
            unset($import_data['deactivated_plugin']);
        } 
        
        if (!$activated && $network_activated) {
            $import_data['deactivated_network_plugins'][] = [
                $main_site_id, 
                wp_normalize_path($plugin_path_target),
                wp_normalize_path($plugin),
                $network_activated
            ];
        }
        
        return $import_data;
    }
        
    /**
     * Deactivate plugin before restore
     * So it cannot cause fatal error when its activated
     * while restoration is ongoing
     * @param array $import_data
     * @param string $plugin
     */
    public function deactivatePluginBeforeRestore($import_data = [], $plugin = '')
    {
        if (!is_multisite() || !$plugin) {
            return $import_data;
        }
        
        $network_active = false;
        $plugin_active = false;
        
        if ($this->getSystemFunctions()->isPluginActive($plugin, true)) {
            $network_active = true;
            $plugin_active = true;
        }
        
        $mainsite_id = $this->getSystemInitialization()->getMainSiteBlogId(true);
        $this->getSystemFunctions()->switchToBlog($mainsite_id);
     
        if ($this->getSystemFunctions()->isPluginActive($plugin)) {
            $plugin_active = true;
        }
        
        $this->getSystemFunctions()->restoreCurrentBlog();
        $network_deactivate = false;
        $deactivate_called = false;
        if ($network_active) {
            $network_deactivate = null;
        }
        if ($plugin_active) {
            $this->getSystemFunctions()->deactivatePlugins($plugin, true, $network_deactivate);
            $deactivate_called = true;
        }        
        
        if (!$deactivate_called) {
            return $import_data;
        }
       
        $import_data['deactivated_plugin'] = ['plugin' => $plugin, 'network' => $network_active, 'mainsite' => $plugin_active];
        return $import_data;        
    }
    
    /**
     * Maybe migrate Prime Mover network settings to site meta table
     * Instead of using the main site options table
     * This is in preparation for main site export/import support
     * @since 1.1.9
     */
    public function maybeMigratePrimeMoverSettings()
    {
        if ( ! $this->getPrimeMover()->getSystemAuthorization()->isUserAuthorized() ) {
            return;
        }
        if ( ! is_multisite() ) {
            return;
        }
        if ( ! is_network_admin()) {
            return;
        }
        if ('yes' === $this->getSystemFunctions()->getSiteOption(self::PRIMEMOVER_SETTINGS_MIGRATED, false, true, true)) {
            return;
        }        
        $backups = $this->getBackupUtilities()->getValidatedBackupsArrayInDb($this->getSystemInitialization()->getPrimeMoverMenuBackupsOption(), true);
        if (!is_array($backups)) {
            return;
        }       
        $backupoptions_to_migrate = $this->retrievedBackupFileOptionsToMigrate($backups);
        $this->moveBackupOptionsToSiteMeta($backupoptions_to_migrate);       
        $main_options = $this->mainPrimeMoverOptions();
        
        $this->mainOptionToSiteMetaOptionHelper($main_options);     
        $this->getSystemFunctions()->updateSiteOption(self::PRIMEMOVER_SETTINGS_MIGRATED, 'yes', true);        
    }
    
    /**
     * Main Prime Mover options
     * @return string[]
     */
    protected function mainPrimeMoverOptions()
    {
        $screen_options = $this->getScreenOptions();
        $screen_options_constant = $screen_options::SCREEN_OPTION_SETTING_MIGRATED;
        
        return [
            $this->getSystemInitialization()->getControlPanelSettingsName(),
            $this->getSystemInitialization()->getBackupSitesOptionName(),
            $this->getSystemInitialization()->getPrimeMoverMenuBackupsOption(),
            $screen_options_constant,
            $this->getSystemInitialization()->getPrimeMoverValidatedBackupsOption()                        
        ];
    }
    /**
     * 
     * @param array $backup_options
     */
    protected function moveBackupOptionsToSiteMeta($backup_options = [])
    {
        foreach ($backup_options as $package_name => $hashes) {
            list($hash, $hash_file) = $hashes;
            $linearized = [];
            
            $linearized = [$package_name, $hash, $hash_file];            
            $this->mainOptionToSiteMetaOptionHelper($linearized);
        }
    }
    
    /**
     * Helper method for moving settings
     * @param array $linearized
     */
    protected function mainOptionToSiteMetaOptionHelper($linearized = [])
    {
        $main_site_id = $this->getSystemInitialization()->getMainSiteBlogId();
        $this->getSystemFunctions()->switchToBlog($main_site_id);
        
        foreach($linearized as $option_name) {
            $orig_value = get_option($option_name);
            if (false === $orig_value) {
                delete_option($option_name);
                continue;
            }           
            $this->getSystemFunctions()->updateSiteOption($option_name, $orig_value, true);
            delete_option($option_name);
        }
        $this->getSystemFunctions()->restoreCurrentBlog();   
    }
    
    /**
     * Retrieved backup file options
     * @param array $backups
     */
    protected function retrievedBackupFileOptionsToMigrate($backups = [])
    {
        $simplified = [];
        foreach ($backups as $blog_id => $sites) {
            foreach ($sites as $backups) {
                foreach ($backups as $backup_meta) {
                    $package_option_name = '';
                    if (!empty($backup_meta['sanitized_package_name'])) {
                        $sanitized_name = $backup_meta['sanitized_package_name'];
                        $package_option_name = $this->getSystemInitialization()->generateZipDownloadOptionName($sanitized_name, $blog_id);                         
                    }
                    $hash = '';
                    $query_parts = [];
                    if (!empty($backup_meta['download_url'])) {
                        $query_parts = $this->getSystemFunctions()->isPrimeMoverDownloadZipURLFormat($backup_meta['download_url'], true);
                    }
                    if (isset($query_parts['hash'])) {
                        $hash = $query_parts['hash'];
                    }
                    if ($hash) {
                        $simplified[$package_option_name] = [$hash, $hash . "_filename"];
                    }                    
                }
            }
        }
        
        return $simplified;
    }
    
    /**
     * Adjust collate on legacy MySQL versions.
     * @param string $line
     * @param array $ret
     * @return mixed
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itAdjustLegacyMySql() 
     */
    public function legacyMySQLCollateAdjustment($line, $ret = [])
    {
        if (empty($ret['mysql_version'])) {
            return $line;
        }
        if (version_compare($ret['mysql_version'], '5.6.0', '<')) {
            return str_replace('utf8mb4_unicode_520_ci', 'utf8mb4_unicode_ci', $line);
        } 
        return $line;
    }
    
    /**
     * Fixed RootsPersona plugin incorrect column after import
     * @param string $line
     * @param array $ret
     * @return mixed
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itAddsCompatRootsPersonaColumn()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsFilteringWhenRootsPersonaNotActive()
     */
    public function rootsPersonaFixedColumnn($line, $ret = [])
    {
        $origin_db_prefix = '';
        if ( ! empty($ret['origin_db_prefix']) ) {
            $origin_db_prefix = $ret['origin_db_prefix'];
        }
        if ($origin_db_prefix && isset($ret['imported_package_footprint']['plugins']['rootspersona/rootspersona.php'])) {
            $column = $origin_db_prefix . 'page_id';
            return str_replace("`$column`", "`wp_page_id`", $line);
        } else {
            return $line;
        }        
    }
    
    /**
     * Log Prime Mover footprint keys
     * @param array $keys
     * @param array $footprint
     * @return array
     */
    public function primeMoverGenerateFootPrintKeys($keys = [], $footprint = [])
    {
        do_action('prime_mover_log_processed_events', "Footprint keys for validation :", 1, 'export', 'primeMoverGenerateFootPrintKeys', $this);
        do_action('prime_mover_log_processed_events', $keys, 1, 'export', 'primeMoverGenerateFootPrintKeys', $this);
        do_action('prime_mover_log_processed_events', "Custom log for site footprint data before check:", 1, 'export', 'primeMoverGenerateFootprintConfiguration', $this);
        do_action('prime_mover_log_processed_events', $footprint, 1, 'export', 'primeMoverGenerateFootprintConfiguration', $this);
        
        return $keys;        
    }
    
    /**
     * Log Prime Mover footprint configuration
     * @param boolean $overall_valid
     * @param array $footprint_temp
     * @return string
     */
    public function primeMoverGenerateFootprintConfiguration($overall_valid = true, $footprint_temp = [])
    {
        do_action('prime_mover_log_processed_events', "Footprint data validity is: $overall_valid", 1, 'export', 'primeMoverGenerateFootprintConfiguration', $this);
        do_action('prime_mover_log_processed_events', "Custom log for site footprint data after check:", 1, 'export', 'primeMoverGenerateFootprintConfiguration', $this);
        do_action('prime_mover_log_processed_events', $footprint_temp, 1, 'export', 'primeMoverGenerateFootprintConfiguration', $this);
        
        return $overall_valid;
    }
    /**
     * Disable interim login on Migration Tools screen
     * @param boolean $show
     * @param WP_Screen $screen
     * @return string|string|boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itRemovedInterimLoginScreenOnMigrationTools()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itDoesNotRemoveInterimLoginScreenOtherPages()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsInterimLoginCheckOnMultisite()
     */
    public function removeInterimLoginOnMigrationToolsScreen($show = true, WP_Screen $screen = null)
    {
        if (is_multisite()) {
            return $show;
        }
        if ( ! isset($screen->id) ) {
            return $show;
        }
        if ('tools_page_migration-tools' === $screen->id) {
            $show = false;
        }
        return $show;
    }
    
    /**
     * Destroy incompatible session on Prime Mover Ajax actions
     */
    public function destroyInCompatibleSessionOnAjax()
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }  
        if ( ! wp_doing_ajax() ) {
            return;
        }
        if ( ! is_admin() ) {
            return;
        }
        $args = ['action' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()];
        $action = $this->getSystemInitialization()->getUserInput('post', $args, 'disable_session', 'export', 0, true); 
        if (empty($action)) {
            return;
        }
        if ( ! isset($action['action']) ) {
            return;            
        }
        $action_posted = $action['action'];
        $session_started = true;
        $valid_actions = array_keys($this->getPrimeMover()->getSystemInitialization()->getPrimeMoverAjaxActions());
        if (session_status() === PHP_SESSION_NONE) {
            $session_started = false;
        }
        if (in_array($action_posted, $valid_actions) && $session_started) {
            session_destroy();
        }        
    }
    
    /**
     * Ensure correct scheme is consistent because it can cause AJAX login session issues.
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itEnsuresCorrectSchemeIsUsed()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsSchemeCheckIfMultisite() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsSchemeCheckIfNotAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsSchemeCheckIfNonSSLSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsSchemeCheckIfFrontEnd()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsSchemeCheckWhenDoingAjax()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsSchemeReplacementWhenDoingImport() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsSchemeReplaceDoingExport() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsSchemeUpdateIfAlreadyHttps() 
     */
    public function ensureCorrectSchemeIsUsed()
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }        
        if ( ! is_admin() || ! is_ssl() || is_multisite() ) {
            return;
        }
        if (wp_doing_ajax()) {
            return;
        }
        if (doing_action('prime_mover_do_import')) {
            return;
        }
        if (doing_action('prime_mover_export')) {
            return;
        }        
        
        $site_url = get_option('siteurl');
        $parsed = wp_parse_url($site_url);
        if (empty($parsed['scheme'])) {
            return;
        }
        $scheme = $parsed['scheme'];
        $correct = '';
        if ('http' === $scheme) {       
            $correct = str_replace('http://', 'https://', $site_url);
            update_option( 'siteurl', $correct);
        }        
    }
    
    /**
     * Deactivate Really Simple SSL Plugin if restored on site without SSL working
     * @param array $ret
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itDeactivatesReallySimpleSSLPluginInsecureSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itDoesNotDeactivateReallySimpleSSLSecureSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCompatibility::itSkipsReallySimpleSSLDeactivationNotAuthorized()  
     */
    public function deactivateReallySimpleSSLNonSecureSite($ret = [], $blog_id = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $this->getSystemFunctions()->switchToBlog($blog_id);
        $active_plugins = $this->getSystemFunctions()->getActivatedPlugins();
        if (!is_array( $active_plugins ) ) {
            $this->getSystemFunctions()->restoreCurrentBlog();
            return;
        }
        foreach ($active_plugins as $plugin) {
            if (false !== strpos($plugin, 'rlrsssl-really-simple-ssl.php') && !is_ssl() ) {
                $this->getSystemFunctions()->deactivatePlugins($plugin, true);
            }
        }
        $this->getSystemFunctions()->restoreCurrentBlog();
    }
}