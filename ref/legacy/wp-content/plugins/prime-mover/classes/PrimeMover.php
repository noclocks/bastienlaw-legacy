<?php
namespace Codexonics\PrimeMoverFramework\classes;

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
 * Prime Mover Main Class
 *
 * The complete migration solution for moving single-site to multisite platform and vice versa. 
 * This can also be used to migrate single-site to another single-site install or multisite subsite to 
 * another multisite subsite install.
 */
class PrimeMover
{
    private $hooked_methods;
    private $system_processors;
    
    /**
     * Constructor
     * @param PrimeMoverHookedMethods $hooked_methods
     * @param PrimeMoverSystemProcessors $system_processors
     */
    public function __construct(        
        PrimeMoverHookedMethods $hooked_methods,
        PrimeMoverSystemProcessors $system_processors
    ) 
    {
        $this->hooked_methods = $hooked_methods;
        $this->system_processors = $system_processors;
    }
    
    /**
     * Gets System authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @compatible 5.6
     */
    public function getSystemAuthorization()
    {
        return $this->getHookedMethods()->getSystemAuthorization();
    }

    /**
     * Gets hooked methods
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverHookedMethods
     * @compatible 5.6
     */
    public function getHookedMethods()
    {
        return $this->hooked_methods;
    }
    
    /**
     * Gets System Processors
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemProcessors
     * @compatible 5.6
     */
    public function getSystemProcessors()
    {
        return $this->system_processors;
    }
    
    /**
     * Get System Initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     * @compatible 5.6
     */
    public function getSystemInitialization()
    {
        return $this->getHookedMethods()->getSystemInitialization();
    }
    
    /**
     * Get Importer
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverImporter
     * @compatible 5.6
     */
    public function getImporter()
    {
        return $this->getSystemProcessors()->getImporter();
    }

    /**
     * Get exporter
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverExporter
     * @compatible 5.6
     */
    public function getExporter()
    {
        return $this->getSystemProcessors()->getExportUtilities()->getExporter();
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     * @compatible 5.6
     */
    public function getSystemFunctions()
    {
        return $this->getSystemProcessors()->getSystemFunctions();
    }
    
    /**
     * Get System checks
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemChecks
     * @compatible 5.6
     */
    public function getSystemChecks()
    {
        return $this->getHookedMethods()->getSystemChecks();
    }
    
    /**
     * Hook in methods
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itAddsInitHooksOnMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itAddsInitHooksOnSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itChecksIfHooksAreOutdated()
     */
    public function primeMoverLoadHooks()
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $notice_hook = 'admin_notices';
        if (is_multisite()) {
            $notice_hook = 'network_admin_notices';
        }
        /** Enqueue scripts and AJAX processor*/                
        foreach ($this->getSystemInitialization()->getPrimeMoverAjaxActions() as $action_name => $action_processor) {
            add_action("wp_ajax_{$action_name}", [$this,$action_processor]);
        }
        
        /**
         * GUI hooks for export, import and delete section in Network -> Sites
         * Hooked methods class: PrimeMoverHookedMethods
         */
        add_filter('manage_sites-network_columns', [$this, 'primeMoverAddNetworkColumn'], 10, 1);
        add_action('manage_sites_custom_column', [$this, 'primeMoverExportSection'], 10, 2);
        add_action('manage_sites_custom_column', [$this, 'primeMoverImportSection'], 10, 2);
        
        add_action('admin_enqueue_scripts', [$this, 'primeMoverEnqueueScripts'], 10, 1);        
        add_action($notice_hook, [$this, 'multisiteShowNetworkAdminNotice'] );
        
        /** System Initialization hooks */
        add_action('init', [$this, 'primeMoverCreateFolder'], 1);
        add_action('init', [$this, 'primeMoverCreateTmpDownloadsFolder'], 1); 
        add_action('init', [$this, 'primeMoverCreateLockFilesFolder'], 1); 
        add_action('admin_init', [$this, 'multisiteInitializeWpFilesystemApi']);
        add_action('init', [$this, 'multisiteInitializeWpFilesystemApiCli']);
        add_action('admin_init', [$this, 'initializeSiteExportDirectory'], 10);
        add_action('admin_init', [$this, 'initializeExportDirectoryProtection'], 11);
        add_action('admin_init', [$this, 'initializeTroubleShootingLog'], 12);
        add_action('admin_init', [$this, 'initializeSiteInfoLog'], 13);
        add_action('admin_init', [$this, 'initializeExportDirIdentity'], 14);
        add_action('admin_init', [$this, 'initializeCliMustUsePlugin'], 15);               
        
        /** System check hooks */
        add_action('init', [$this, 'systemCheckHooks']);
        add_filter('site_option_upload_filetypes', [$this, 'addZipFileTypeSupport'], 10, 3);
        add_filter('network_admin_plugin_action_links', [$this, 'addPluginActionLinks'], 99, 4);
        
        /** Added js body-class on sites network page */
        add_filter('admin_body_class', [$this, 'addJsBodyClassOnNetworkSitesPage'], 10, 1);
        
        /** Single site support */        
        add_action('prime_mover_exporter_block', [$this, 'primeMoverExportSection']);
        add_action('prime_mover_importer_block', [$this, 'primeMoverImportSection']);
        add_action('admin_menu', [$this, 'addExportImportOptionsSingleSite']);
        
        /** Basic menu related hooks */ 
        add_action('network_admin_menu', [$this,'addMenuPage']);
        add_action('admin_menu', [$this,'addMenuPage'], 0);
        add_action('admin_enqueue_scripts', [$this, 'removeDistractionsOnSettingsPage'], 5, 1);
        
        /** Translation-ready */
        add_action('init', [$this, 'loadPluginTextdomain']);        
    }   
    
    /**
     * Load plugin text domain
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itLoadsPluginTextDomain()
     */
    public function loadPluginTextdomain()
    {
        $this->getHookedMethods()->loadPluginTextdomain();
    }
    
    /**
     * Settings page cleanup
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itRemovesDistractionOnSettingsPage()
     */
    public function removeDistractionsOnSettingsPage()
    {
        $this->getHookedMethods()->removeDistractionsOnSettingsPage();
    }
    
    /**
     * Add menu page
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itAddsMenuPage() :
     */
    public function addMenuPage()
    {
        $this->getHookedMethods()->addMenuPage();
    }
    
    /**********************
     * SINGLE SITE SUPPORT*
     * ********************
     */
    
    /**
     * Add export - import option to single site
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itAddsExportImportSingleSiteOption()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itDoesNotAddExportImportSingleOptionInMultisite() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itDoesNotExportImportSingleOptionIfNotCorrectPage() 
     */
    public function addExportImportOptionsSingleSite()
    {
        if ( ! $this->getSystemFunctions()->maybeLoadMenuAssets() || is_multisite() ) {
            return;
        }
        
        $this->getHookedMethods()->addExportImportOptionsSingleSite();
    }
    
    /*****************
     * HOOKED METHODS*
     * ***************
     */
    
    /**
     * Add network column
     * @param array $column_headers
     * @return array|void|string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itAddsNetworkColumns()
     */
    public function primeMoverAddNetworkColumn($column_headers = [])
    {
        return $this->getHookedMethods()->primeMoverAddNetworkColumn($column_headers);
    }
    
    /**
     * Add js body class
     * @param string $classes
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itAddsJsBodyClassOnNetworkSitesPage()
     */
    public function addJsBodyClassOnNetworkSitesPage($classes = '')
    {
        return $this->getHookedMethods()->addJsBodyClassOnNetworkSitesPage($classes);
    }
    
    /**
     * Export section
     * @param string $column_name
     * @param number $blog_id
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itShowsPrimeMoverExportSection()
     */
    public function primeMoverExportSection($column_name = '', $blog_id = 0)
    {
        $this->getHookedMethods()->primeMoverExportSection($column_name, $blog_id);
    }
    
    /**
     * Import section
     * @param string $column_name
     * @param number $blog_id
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itShowsPrimeMoverImportSection()
     */
    public function primeMoverImportSection($column_name = '', $blog_id = 0)
    {
        $this->getHookedMethods()->primeMoverImportSection($column_name, $blog_id);
    }

    /**
     * Enqueue scripts
     * @param string $hook
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itEnqueuesPrimeMoverScripts() 
     */
    public function primeMoverEnqueueScripts($hook = '')
    {
        $this->getHookedMethods()->primeMoverEnqueueScripts($hook);
    }

    /**
     * Show network admin notice
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itShowsMultisiteNetworkAdminNotice()
     */
    public function multisiteShowNetworkAdminNotice()
    {
        $this->getHookedMethods()->multisiteShowNetworkAdminNotice();
    }
    
    /*********************
     * SYSTEM PROCESSORS**
     * *******************
     */
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itRunsPrimeMoverExportProcessor()
     */
    public function primeMoverExportProcessor()
    {
        $this->getSystemProcessors()->primeMoverExportProcessor();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itRunsPrimeMoverImportProcessor()
     */
    public function primeMoverImportProcessor()
    {
        $this->getSystemProcessors()->primeMoverImportProcessor();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itRunsImportProgressProcessor()
     */
    public function primeMoverImportProgressProcessor()
    {
        $this->getSystemProcessors()->primeMoverImportProgressProcessor();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itProcessesDeleteTmpFileProcessor()
     */
    public function primeMoverTempfileDeleteProcessor()
    {
        $this->getSystemProcessors()->primeMoverTempfileDeleteProcessor();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itRunsPrimeMoverUploadsProcessor()
     */
    public function primeMoverUploadsProcessor()
    {
        $this->getSystemProcessors()->primeMoverUploadsProcessor();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itRunsExportProgressProcessor() 
     */
    public function primeMoverExportProgressProcessor()
    {
        $this->getSystemProcessors()->primeMoverExportProgressProcessor();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itRunsPrimeMoverShutdownExportProcessor()
     */
    public function primeMoverShutdownExportProcessor()
    {
        $this->getSystemProcessors()->primeMoverShutdownExportProcessor();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itRunsPrimeMoverShutdownImportProcessor()
     */
    public function primeMoverShutdownImportProcessor()
    {
        $this->getSystemProcessors()->primeMoverShutdownImportProcessor();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itVerifyEncryptedPackage()
     */
    public function primeMoverVerifyEncryptedPackage()
    {
        $this->getSystemProcessors()->primeMoverVerifyEncryptedPackage();
    }
    /******************************
     * SYSTEM INITIALIZATION HOOKS*
     * ****************************
     */
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itCreatesFolder()
     */
    public function primeMoverCreateFolder()
    {
        $this->getSystemInitialization()->primeMoverCreateFolder();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itCreateTmpDownloadsFolder() 
     */
    public function primeMoverCreateTmpDownloadsFolder()
    {
        $this->getSystemInitialization()->primeMoverCreateTmpDownloadsFolder();
    }

    /**
     * Create lock files folder
     */
    public function primeMoverCreateLockFilesFolder()
    {
        $this->getSystemInitialization()->primeMoverCreateLockFilesFolder();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itInitializesWpFileSystemApi()
     */
    public function multisiteInitializeWpFilesystemApi()
    {
        $this->getSystemInitialization()->multisiteInitializeWpFilesystemApi();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itInitializesWpFileSystemApiCli() 
     */
    public function multisiteInitializeWpFilesystemApiCli()
    {
        $this->getSystemInitialization()->multisiteInitializeWpFilesystemApiCli();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itInitializesExportSiteDirectory() 
     */
    public function initializeSiteExportDirectory()
    {
        $this->getSystemFunctions()->initializeSiteExportDirectory();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itInitializesExportDirectoryProtection() 
     */
    public function initializeExportDirectoryProtection()
    {
        $this->getSystemInitialization()->initializeExportDirectoryProtection();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itInitializesTroubleShootingLog() 
     */
    public function initializeTroubleShootingLog()
    {
        $this->getSystemInitialization()->initializeTroubleShootingLog();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itInitializesSiteInfoLog()
     */
    public function initializeSiteInfoLog()
    {
        $this->getSystemInitialization()->initializeSiteInfoLog();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itInitializesExportDirIdentity() 
     */
    public function initializeExportDirIdentity()
    {
        $this->getSystemInitialization()->initializeExportDirIdentity();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itInitializesCliMustUsePlugin()
     */
    public function initializeCliMustUsePlugin()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $must_use_cli_script = $this->getSystemInitialization()->getCliMustUsePluginPath();
        if ($this->getSystemFunctions()->nonCachedFileExists($must_use_cli_script) && $this->getSystemFunctions()->getMd5File($must_use_cli_script) === $this->getSystemFunctions()->getMd5File(PRIME_MOVER_MUST_USE_PLUGIN_SCRIPT)) {
            return;
        }
        global $wp_filesystem;
        if (!$this->getSystemFunctions()->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
        if (wp_mkdir_p(WPMU_PLUGIN_DIR)) {
            $wp_filesystem->copy(PRIME_MOVER_MUST_USE_PLUGIN_SCRIPT, $must_use_cli_script, true);
        }
    }
        
    /****************
     * SYSTEM CHECKS*
     * **************
     * @compatible 5.6
     *
     */
    public function systemCheckHooks()
    {
        $this->getSystemChecks()->systemCheckHooks();
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itAddsZipFileTypeSupport()
     * @param string $value
     * @param string $option
     * @param number $network_id
     * @return string
     */
    public function addZipFileTypeSupport($value = '', $option = '', $network_id = 0)
    {
        return $this->getSystemChecks()->addZipFileTypeSupport($value, $option, $network_id);
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMover::itAddsPluginActionLinks()
     * @param array $actions
     * @param string $plugin_file
     * @param array $plugin_data
     * @param string $context
     * @return array|void|string
     */
    public function addPluginActionLinks($actions = [], $plugin_file = '', $plugin_data = [], $context ='')
    {
        return $this->getSystemChecks()->addPluginActionLinks($actions, $plugin_file, $plugin_data, $context);
    }
}
