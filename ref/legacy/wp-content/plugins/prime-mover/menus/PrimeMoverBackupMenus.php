<?php
namespace Codexonics\PrimeMoverFramework\menus;

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

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Backup Menus
 * Handles the backup menu functionality (available in core)
 *
 */
class PrimeMoverBackupMenus
{     
    private $prime_mover;
    private $utilities;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->utilities = $utilities;         
    }
  
    /**
     * Get backup utilities
     */
    public function getBackupUtilities()
    {
        $utilities = $this->getUtilities();
        return $utilities['backup_utilities'];
    }
    /**
     * Get component aux
     */
    public function getComponentAuxiliary()
    {
        $utilities = $this->getUtilities();
        return $utilities['component_utilities'];
    }
    
    /**
     * Get Freemius integration
     */
    public function getFreemiusIntegration()
    {
        $utilities = $this->getUtilities();
        return $utilities['freemius_integration'];  
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
     * Get utilities
     * @return array
     */
    public function getUtilities()
    {
        return $this->utilities;
    }
    
    /**
     * Get Prime Mover
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
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getPrimeMover()->getSystemInitialization();
    }
    
    /**
    * Initialize hooks
    * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itAddsInitHooks() 
    * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itChecksIfHooksAreOutdated()
    */
    public function initHooks()
    {
        add_action('prime_mover_run_menus', [$this, 'addBackupsMenuPage'], 0 );  
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'removeDistractionsOnBackupsPage'], 5, 1);
        
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setMigrationTools'], 90, 1 );
        add_filter('prime_mover_filter_subsites_with_backups', [$this, 'onlyShowsSitesThatExistsForBackups'], 10, 1);   
        add_action('prime_mover_do_extra_tablenav', [$this, 'addRefreshBackupButton']);   
        
        add_filter('prime_mover_delete_zip_after_unzip', [$this, 'dontDeleteIfRestoringFromBackup'], 10, 3 );
        add_filter('prime_mover_force_backup_refresh', [$this, 'maybeForceBackupRefreshOnChangeVersion'], 10, 4); 
        add_action('prime_mover_dothings_export_dialog', [$this, 'primeMoverManageBackupsSection'], 20, 1 );  
        
        add_action( 'admin_enqueue_scripts', [$this, 'maybeDisableHeartBeat'], 99 );
        add_action( 'wp_enqueue_scripts', [$this, 'maybeDisableHeartBeat'], 99 );
        
        add_filter('prime_mover_after_creating_tar_archive', [$this, 'logInProgressPackage'], 10, 2);        
        add_action('prime_mover_after_generating_download_url', [$this, 'maybeMarkPackageCompleted'], 10,  3);
        add_action('prime_mover_after_reallydeleting_package', [$this, 'maybeRemoveWipStatusOnError'], 10, 2);
    }
 
    /**
     * Maybe remove WIP package status on runtime error
     * @param string $path_to_delete
     * @param number $blog_id
     */
    public function maybeRemoveWipStatusOnError($path_to_delete = '', $blog_id = 0)
    {
        $this->getBackupUtilities()->maybeRemoveWipStatusOnError($path_to_delete, $blog_id);
    }
    
    /**
     * Maybe marked package completed - remove in-progress status
     * @param string $results
     * @param string $hash
     * @param number $blogid_to_export
     */
    public function maybeMarkPackageCompleted($results = '', $hash = '', $blogid_to_export = 0)
    {
        $this->getBackupUtilities()->maybeMarkPackageCompleted($results, $hash, $blogid_to_export);
    }
    
    /**
     * Log in-progress packages
     * @param array $ret
     * @param number $blogid_to_export
     * @return array
     */
    public function logInProgressPackage($ret = [], $blogid_to_export = 0)
    {
        return $this->getBackupUtilities()->logInProgressPackage($ret, $blogid_to_export);
    }    
    
    /**
     * Maybe disable heartbeat
     * If on backup menu page
     */
    public function maybeDisableHeartBeat()
    {
        if ($this->isReallyBackupMenuPage()) {
            wp_deregister_script( 'heartbeat' );
        }
    }
    
    /**
     * Show package manager to users in export dialog
     * Easy access to package manager specially in multisites
     * @param number $blog_id
     */
    public function primeMoverManageBackupsSection($blog_id = 0)
    {
        $this->getBackupUtilities()->primeMoverManageBackupsSection($blog_id);
    }
    
    /**
     * Maybe force backup refresh on change version (only updates version to dB when requested)
     * @param boolean $refresh
     * @param boolean $markup
     * @param number $blog_id
     * @param boolean $update
     * @return string|boolean
     */
    public function maybeForceBackupRefreshOnChangeVersion($refresh = false, $markup = false, $blog_id = 0, $update = false)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $refresh;
        }
        if ( ! $markup ) {
            return $refresh;
        }
        
        $blog_id = (int)$blog_id;
        $latest_backup_markup = PRIME_MOVER_BACKUP_MARKUP_VERSION;
        $option = $this->getSystemInitialization()->getPrimeMoverBackupMarkupVersion();        
        $backup_markup_db_version = $this->getSystemFunctions()->getBlogOption($blog_id, $option);   
        
        if ( ! $backup_markup_db_version ) {
            if ($update) {
                $this->getSystemFunctions()->updateBlogOption($blog_id, $option, $latest_backup_markup);
            }            
            return true;
        }
        
        if ($backup_markup_db_version !== $latest_backup_markup) {
            if ($update) {
                $this->getSystemFunctions()->updateBlogOption($blog_id, $option, $latest_backup_markup);
            }            
            return true;
        }
       
        return $refresh;
    }
    
    /**
     * Dont delete backup if restoring from backup
     * @param boolean $delete
     * @param string $filepath
     * @return string
     */
    public function dontDeleteIfRestoringFromBackup( $delete = true, $filepath = '', $export_array = array() )
    {
        if ( ! $filepath || ! $delete ) {
            return $delete;
        }
        if ($this->getSystemFunctions()->isFileResideInExportDir($filepath)) {
            $delete = false;
        }
        return $delete;
    }
    
    /**
     * Add refresh backup button in backup menu table
     */
    public function addRefreshBackupButton()
    {        
        $blog_id = $this->getBlogIdUnderQuery();
        if (!$this->getBackupUtilities()->blogIsUsable($blog_id)) {
            return;
        }
        $refresh_url = $this->getSystemFunctions()->getRefreshPackageUrl($blog_id);        
    ?>
        <a title="<?php esc_attr_e('Use this refresh button to scan newly added packages in your current site package path.', 'prime-mover')?>" href="<?php echo esc_url($refresh_url); ?>" class="button prime-mover-refresh-backups"><?php esc_html_e('Refresh packages', 'prime-mover'); ?></a>
    <?php     
    }
    
    /**
     * Only shows sites that exists for backups
     * @param array $sites
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itOnlyShowsSitesThatExistsForBackup() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itDoesNotFilterWhenNotMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itDoesNotFilterWhenNoSitesInBackup()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itDoesNotFilterWhenNotArray() 
     */
    public function onlyShowsSitesThatExistsForBackups($sites = [])
    {
        if ( ! is_array($sites) || empty($sites) ) {
            return $sites;
        }
        if ( ! $this->isReallyBackupMenuPage() || ! is_multisite() ) {
            return $sites;
        }
        return array_filter($sites, function($site){ 
            if (! get_blogaddress_by_id($site) ) {
                return false;
            }            
            return true;
        });
    }
    
    /**
     * Set migration tools URL
     * @param array $args
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itSetsMigrationToolssUrl()
     */
    public function setMigrationTools( array $args )
    {
        $args['prime_mover_migration_tools_url'] = $this->getSystemInitialization()->getMigrationToolsUrl(true);
        return $args;
    }
    
    /**
     * Remove distractions on backup page
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itRemovesDistractionsOnBackupsPage()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itDoesNotRemoveDistractionIfNotAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itDoesNotRemoveDistractionIfNotBackupPage() 
     */
    public function removeDistractionsOnBackupsPage()
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        if ( $this->isReallyBackupMenuPage() ) {
            remove_all_actions( 'admin_notices' );
        }
    }
    
    /**
     * Checks if we are on backup menu page by using page hook
     * @param string $hook
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itReturnsTrueIfBackupMenuPageInMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itReturnsFalseIfNotBackupMenu()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itReturnsTrueIfBackupMenuPageInSingleSite()
     */
    public function isBackupsMenuPage($hook = '')
    {
        return $this->getSystemInitialization()->isBackupsMenuPage($hook);
    }
    
    /**
     * Checks if really backup menu page
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itReturnsTrueIfBackupMenuPageInMultisite() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itReturnsFalseIfNotBackupMenu()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itReturnsTrueIfBackupMenuPageInSingleSite()
     */
    public function isReallyBackupMenuPage()
    {
        if (!is_admin()) {
            return false;    
        }
        
        if (!function_exists('get_current_screen')) {
            return false;   
        }
        
        $current_screen = get_current_screen();
        if (!is_object($current_screen)) {
            return false;
        }
        
        $hook = $current_screen->id;        
        return $this->isBackupsMenuPage($hook);
    }
    
    /**
     * Enqueue backup menu assets
     * @param string $hook
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itEnqueuesScriptsOnBackupPage() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itDoesNotEnqueueNotOnBackupPage() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itDoesNotEnqueueIfNotAuthorized() 
     */
    public function enqueueScripts($hook = '')
    {
        if ( ! $this->isBackupsMenuPage($hook)) {
            return;
        }
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $min = '.min';
        if ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) {
            $min = '';
        }
        $menu_js = "prime-mover-js-backup-menu$min.js";        
        
        wp_enqueue_style(
            'prime_mover_css_network_admin',
            esc_url_raw(plugins_url('res/css/prime_mover_css_backup_admin.css', dirname(__FILE__))),
            ['wp-jquery-ui-dialog'],
            PRIME_MOVER_VERSION
            );
        
        wp_enqueue_script(
            'prime_mover_backup_menu_js',
            esc_url_raw(plugins_url('res/js/' . $menu_js, dirname(__FILE__))),
            ['jquery', 'jquery-ui-core', 'jquery-ui-dialog'],
            PRIME_MOVER_VERSION
        );        
        
        $this->getComponentAuxiliary()->enqueueClipBoardJs(true);
        $backup_menu_url = $this->getSystemFunctions()->getBackupMenuUrl();
        $blog_id = $this->getBlogIdUnderQuery();
        
        if (is_multisite()) {
            $backup_menu_url = add_query_arg('prime-mover-select-blog-to-query', $blog_id, $backup_menu_url);
        }        
        
        wp_localize_script(
            'prime_mover_backup_menu_js',
            'prime_mover_js_backups_renderer',
            apply_filters( 'prime_mover_js_backups_renderer', [
                'deleteBackupFileWarning' => esc_js(__('Are you sure you want to delete these packages?', 'prime-mover')),
                'cancelbutton' => esc_js(__( 'Cancel', 'prime-mover' )),
                'deletebutton' => esc_js(__( 'Yes', 'prime-mover' )),
                'freerestorebutton' => esc_js(__( 'Yes', 'prime-mover' )),
                'download_text' => esc_js(__( 'Download', 'prime-mover' )),
                'restore_package_text' => esc_js(__( 'Restore package', 'prime-mover' )),
                'copy_restore_url' => esc_js(__('Copy restore URL', 'prime-mover')),
                'upgradetoprotext' => '<i class="dashicons dashicons-cart prime-mover-cart-dashicon"></i>' . esc_js(__('Upgrade to PRO', 'prime-mover')),
                'backup_menu_url' => esc_url($backup_menu_url)
            ])
        );
    }

    /**
     * Get create export URL
     * @param number $blog_id
     * @param boolean $dashboard_mode
     * @return string
     */
    protected function getCreateExportUrl($blog_id = 0, $dashboard_mode = false)
    {
        return $this->getSystemFunctions()->getCreateExportUrl($blog_id, $dashboard_mode);
    }
    
    /**
     * Add new backup markup
     * @param number $blog_id
     * @return string
     */
    protected function getAddNewBackupMarkup($blog_id = 0)
    {
        $enabled = false;
        $blog_id = (int) $blog_id;
        if (is_multisite() && ! $blog_id ) {
 
            $url = '#';
            $note = sprintf(esc_html__('You are not on a valid blog ID, you cannot create backups. Please enter blog ID.', 'prime-mover'), $blog_id);
            $class = "page-title-action button disabled prime-mover-autoexport-disabled";
            
        } elseif (is_multisite() && ! get_blogaddress_by_id($blog_id)) {
            
            $url = '#';
            $note = sprintf(esc_html__('Subsite with blog ID: %d does not exist, please create the subsite first.', 'prime-mover'), $blog_id);
            $class = "page-title-action button disabled prime-mover-autoexport-disabled";
            
        } else {
            $enabled = true;
            $note = esc_html__('Click to create new backup for this site.', 'prime-mover');
            $url = $this->getCreateExportUrl($blog_id);
            $class = "page-title-action";
            
        }
        
        $link_text = esc_html__('Create new package', 'prime-mover');
        $addnewbackup_button = [$note, $url, $class, $link_text];        
        $original_button_markup = $addnewbackup_button;
        list($note, $url, $class, $link_text) = apply_filters('prime_mover_addnew_backupmenu', $addnewbackup_button, $blog_id, $enabled, $original_button_markup);
        
        return '<a title="' . $note . '" href="' . $url . '" class="' . $class . '">' . $link_text . '</a>';
    }
    
    /**
     * Added menu page for backups
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itAddsBackupMenuPageCallBack()
     */
    public function addBackupsMenuPageCallBack()
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $testListTable = $this->getBackupsMenuListTableInstance();
        $testListTable->initHooks();
        $testListTable->prepare_items();       
        
        $blog_id = $this->getBlogIdUnderQuery();
        $blogexport_path = $this->getSystemFunctions()->getExportPathOfThisSubsite($blog_id);
        $export_path_exist = false;
        
        if ($blog_id && $this->getBackupUtilities()->blogIsUsable($blog_id) && $blogexport_path && wp_mkdir_p($blogexport_path)) {
            $export_path_exist = true;
        }
        if ($export_path_exist) {
            $this->getSystemInitialization()->camouflageFolders($blogexport_path);
        }
        ?>
      <div class="wrap prime-mover-backup-menu-wrap">
         <h1 class="wp-heading-inline"><?php echo apply_filters('prime_mover_filter_backuppage_heading', esc_html__('Prime Mover Packages', 'prime-mover'), $blog_id);?></h1>
         <?php echo $this->getAddNewBackupMarkup($blog_id); ?>
         <?php 
         if ($this->getBackupUtilities()->blogIsUsable($blog_id)) {
         ?>
             <p class="edit-site-actions prime-mover-edit-site-actions"><a href="<?php echo esc_url($this->getSystemFunctions()->getPublicSiteUrl($blog_id)); ?>"><?php esc_html_e('Visit Site', 'prime-mover');?></a> | 
             <a href="<?php echo $this->getCreateExportUrl($blog_id, true); ?>"><?php esc_html_e('Migration Tools', 'prime-mover'); ?></a> |
             <a class="prime-mover-external-link" target="_blank" href="<?php echo esc_url(CODEXONICS_PACKAGE_MANAGER_RESTORE_GUIDE); ?>">
             <?php esc_html_e('Restore Guide', 'prime-mover'); ?></a>
             </p>
         <?php 
         }
         ?>         
        <div id="icon-users" class="icon32"><br/></div>     
        <?php do_action('prime_mover_package_manager_notices', $blog_id); ?>   
        <div class="prime-mover-backupmenu-notes-div">        
            <p>
                <?php printf(esc_html__('This page shows all the packages controlled by %s', 'prime-mover'), '<strong>' . PRIME_MOVER_PLUGIN_CODENAME . '</strong>');?>.
                <?php if ($export_path_exist) { ?> 
                <?php esc_html_e('This is the current site package path:', 'prime-mover'); ?> 
                <tt>
                <code title="<?php esc_attr_e('You can manually add or upload Prime Mover package zip in this path via SFTP.', 'prime-mover');?>"><?php echo $blogexport_path; ?></code></tt>
                <?php } ?>
            </p> 
        </div>
        
        <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
        <form id="prime_mover_backups-filter" method="get">
            <?php $this->getUserConfirmationDialog(); ?>
            <?php $this->getUserConfirmationFreeRestoreDialog(); ?>
            <?php do_action('prime_mover_dosomething_freerestore_form'); ?>
            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
            <input type="hidden" name="page" value="<?php esc_attr_e($_REQUEST['page']) ?>" />
            <input type="hidden" name="prime-mover-blog-id-menu" value="<?php esc_attr_e($this->getBlogIdUnderQuery())?>" />
            <!-- Now we can render the completed list table -->
            <?php $testListTable->display() ?>
        </form>         
      </div>       
    <?php
    }
    
    /**
     * Get user confirmation dialog when deleting backups
     */
    protected function getUserConfirmationDialog()
    {
    ?>
       <div style="display:none;" id="js-prime-mover-confirm-backups-delete" title="<?php esc_attr_e('Warning!', 'prime-mover')?>"> 
			<h3><span></span></h3>  	  	
        </div>
    <?php      
    }
    
    /**
     * Get user confirmation dialog when restoring free backups
     */
    protected function getUserConfirmationFreeRestoreDialog()
    {
        $text = esc_html__('This will replace your website files and content.', 'prime-mover');
        if (is_multisite()) {
            $text = esc_html__('This will replace your subsite files and content.', 'prime-mover');
        }
        ?>
       <div style="display:none;" id="js-prime-mover-confirm-backups-free-restore" title="<?php esc_attr_e('Warning!', 'prime-mover')?>"> 
			<h3><?php esc_html_e('Are you sure you want restore this package?', 'prime-mover');?></h3>  
			<p><span><?php echo $text; ?></span></p>	  	
        </div>
    <?php      
    }
  
    /**
     * Get blog ID under query in the backups menu list table
     * This will always return "1" in single-site
     * In multisite, may differ depending on what blog is queried.
     * @return number
     */
    protected function getBlogIdUnderQuery()
    {
        if (is_multisite()) {
            return $this->getCanonicalSiteInMultisite();
            
        } else {
            return 1;
        }
    }
    
    /**
     * Get sites with backups in multisites
     * @return array
     */
    protected function getSitesWithBackups()
    {
        return $this->getComponentAuxiliary()->getSitesWithBackupsInMultisite();
    }
    
    /**
     * Get canonical site in multisite
     * @return NULL|mixed
     */
    private function getCanonicalSiteInMultisite()
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return null;
        }
        
        $queried = $this->getSystemInitialization()->getUserInput('get',
            [
                'prime-mover-select-blog-to-query' => FILTER_SANITIZE_NUMBER_INT
            ], 'prime_mover_backups');
        
        if ( ! empty($queried['prime-mover-select-blog-to-query'] ) ) {
            return $queried['prime-mover-select-blog-to-query'];    
        }
        
        $sites_with_backups = $this->getSitesWithBackups();
        if (empty($sites_with_backups) || ! is_array($sites_with_backups)) {
            return null;    
        } 

        return reset($sites_with_backups);
        
    }
    /**
     * Get menu list table instance
     * @return \Codexonics\PrimeMoverFramework\menus\PrimeMoverBackupMenuListTable
     */
    protected function getBackupsMenuListTableInstance()
    {
        $prime_mover = $this->getPrimeMover();
        $utilities = $this->getUtilities();
        $blog_id = $this->getBlogIdUnderQuery();
        $sites_with_backups = $this->getSitesWithBackups();
        
        return new PrimeMoverBackupMenuListTable($prime_mover, $utilities, $blog_id, $sites_with_backups);        
    }
    
    /**
     * Added menu page for backups
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itAddsBackupMenuPageOnSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itAddsBackupMenuPageOnMultisite()
     */
    public function addBackupsMenuPage()
    {
        $required_cap = 'manage_network_options';
        if ( ! is_multisite() ) {
            $required_cap = 'manage_options';
        }
        
        add_submenu_page( 'migration-panel-settings', esc_html__('Packages', 'prime-mover'), esc_html__('Packages', 'prime-mover'),
            $required_cap, 'migration-panel-backup-menu', [$this, 'addBackupsMenuPageCallBack']);
    }   
}