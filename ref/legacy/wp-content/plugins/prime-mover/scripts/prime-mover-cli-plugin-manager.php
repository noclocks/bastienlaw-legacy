<?php
namespace Codexonics\PrimeMoverFramework\general;
/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * This is automatically added by Prime Mover to the must use plugins directory. 
 * This code will only run when doing export/import process with Prime Mover. This will be deleted automatically when plugin is deactivated.
 */
final class PrimeMoverMustUsePluginManager
{
    private $input_post;
    private $input_get;
    private $current_process;
    private $is_switched;
    private $locale;
    
    const PRIME_MOVER_CORE_EXPORT_PROCESSES = ['prime_mover_process_export', 'prime_mover_monitor_export_progress', 'prime_mover_shutdown_export_process'];
    const PRIME_MOVER_CORE_IMPORT_PROCESSES = ['prime_mover_process_import', 'prime_mover_monitor_import_progress', 'prime_mover_shutdown_import_process'];
    const PRIME_MOVER_CORE_UPLOAD_PROCESSES = ['prime_mover_process_uploads'];
    const PRIME_MOVER_CORE_PANEL_PROCESSES = ['prime_mover_delete_all_backups_request'];
    
    /**
     * Constructor
     * @param array $input_post
     * @param array $input_get
     */
    public function __construct($input_post = [], $input_get = [])
    {
        $this->input_post = $input_post;
        $this->input_get = $input_get;
        $this->current_process = '';
        $this->is_switched = false;
        $this->locale = 'en_US';
    }
    
    /**
     * Get locale
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }
    
    /**
     * Get is switched value
     * @return boolean
     */
    public function getIsSwitched()
    {
        return $this->is_switched;
    }
    
    /**
     * Set is switched
     * @param boolean $switched
     */
    public function setIsSwitched($switched = false)
    {
        $this->is_switched = $switched;
    }
    
    /**
     * Get current process
     * @return string
     */
    public function getCurrentProcess()
    {
        return $this->current_process;
    }
    
    /**
     * Set current process
     * @param array $input_post
     */
    public function setCurrentProcess($input_post = [])
    {
        if (!wp_doing_ajax()) {
            return;
        }
        $export_import_processes = ['prime_mover_process_export', 'prime_mover_process_import'];
        if (!empty($input_post['action']) && !in_array($input_post['action'], $export_import_processes)) {
            return;
        }
        $processes = ['export', 'import'];
        foreach ($processes as $process) {
            if (!empty($input_post["prime_mover_next_{$process}_method"])) {
                $this->current_process = $input_post["prime_mover_next_{$process}_method"];
            }
        }        
    }
    
    /**
     * Set basic constants
     */
    public function setConstants()
    {
        if (defined('PRIME_MOVER_OVERRIDE_PLUGIN_MANAGER') && PRIME_MOVER_OVERRIDE_PLUGIN_MANAGER) {
            return;
        }
        
        if (!defined('PRIME_MOVER_DEFAULT_FREE_BASENAME')) {
            define('PRIME_MOVER_DEFAULT_FREE_BASENAME', 'prime-mover/prime-mover.php');
        }
        
        if (!defined('PRIME_MOVER_DEFAULT_PRO_BASENAME')) {
            define('PRIME_MOVER_DEFAULT_PRO_BASENAME', 'prime-mover-pro/prime-mover.php');
        }
        
        if (!defined('PRIME_MOVER_DEFAULT_ELEMENTOR_BASENAME')) {
            define('PRIME_MOVER_DEFAULT_ELEMENTOR_BASENAME', 'elementor/elementor.php');
        }
        
        if (!defined('PRIME_MOVER_ALLOW_THIRDPARTY_PLUGINS')) {
            define('PRIME_MOVER_ALLOW_THIRDPARTY_PLUGINS', true);
        }
        
        if (!defined('PRIME_MOVER_MAX_MEMORY_LIMIT')) {
            define('PRIME_MOVER_MAX_MEMORY_LIMIT', '1G');
        }
        
        $this->defineWPFSNetworkAdminConstant();
    }
    
    /**
     * Define custom network admin constant
     */
    private function defineWPFSNetworkAdminConstant()
    {
        $network_admin = false;
        if (is_multisite() && (is_network_admin())) {
            $network_admin = true;
        }
        
        $mu_ajax = false;
        if (!$network_admin && is_multisite() && wp_doing_ajax()) {
            $mu_ajax = true;
        }
        
        $ajax_post = $this->getInputPost();
        if ($mu_ajax && isset($ajax_post['action']) && (false !== strpos($ajax_post['action'], 'prime_mover_') || 'multisite_tempfile_cancel' === $ajax_post['action'])) {
            $network_admin = true;
        }
        
        if (!defined('WP_FS__IS_NETWORK_ADMIN') && $network_admin) {
            define('WP_FS__IS_NETWORK_ADMIN', true);
        }
    }
    
    /**
     * Get input POST
     * @return array
     */
    public function getInputPost()
    {
        return $this->input_post;
    }
    
    /**
     * Get input GET
     * @return array
     */
    public function getInputGet()
    {
        return $this->input_get;
    }
        
    /**
     * Check if we need to enable log
     * @param string $current_process
     * @return boolean
     */
    private function primeMoverMaybeEnablePluginManagerLog($current_process = '')
    {
        return (defined('PRIME_MOVER_PLUGIN_MANAGER_LOG') && PRIME_MOVER_PLUGIN_MANAGER_LOG && file_exists(PRIME_MOVER_PLUGIN_MANAGER_LOG) && $current_process);
    }
    
    /**
     * Is doing a specific Prime Mover Export/Import Process
     * @param array $input_post
     * @param string $target_process
     * @param string $mode
     * @return boolean
     */
    private function isDoingThisPrimeMoverProcess($input_post = [], $target_process = '', $mode = 'import')
    {
        return (wp_doing_ajax() && !empty($input_post["prime_mover_next_{$mode}_method"]) && $target_process === $input_post["prime_mover_next_{$mode}_method"]);
    }
    
    /**
     * Maybe load plugin manager
     * @return boolean
     */
    public function primeMoverMaybeLoadPluginManager()
    {
        $input_post = $this->getInputPost();
        $input_get = $this->getInputGet();
        
        if (!wp_doing_ajax() && isset($input_get['prime_mover_export_hash']) && isset($input_get['prime_mover_blogid'])) {            
            return true;
        }
        
        if (wp_doing_ajax() && isset($input_post['action']) && in_array($input_post['action'], self::PRIME_MOVER_CORE_EXPORT_PROCESSES)) {   
            $this->setCurrentProcess($input_post);
            return true;
        }
        
        if (wp_doing_ajax() && isset($input_post['action']) && in_array($input_post['action'], self::PRIME_MOVER_CORE_IMPORT_PROCESSES)) { 
            $this->setCurrentProcess($input_post);
            return true;
        }
        
        $bypass_upload_plugin_control = false;
        
        if (defined('PRIME_MOVER_BYPASS_UPLOAD_PLUGIN_CONTROL') && true === PRIME_MOVER_BYPASS_UPLOAD_PLUGIN_CONTROL) {
            $bypass_upload_plugin_control = true;
        }
        
        if (wp_doing_ajax() && isset($input_post['action']) && in_array($input_post['action'], self::PRIME_MOVER_CORE_UPLOAD_PROCESSES) & false === $bypass_upload_plugin_control) {
            return true;
        }
        
        if (wp_doing_ajax() && isset($input_post['action']) && in_array($input_post['action'], self::PRIME_MOVER_CORE_PANEL_PROCESSES)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Maybe add third party app
     * @param array $input_post
     * @return string[]|string[]|mixed[]|boolean[]|NULL[]|array[]
     */
    private function primeMoverMaybeAddThirdPartyApp($input_post = [])
    {
        $required = [PRIME_MOVER_DEFAULT_PRO_BASENAME, PRIME_MOVER_DEFAULT_FREE_BASENAME];
        if (true !== PRIME_MOVER_ALLOW_THIRDPARTY_PLUGINS) {
            return $required;
        }
        
        $process_id = '';
        if (!empty($input_post['process_id'])) {
            $process_id = $input_post['process_id'];
        }
        
        if ($this->isDoingThisPrimeMoverProcess($input_post, 'maybeProcessThirdPartyData') && $process_id) {
            $thirdparty_plugins = get_site_option('_thirdpartyplugins_' . $process_id, []);
            foreach ($thirdparty_plugins as $thirdparty_plugin) {
                $required[] = $thirdparty_plugin;
            }
        }
        
        if ($this->isDoingThisPrimeMoverProcess($input_post, 'markImportSuccess')) {
            $required[] = PRIME_MOVER_DEFAULT_ELEMENTOR_BASENAME;
        }
        
        return $required;
    }
    
    /**
     * Initialize MU plugin hooks
     */
    public function initMuHooks()
    {
        add_filter('option_active_plugins', [$this, 'loadOnlyPrimeMoverPlugin']);
        add_filter('site_option_active_sitewide_plugins', [$this, 'loadOnlyPrimeMoverPlugin']);        
        
        add_filter('stylesheet_directory', [$this, 'disableThemeOnPrimeMoverProcesses'], 10000);        
        add_filter('template_directory', [$this, 'disableThemeOnPrimeMoverProcesses'], 10000); 
        add_filter('admin_memory_limit', [$this, 'maybeAdjustAdminMemoryMaxLimit'], 100000, 1);
    }
    
    /**
     * Maybe adjust admin memory max limit
     * @param string $filtered_limit
     * @return string
     */
    public function maybeAdjustAdminMemoryMaxLimit($filtered_limit = '256M')
    {
        return PRIME_MOVER_MAX_MEMORY_LIMIT;
    }
    
    /**
     * Maybe increase allocated server resource like memory_limit
     */
    public function maybeIncreaseAllocatedResource()
    {
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit();
        }  
    }
    /**
     * Disable block hooks from interfering with Prime Mover processes
     * These are not needed.
     */
    public function maybeDisableSomeBlockHooks()
    {
        remove_action( 'init', 'register_block_core_file' );
        remove_action( 'init', 'register_block_core_navigation' );
        remove_action( 'init', '_register_theme_block_patterns' );
    }
    
    /**
     * Initialize plugin loaded hooks
     */
    public function initPluginsLoadedHook()
    {
        add_action('muplugins_loaded', [$this, 'primeMoverSwitchToBlog']);
        add_action('plugins_loaded', [$this, 'primeMoverRestoreCurrentBlog']);
    }
    
    /**
     * Disable cron when running Prime Mover backup/migration processes to prevent conflicts and save resources
     */
    public function disableCron()
    {
        remove_action( 'init', 'wp_cron' );
    }
    
    /**
     * Set locale on Prime Mover processes
     */
    public function setLocale()
    {
        $blog_id = $this->getBlogId();
        $this->locale = get_locale();
        
        if (is_multisite() && $blog_id) {
            
            $this->resetLocale();            
            switch_to_blog($blog_id);
            
            $this->locale = get_locale();
            restore_current_blog();      
            $this->resetLocale();
        }
    }
    
    /**
     * Reset locale helper
     */
    private function resetLocale()
    {
        /** @var Type $locale locale*/
        /** @var Type $wp_local_package*/
        global $locale, $wp_local_package;
        $locale = null;
        $wp_local_package = null;
    }
    
    /**
     * When in multisite - switch to correct blog so we can
     * manage the plugins specific to that subsite
     */
    public function primeMoverSwitchToBlog()
    {
        $blog_id = $this->maybeSwitchBlog();
        if ($blog_id) {
            $switched = switch_to_blog($blog_id);
            $this->setIsSwitched($switched);
        }
    }
    
    /**
     * Maybe switch blog
     * Return false on single site
     * Returns blog ID on multisite
     * @return boolean|number
     */
    private function maybeSwitchBlog()
    {        
        if (!is_multisite() || !wp_doing_ajax()) {
            return false;
        }
        $input_post = $this->getInputPost();
        $blog_id = $this->getBlogId();
        if ($blog_id && $this->isThirdPartyProcess($input_post)) {
            return $blog_id;
        }
        return false;
    }
    
    /**
     * Get blog ID
     * @return number
     */
    public function getBlogId()
    {
        $input_post = $this->getInputPost();        
        $blog_id = 0;
        
        if (!empty($input_post['multisite_blogid_to_import'])) {
            $blog_id = $input_post['multisite_blogid_to_import'];
        }
        if (!$blog_id && !empty($input_post['multisite_blogid_to_export'])) {
            $blog_id = $input_post['multisite_blogid_to_export'];
        }
        
        $blog_id = (int)$blog_id;
        if ($blog_id) {
            return $blog_id;
        }
        
        if (!empty($input_post['blog_id'])) {
            $blog_id = $input_post['blog_id'];
        }
        if (!$blog_id && !empty($input_post['blog_id'])) {
            $blog_id = $input_post['blog_id'];
        }
        
        $blog_id = (int)$blog_id;
        return $blog_id;
    }
    
    /**
     * Is third party process
     * @param array $input_post
     * @return boolean
     */
    private function isThirdPartyProcess($input_post = [])
    {
        return ($this->isDoingThisPrimeMoverProcess($input_post, 'maybeProcessThirdPartyData', 'import') || $this->isDoingThisPrimeMoverProcess($input_post, 'maybeGetThirdPartyCallBacks', 'export'));
    }
    
    /**
     * Restore current on `plugins_loaded` since we are done managing plugins at this point.
     */
    public function primeMoverRestoreCurrentBlog()
    {
        if ($this->getIsSwitched()) {
            restore_current_blog();
        }        
    }
    
    /**
     * Disable theme on Prime Mover process
     * @param mixed $theme
     * @return string
     */
    public function disableThemeOnPrimeMoverProcesses($theme) {
        return '';
    }

    /**
     * Load only Prime Mover plugin + selected third party plugins if required
     * @param mixed $plugins
     * @return array
     */
    public function loadOnlyPrimeMoverPlugin($plugins)
    {
        $input_post = $this->getInputPost();
        $required = $this->primeMoverMaybeAddThirdPartyApp($input_post);
        $current_filter = current_filter();
        $current_process = $this->getCurrentProcess();
        
        if ($this->primeMoverMaybeEnablePluginManagerLog($current_process)) {
            
            file_put_contents(PRIME_MOVER_PLUGIN_MANAGER_LOG, "INPUT PLUGINS BEFORE FILTERING:" . PHP_EOL, FILE_APPEND | LOCK_EX);
            file_put_contents(PRIME_MOVER_PLUGIN_MANAGER_LOG, print_r($plugins, true)  . PHP_EOL, FILE_APPEND | LOCK_EX);
            
            file_put_contents(PRIME_MOVER_PLUGIN_MANAGER_LOG, "CURRENT FILTER: $current_filter"  . PHP_EOL, FILE_APPEND | LOCK_EX);            
            file_put_contents(PRIME_MOVER_PLUGIN_MANAGER_LOG, "REQUIRED PLUGINS ON THIS PROCESS: $current_process"  . PHP_EOL, FILE_APPEND | LOCK_EX);
            file_put_contents(PRIME_MOVER_PLUGIN_MANAGER_LOG, print_r($required, true)  . PHP_EOL, FILE_APPEND | LOCK_EX);            
        }
        
        if ('site_option_active_sitewide_plugins' === $current_filter) {
            $plugins = array_filter(
                $plugins,
                function ($key) use ($required) {                    
                    return in_array($key, $required);
                },
                ARRAY_FILTER_USE_KEY
                );
        }
        
        if ('option_active_plugins' === $current_filter) {
            $plugins = array_filter($plugins, function($plugin) use ($required) {                
                return (in_array($plugin, $required));
            });
        }
        
        if ($this->primeMoverMaybeEnablePluginManagerLog($current_process)) {
            file_put_contents(PRIME_MOVER_PLUGIN_MANAGER_LOG, "FILTERED PLUGINS FINAL RESULT:" . PHP_EOL, FILE_APPEND | LOCK_EX);
            file_put_contents(PRIME_MOVER_PLUGIN_MANAGER_LOG, print_r($plugins, true)  . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        
        return $plugins;
    }   
}

function getPrimeMoverSanitizeStringFilter()
{
    $deprecated = false;
    if (version_compare(PHP_VERSION, '8.1.0') >= 0) {
        $deprecated = true;
    }
    
    if ($deprecated) {
        return FILTER_SANITIZE_FULL_SPECIAL_CHARS;
    }
    
    return FILTER_SANITIZE_STRING;
}

/**
 * Instantiate
 * @var PrimeMoverMustUsePluginManager $prime_mover_plugin_manager
 */
global $prime_mover_plugin_manager;
$prime_mover_plugin_manager = new \Codexonics\PrimeMoverFramework\general\PrimeMoverMustUsePluginManager(filter_input_array(INPUT_POST, getPrimeMoverSanitizeStringFilter()), filter_input_array(INPUT_GET, getPrimeMoverSanitizeStringFilter()));

$prime_mover_plugin_manager->setConstants();
$prime_mover_plugin_manager->initPluginsLoadedHook();

if ($prime_mover_plugin_manager->primeMoverMaybeLoadPluginManager()) {  
    $prime_mover_plugin_manager->setLocale();
    $prime_mover_plugin_manager->initMuHooks();
    $prime_mover_plugin_manager->maybeDisableSomeBlockHooks();
    $prime_mover_plugin_manager->maybeIncreaseAllocatedResource();
    $prime_mover_plugin_manager->disableCron();    
}
