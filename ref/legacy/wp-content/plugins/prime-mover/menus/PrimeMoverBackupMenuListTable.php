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

use WP_List_Table;
use Codexonics\PrimeMoverFramework\classes\PrimeMover;

/**
 * Prime Mover Backups List Table
 *
 * The class aims to provide backups list for which user has to manage.
 *
 */
class PrimeMoverBackupMenuListTable extends WP_List_Table 
{    
    private $prime_mover;
    private $import_utilities;
    private $component_utilities;
    private $blog_id;   
    private $sys_utilities;
    private $sites_with_backups;
    private $backup_utilities;
    private $openssl_utilities;
    
    /**
     * Initialize hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itAddsInitHooks() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itChecksIfHooksAreOutdated()
     */
    public function initHooks()
    {
        add_filter("prime_mover_get_validation_id_prime_mover_backup_menu_sort", [$this, 'returnPrimeMoverBackupMenuSort'], 10, 1);
    }
 
    /**
     * Get backup menu sort validation
     * @return string[]|string[][]|array[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itReturnsPrimeMoverBackupMenuSort()
     */
    public function returnPrimeMoverBackupMenuSort()
    {
        $sortable_columns = $this->get_sortable_columns();
        $sortables = array_keys($sortable_columns);
        return [
            'page' => 'migration-panel-backup-menu',
            'order' => ['asc', 'desc'],
            'orderby' => $sortables
        ];
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
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getPrimeMover()->getSystemAuthorization();
    }
    
    /**
     * Get component aux instance
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverComponentAuxiliary
     */
    public function getComponentUtilities()
    {
        return $this->component_utilities;
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
     * 
     * Get import utilities
     */
    public function getImportUtilities()
    {
        return $this->import_utilities;
    }
    
    /**
     * Get system utilities
     * @return array
     */
    public function getSystemUtilities()
    {
        return $this->sys_utilities;
    }
    /**
     * 
     * Get export utilities
     */
    public function getExportUtilities()
    {
        return $this->getImportUtilities()->getExportUtilities();
    }    
    
    /**
     * Get sites with backups
     * @return array
     */
    public function getSitesWithBackups()
    {
        return $this->sites_with_backups;
    }
    
    /**
     * Get openSSL utilities
     * @return array
     */
    public function getOpenSSLUtilities()
    {
        return $this->openssl_utilities;
    }
    
    /**
     * Constructor
     * @param PrimeMover $prime_mover
     * @param array $utilities
     * @param number $blog_id
     * @param array $sites_with_backups
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [], $blog_id = 0, $sites_with_backups = [])
    {        
        $this->prime_mover = $prime_mover;
        $this->component_utilities = $utilities['component_utilities'];
        $this->import_utilities = $utilities['import_utilities'];
        $this->blog_id = $blog_id;
        
        $this->sys_utilities = $utilities['sys_utilities'];
        $this->sites_with_backups = $sites_with_backups;
        $this->backup_utilities = $utilities['backup_utilities'];
        $this->openssl_utilities = $utilities['openssl_utilities'];
        
        parent::__construct( [
            'singular'  => 'prime_mover_backup',     
            'plural'    => 'prime_mover_backups',    
            'ajax'      => false        
        ] );        
    }    
 
    /**
     * Get backup utilities
     */
    public function getBackupUtilities()
    {
        return $this->backup_utilities;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see WP_List_Table::no_items()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itShowsNoItems()
     */
    public function no_items() {
        _e( 'No packages found.' );
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see WP_List_Table::column_default()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itReturnsDefaultColumn() 
     */
    public function column_default($item, $column_name)
    {
        switch($column_name){
            default:
                return print_r($item,true);
        }
    }    
    
    /**********************
     * COLUMN DEFINITIONS*
     * ********************
     */    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itReturnsPackageNameColumn() 
     */
    public function column_package_name($item)
    {        
        return $item['package_name'];
    }  
    
    public function column_target_blog_id($item)
    {
        return $item['target_blog_id'];
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itReturnsPackageSiteTitleColumn()
     */
    public function column_site_title($item)
    {
        return $item['site_title'];
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itReturnsPackageSitePackageType()
     */
    public function column_package_type($item) 
    {
        return strtoupper($item['package_type']);
    }    
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itGetsColumnPackageMode()
     */
    public function column_package_mode($item) 
    {
        return $item['package_mode'];
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itGetsColumnDateCreated() 
     */
    public function column_date_created($item) 
    {
        return $item['date_created'];
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itGetsColumnPackageSize() 
     */
    public function column_package_size($item) 
    {
        return $item['package_size'];
    }    
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itGetsColumnRestoreBackup() 
     */
    public function column_restore_backup($item) 
    {
        return $item['restore_backup'];
    }
  
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itGetsColumnUsers()
     */
    public function column_include_users($item)
    {
        if (isset($item['include_users'])) {
            return $item['include_users'];
        } else {
            return '---';    
        }        
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenuListTable::itGetsMigrateColumns() 
     */
    public function column_migrate($item)
    {        
        return $this->getMigrateSiteBackup($item);
    }
    
    public function column_download($item)
    {
        return '<a class="button prime-mover-menu-button prime-mover-download-button-backup js-prime-mover-download-button-backup" href="' . esc_url($item['download_url']) . '" title="' . 
            esc_attr__('Download this package to your Desktop', 'prime-mover') . '">' . esc_html__('Download', 'prime-mover') . '</a>';        
    }

    /**
     * Get migrate site URL to copy to clipboard
     * @param array $item
     * @return string
     */
    protected function getMigrateSiteBackup($item = [])
    {        
        $blog_id = $this->getBlogId();
        list($note, $url, $class, $link_text, $clipboard_span, $data_clipboard_id) = apply_filters('prime_mover_filter_migratesites_column_markup',[
            esc_attr__('Upgrade to PRO to save time migrating sites.', 'prime-mover'),
            "#",
            "button disabled prime-mover-menu-button",
            esc_html__('PRO version only', 'prime-mover'),
            '',
            ''
        ], $item, $blog_id);
          
        return '<button type="button" class="' . esc_attr($class) . '" data-clipboard-text="' . esc_url($url) . '" title="' . $note . '" data-clipboard-id="' . 
        $data_clipboard_id . '">' . $link_text . '</button>' . $clipboard_span;          
    }
    
    /**
     * Row actions
     * {@inheritDoc}
     * @see WP_List_Table::handle_row_actions()
     */
    protected function handle_row_actions( $item = [], $column_name = '', $primary = '' ) {
        if ($primary === $column_name) {
    ?>
        <div class="row-actions">
            <span class="prime-mover-download-row-action" id="js-prime-mover-download-row-action">
                <a href="<?php echo esc_url($item['download_url'])?>" aria-label="Download"><?php esc_html_e('Download', 'prime-mover'); ?></a> |
            </span>
            <span class="prime-mover-blog-id-row-action" id="js-prime-mover-blog-id-row-action">
                <?php esc_html_e('Blog ID', 'prime-mover'); ?> : <?php echo $item['target_blog_id']; ?> | 
            </span>            
            <span class="prime-mover-encryption-row-action" id="js-prime-mover-encryption-row-action">
                <?php esc_html_e('Encrypted', 'prime-mover'); ?> : <?php echo $item['encryption_status']; ?> |
            </span>
        </div>
        <button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'prime-mover' ) ?></span></button>
    <?php             
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see WP_List_Table::column_cb()
     */
    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
             $this->_args['singular'],  
             $item['package_filepath']                
            );
    }    
  
    /**
     * 
     * {@inheritDoc}
     * @see WP_List_Table::get_columns()
     */
    public function get_columns()
    {
        $columns = [
            'cb'        => '<input type="checkbox" />',             
            'package_name' => esc_html__('Package name', 'prime-mover'),
            'package_type' => esc_html__('Package type', 'prime-mover'),
            'target_blog_id' => esc_html__('Target blog ID', 'prime-mover'),
            'package_mode' => esc_html__('Package mode', 'prime-mover'),            
            'site_title' => esc_html__('Site title', 'prime-mover'),               
            'date_created' => esc_html__('Date created', 'prime-mover'),
            'package_size' => esc_html__('Package size', 'prime-mover'),  
            'include_users' => esc_html__('Users exported', 'prime-mover'),
            'restore_backup' => esc_html__('Restore package', 'prime-mover'),
            'download' => esc_html__('Download package', 'prime-mover')
        ];
        
        return apply_filters('prime_mover_filter_package_manager_columns', $columns, $this->getBlogId());
    } 
    
    /**
     * Get columns description
     * @return string[]|NULL[]
     */
    public function getColumnsDescription()
    {
        return [            
            'package_name' => __('File name of the package.', 'prime-mover'),
            'package_type' => __('This is MULTISITE if package targets multisite or SINGLE-SITE if package targets WordPress single site.', 'prime-mover'),
            'target_blog_id' => __("This is the target subsite blog ID for a MULTISITE package. This is always '1' for a SINGLE SITE or if it's a MULTISITE MAIN SITE.", 'prime-mover'),
            'package_mode' => __('This describes what is included in the package.', 'prime-mover'),            
            'site_title' => __('This is the site title.', 'prime-mover'),                        
            'date_created' => __('This is the date the package is created.', 'prime-mover'),
            'package_size' => __('This is the size of the package.', 'prime-mover'),
            'include_users' => __('By default, users are included in the package.', 'prime-mover'),
            'restore_backup' => __('Restore this package to this site.', 'prime-mover'),
            'migrate' => __('Migrate package to target site via remote URL migration. This feature requires a PRO version on the target site.', 'prime-mover'),
            'download' => __('Download this package to your Desktop. You can also download using cPanel and FTP by going to the current site package path.', 'prime-mover')
        ];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see WP_List_Table::get_sortable_columns()
     */
    public function get_sortable_columns() 
    {
        $sortable_columns = [             
            'package_name' => ['package_name',false],
            'target_blog_id' => ['target_blog_id',false],
            'site_title' => ['site_title',false],
            'package_type' => ['package_type',false],
            'package_mode' => ['package_mode',false],
            'date_created' => ['date_created',false],
            'package_size' => ['package_size',false],  
            'include_users' => ['include_users',false], 
        ];
        
        return $sortable_columns;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see WP_List_Table::get_bulk_actions()
     */
    public function get_bulk_actions() 
    {
        $actions = [
            'delete' => esc_html__('Delete', 'prime-mover'),
        ];
        return $actions;
    }
    
    /**
     * Process bulk action
     */
    public function processBulkAction() 
    {          
        if ( ! $this->isDeletingAuthorized()) {
            return;
        }            
        $site_array = $this->getSystemInitialization()->getUserInput('get',
            [                    
               'prime-mover-blog-id-menu' => FILTER_SANITIZE_NUMBER_INT
            ], 'prime_mover_backups', 'common', 0);
        
        $blog_id = $site_array['prime-mover-blog-id-menu'];            
        $input_data =  $this->getSystemInitialization()->getUserInput('get', 
            [
                'prime_mover_backup' => [
                'filter' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
                'flags'  => FILTER_REQUIRE_ARRAY,
            ]
            ], 'prime_mover_backups', '', $blog_id, true, true);
        
        if (empty($input_data['prime_mover_backup']) || ! is_array($input_data['prime_mover_backup'])) {
            return;
        }   
        $deleted = [];
        $blog_id = $this->getBlogId();
        foreach ($input_data['prime_mover_backup'] as $backup) {
            $delete_result = $this->getPrimeMover()->getSystemFunctions()->primeMoverDoDelete($backup);           
            if ($delete_result) {
                $deleted[] = $backup;
                $this->getSystemUtilities()->cleanOptionsRelatedToThisBackup($backup, $blog_id);
            }
            do_action('prime_mover_after_singlezipfile_delete', $blog_id);
        }
        
        if ( ! empty($deleted) ) {
            $this->deleteSuccessNotice($deleted);
        }                        
    }
 
    /**
     * Checks if deleting is authorized
     * @return boolean
     */
    protected function isDeletingAuthorized()
    {        
        $input = $this->getSystemInitialization()->getUserInput('get', ['_wpnonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()], 'prime_mover_backups');
        return ('delete' === $this->current_action() && $this->getSystemAuthorization()->isUserAuthorized() && isset($input['_wpnonce']) && 
            $this->getPrimeMover()->getSystemFunctions()->primeMoverVerifyNonce($input['_wpnonce'], 'bulk-prime_mover_backups', true));
    }
    
    /**
     * Delete success notice
     */
    protected function deleteSuccessNotice($backups = []) 
    { 
    ?>	
    	<div class="notice notice-success is-dismissible">
    		<p><?php _e('Successfully deleted the following backups', 'prime-mover'); ?>: </p>
    		<ul class="ul-disc">
    		<?php 
    		foreach ($backups as $backup) {
    		?>    
    		    <li><?php echo $backup;?></li>
    		<?php 
    		}
    		?>
    		</ul>
    	</div>	
    <?php 
    }

    /**
     * Filters for backup menu table
     * {@inheritDoc}
     * @see WP_List_Table::extra_tablenav()
     */
    protected function extra_tablenav( $which ) 
    {       
        if ('bottom' === $which) {
            return;    
        }
        $this->displayMultisiteBlogIdSelectors();
        do_action('prime_mover_do_extra_tablenav');
    }
  
    /**
     * Display multisite blog ID selectors
     */
    protected function displayMultisiteBlogIdSelectors()
    {
        if ( ! is_multisite() ) {
            return;
        }
        $blog_ids = $this->getSitesWithBackups();
        $queried_id = $this->getBlogId();
        ?>
        <p class="prime-mover-backup-menu-site-selection"><label><span><?php esc_html_e("Select or Enter a blog ID", 'prime-mover');?></span></label>
        <?php if ( ! $this->isSafari()) { ?>
        <span class="myarrow">
        <?php } ?>
        <input class="prime-mover-site-selector js-prime-mover-site-selector" list="prime-mover-backups-datalist" size="12" value="<?php echo esc_attr($queried_id); ?>" name="prime-mover-select-blog-to-query" />
        <?php if ( ! $this->isSafari()) { ?>
        </span>
        <?php } ?>
        <datalist id ="prime-mover-backups-datalist" >
            <?php 
            foreach ($blog_ids as $blog_id) {
            ?>
                <option value="<?php echo esc_attr($blog_id);?>"><?php echo $blog_id; ?></option>  
            <?php   
            }
            ?>
        </datalist>
        <button class="button js-prime-mover-clear-site prime-mover-clear-site" type="button"><?php esc_html_e('Clear value to select new site', 'prime-mover'); ?></button></p>
    <?php       
    }
    
    /**
     * Check if Safari
     * @return boolean
     */
    private function isSafari()
    {
        return (isset($_SERVER['HTTP_USER_AGENT']) && false !== stripos( $_SERVER['HTTP_USER_AGENT'], 'Safari' ) && false === stripos( $_SERVER['HTTP_USER_AGENT'], 'Chrome'));      
    }
    
    /**
     * Get blog ID
     * @return number
     * @codeCoverageIgnore
     */
    public function getBlogId()
    {
        return $this->blog_id;
    }
    
    /**
     * Get backups data
     * @return array|string
     */
    protected function getBackupsData()
    {
        $blog_id = $this->getBlogId();
        if (is_multisite() && ! get_blogaddress_by_id($blog_id) ) {
            return [];    
        }
        $backups = $this->getComponentUtilities()->getValidatedBackupsInExportDirectoryCached($blog_id);       
        $current_backup_hash = $this->getBackupUtilities()->computeBackupHash($backups, $blog_id);
        
        if (!empty($backups)) { 
            $this->maybeMarkSiteWithBackups($blog_id);
            uasort($backups, function($a, $b) {
                return $b['date'] - $a['date'];
            });                
                return $this->formatDataForListTableDisplay($backups, $blog_id, $current_backup_hash);                
        } else {
            return [];    
        }        
    }
    
    /**
     * Maybe mark site with backups in multisite
     * @param number $blog_id
     * @mainsitesupport_affected
     */
    protected function maybeMarkSiteWithBackups($blog_id = 0)
    {
        if ( ! is_multisite() || ! $blog_id ) {
            return;    
        }
        
        $this->getPrimeMover()->getSystemFunctions()->doScreenOptionSettings('update', $blog_id);        
    }
    
    /**
     * Formatted data list table display
     * @param array $backups
     * @param number $blog_id
     * @param string $current_validated_backup_hash
     * @return []
     * @mainsitesupport_affected
     */
    protected function formatDataForListTableDisplay($backups = [], $blog_id = 0, $current_backup_hash = '')
    {        
        $formatted = [];     
        
        $refresh = false;
        $option_name = $this->getSystemInitialization()->getPrimeMoverMenuBackupsOption();
        
        $backups_array = $this->getBackupUtilities()->getValidatedBackupsArrayInDb($option_name);
        $backups_hash_db = $this->getBackupUtilities()->getBackupsHashInDb($backups_array, $blog_id);
        
        if ($this->getBackupUtilities()->maybeRefreshBackupData($backups_hash_db, $current_backup_hash, true, $blog_id, true)) {
            $refresh = true;
        }
        
        if ( ! $refresh && ! empty($backups_array[$blog_id]) ) {
            return reset($backups_array[$blog_id]);
        }    
        
        foreach ($backups as $filename => $backup_meta) {  
            $backup_array = [];
            $sanitized_name = sanitize_html_class($filename);
            $tar_mode = false;
            if ($this->getPrimeMover()->getSystemFunctions()->hasTarExtension($filename)) {
                $tar_mode = true;
            }
            $tar_config = [];
            if ($tar_mode && !empty($backup_meta['filepath'])) {
                $tar_config = apply_filters('prime_mover_get_tar_package_config_from_file', [], $backup_meta['filepath']);
            }
            $backup_array['package_name'] = $filename;

            $backup_array['package_mode'] = $this->getExportUtilities()->getExportModeOfThisBackup($blog_id, $sanitized_name);            
            $date_created = '';
            if ( ! empty($backup_meta['date']) ) {
                $date_created = $this->getPrimeMover()->getSystemFunctions()->getPackageCreationDateTime($backup_meta['date']);
            }
            
            $backup_array['date_created'] = $date_created;
            $backup_array['package_size'] = $backup_meta['filesize'];
            $backup_array['package_size_raw'] = $backup_meta['filesize_raw'];
            $encryption_status = $this->getComponentUtilities()->getEncryptionStatusGivenOption($blog_id, $sanitized_name);
            
            $backup_array['encryption_status'] = $this->getEncryptionStatusForTable($encryption_status);            
            $backup_array['package_filepath'] = $backup_meta['filepath'];
            if (isset($backup_meta['site_title'])) {
                $backup_array['site_title'] = $backup_meta['site_title'];
            } else {
                $backup_array['site_title'] = $this->getImportUtilities()->getSiteTitleFromZipPackage($backup_meta['filepath']);
            }            
            if ($tar_mode) {
                $target_blog_id = (int)$tar_config['prime_mover_export_targetid'];                
            } else {
                $target_blog_id = (int)$this->getImportUtilities()->getRealBlogIDFromZipPackage(null, 0, $backup_meta['filepath']);
            }            
            
            $package_type = $this->getPackageType($target_blog_id, $tar_config);
            $backup_array['package_type'] = $package_type;
            $can_decrypt_package = $this->getOpenSSLUtilities()->maybeCanDecryptPackage($backup_meta['filepath'], $blog_id, $tar_config, $tar_mode, $encryption_status);
            $backup_array['restore_backup'] = $this->getRestoreUrlMarkup($blog_id, $sanitized_name, $package_type, $target_blog_id, $backup_meta['filepath'], $encryption_status, $can_decrypt_package);
            
            $backup_array['target_blog_id'] = $target_blog_id;
            $backup_array['download_url'] = $this->getComponentUtilities()->generateDownloadURLForClipBoard($sanitized_name, $blog_id, false, true);
            $backup_array['sanitized_package_name'] = $sanitized_name;
            
            if (isset($backup_meta['include_users'])) {
                $backup_array['include_users'] = $backup_meta['include_users'];
            }            
            $formatted[] = $backup_array;
        }
        
        $this->getBackupUtilities()->updateValidatedBackupsArrayInDb($formatted, $current_backup_hash, $option_name, $blog_id, $backups_hash_db);
        
        do_action('prime_mover_after_packages_update', $backups, $refresh, $blog_id, $current_backup_hash);
        
        return $formatted;
    }
        
    /**
     * Get encryption text
     * @param string|boolean $encryption_status
     * @return string
     */
    private function getEncryptionText($encryption_status)
    {
        if (is_bool($encryption_status)) {
            return $encryption_status ? 'true' : 'false';
        } else {
            return $encryption_status;
        } 
    }
    
    /**
     * Get encryption status for table display
     * @param mixed $encryption_status
     * @return string
     */
    private function getEncryptionStatusForTable($encryption_status)
    {       
        $encryption_status = $this->getEncryptionText($encryption_status);        
        $values = ['false' => esc_html__('NO', 'prime-mover'), 'true' => esc_html__('YES', 'prime-mover')];
        
        if ( ! isset($values[$encryption_status])) {
            return esc_html__('NO', 'prime-mover');
        } 
        
        return $values[$encryption_status];        
    }
    
    /**
     * Maybe enable restore link
     * @param string $package_type
     * @return boolean
     */
    protected function maybeEnableRestoreLink($package_type = '')
    {
        $current_site_type = $this->getCurrentSiteType();        
        return ($current_site_type === $package_type);       
    }
    
    /**
     * Get current site type
     * @return string
     */
    private function getCurrentSiteType()
    {
        $current_site_type = 'single-site';
        if (is_multisite()) {
            $current_site_type = 'multisite';
        }
        
        return $current_site_type;
    }
    
    /**
     * 
     * @param number $blog_id
     * @param string $sanitized_name
     * @param string $package_type
     * @param number $target_blog_id
     * @param string $backup_filepath
     * @param string $encryption_status
     * @param boolean $can_decrypt_package
     * @return string
     */
    protected function getRestoreUrlMarkup($blog_id = 0, $sanitized_name = '', $package_type = '', $target_blog_id = 0, $backup_filepath = '', $encryption_status = 'false', $can_decrypt_package = true)
    {        
        $target_blog_id = (int) $target_blog_id;
        $blog_id = (int) $blog_id;
        $enable_restore_link = false;
        $link_text = esc_html__('Restore package', 'prime-mover');
        $url = "#";
        $class = "button disabled js-prime-mover-restore-icon prime-mover-menu-button";
        $link_active = false;
        
        if (!is_multisite() && 'single-site' === $package_type && false === apply_filters('prime_mover_is_loggedin_customer', false)) {            
            list($url, $class, $note, $link_text, $link_active) = $this->restoreFreeBackupParameters($blog_id, $backup_filepath, $encryption_status);
            
        } elseif (!is_multisite() && 'multisite' === $package_type) {           
            $note = sprintf(esc_html__('You cannot restore a %s package to a %s configuration.', 'prime-mover'), $package_type, $this->getCurrentSiteType());
            
        } elseif (is_multisite() && 'multisite' === $package_type && $target_blog_id === $blog_id && false === apply_filters('prime_mover_is_loggedin_customer', false)) {            
            list($url, $class, $note, $link_text, $link_active) = $this->restoreFreeBackupParameters($blog_id, $backup_filepath, $encryption_status);
            
        } elseif (is_multisite() && 'multisite' === $package_type && $target_blog_id !== $blog_id ) {            
            $note = sprintf(esc_html__('You cannot restore a multisite package with blog ID of %d to a subsite with blog ID of %d', 'prime-mover'), $target_blog_id, $blog_id);                        
            
        } elseif (is_multisite() && 'multisite' === $package_type && $target_blog_id === $blog_id && ! get_blogaddress_by_id($blog_id)) {           
            $note = sprintf(esc_html__('Subsite with blog ID: %d does not exist, please create the site first.', 'prime-mover'), $blog_id);                       
        
        } elseif (is_multisite() && 'multisite' === $package_type && $target_blog_id === $blog_id && false === apply_filters('prime_mover_multisite_blog_is_licensed', false, $blog_id)) {  
            list($url, $class, $note, $link_text, $link_active) = $this->restoreFreeBackupParameters($blog_id, $backup_filepath, $encryption_status);
            
        } elseif ($this->maybeEnableRestoreLink($package_type)) {   
            $enable_restore_link = true;
            $note = esc_html__('Click to restore this package to this site.', 'prime-mover');  
            $url = $this->getRestoreUrl($blog_id, $sanitized_name);
            $class = "button prime-mover-menu-button js-prime-mover-restore-icon";            
            
        } else {            
            $note = sprintf(esc_html__('You cannot restore a %s package to a %s configuration.', 'prime-mover'), $package_type, $this->getCurrentSiteType());                    
        }
        
        if ($enable_restore_link) {
            $link_active = true;
        }
        if (!$can_decrypt_package && $enable_restore_link) {            
            $note = esc_html__('Unable to read encrypted package. A correct decryption key is required to restore this package.', 'prime-mover');
            $class = "button disabled js-prime-mover-restore-icon prime-mover-menu-button";
            $url = "#";
            $link_active = false;
        }
        
        $original = [$url, $class, $note, $link_text];
        $final = $original;
        
        list($url, $class, $note, $link_text) = apply_filters('prime_mover_restore_backup_parameters_menu', $final, $original, $blog_id, $link_active, $target_blog_id, 
            $can_decrypt_package, $enable_restore_link);        
        
        return '<a ' . 'title="' . esc_attr($note) . '" class="' . esc_attr($class) . '" href="' . $url . '">' . $link_text . '</a>';        
    }
    
    /**
     * Restore free backup parameters
     * @param number $blog_id
     * @param string $backup_filepath
     * @param string $encryption_status
     * @return string[]
     */
    private function restoreFreeBackupParameters($blog_id = 0, $backup_filepath = '', $encryption_status = 'false')
    {
        $url = $this->getPrimeMover()->getSystemFunctions()->getCreateExportUrl($blog_id, true, $backup_filepath);
        $class = "button prime-mover-menu-button js-prime-mover-restore-icon js-prime-mover-restore-free-backups";
        $note = esc_html__('Use migration tools to upload package from Desktop. Use this button to restore backup within this site.', 'prime-mover');
        $link_text = esc_html__('Restore package', 'prime-mover'); 
        $link_active = true;
        
        if ('true' === $encryption_status) { 
            $note = esc_html__('Restoring encrypted package is a PRO feature. Please upgrade or activate license to restore this package.', 'prime-mover');
            $class = "js-prime-mover-upgrade-button-simple prime-mover-upgrade-button-simple button";
            $url = $this->getSystemInitialization()->getUpgradeUrl();
            $link_text = apply_filters('prime_mover_filter_upgrade_pro_text', esc_html__( 'Upgrade to PRO', 'prime-mover' ), $blog_id);
            $link_active = false;
        }
        
        return [$url, $class, $note, $link_text, $link_active];        
    }
    
    /**
     * Get restore URL
     * @param number $blog_id
     * @param string $sanitized_name
     * @return string
     */
    protected function getRestoreUrl($blog_id = 0, $sanitized_name = '')
    {
        $migration_tools = $this->getSystemInitialization()->getMigrationToolsUrl();
        $params = [
            'blog_id' => $blog_id,
            'action' => 'prime_mover_restore_backup_action',
            'filename' => $sanitized_name
        ];
        
        if (is_multisite()) {
            $params['s'] = $blog_id;
        }
        
        return esc_url(add_query_arg($params, $migration_tools));
    }
    
    /**
     * @mainsitesupport_affected
     * @param number $blog_id
     * @param array $tar_config
     * @return string
     * 
     * This needs to be translatable so it will be translated on the table list table for backups.
     */    
    protected function getPackageType($blog_id = 0, $tar_config = [])
    {
        if (!empty($tar_config['prime_mover_export_type'])) {
            return $tar_config['prime_mover_export_type'];
        }
        
        if (1 === $blog_id ) {
            return 'single-site';   
        } 
        
        if ($blog_id > 1) {
            return 'multisite'; 
        }
        
        return "N/A";
    }
  
    /**
     * Reorder data
     * @return number
     */
    protected function usortReorder($a,$b)
    {
        $sort_params = $this->getSortRequestParameters();
        
        $orderby = (!empty($sort_params['orderby'])) ? $sort_params['orderby'] : 'date_created'; 
        $order = (!empty($sort_params['order'])) ? $sort_params['order'] : 'desc'; 
        if ('package_size' === $sort_params['orderby']) {
            $orderby = 'package_size_raw';
            $result = ($a[$orderby] < $b[$orderby]) ? -1 : (($a[$orderby] > $b[$orderby]) ? 1 : 0);
        } else {
            $result = strcmp($a[$orderby], $b[$orderby]);            
        }  
        
        return ($order==='asc') ? $result : -$result; 
    }
    
    /**
     * Sort request parameters
     * @return mixed|NULL|array
     */
    private function getSortRequestParameters()
    {        
        return $this->getSystemInitialization()->getUserInput('get',
            [
                'order' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
                'orderby' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
                'page' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
            ], 'prime_mover_backup_menu_sort');       
    }
 
    /**
     * 
     * {@inheritDoc}
     * @see WP_List_Table::print_column_headers()
     */
    public function print_column_headers( $with_id = true ) {
        list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
        
        $current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
        $current_url = remove_query_arg( 'paged', $current_url );
        
        if ( isset( $_GET['orderby'] ) ) {
            $current_orderby = $_GET['orderby'];
        } else {
            $current_orderby = '';
        }
        
        if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
            $current_order = 'desc';
        } else {
            $current_order = 'asc';
        }
        
        if ( ! empty( $columns['cb'] ) ) {
            static $cb_counter = 1;
            $columns['cb']     = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label>'
                . '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
                $cb_counter++;
        }
        
        foreach ( $columns as $column_key => $column_display_name ) {
            
            if ('migrate' === $column_key) {
                $column_display_name = '<a class="prime-mover-external-link" href="' . esc_url(PRIME_MOVER_RESTORE_URL_DOC . "#remote-url-restore-dialog"). '">' . $column_display_name . '</a>';     
            }
            
            $class = array( 'manage-column', "column-$column_key" );
            
            if ( in_array( $column_key, $hidden, true ) ) {
                $class[] = 'hidden';
            }
            
            if ( 'cb' === $column_key ) {
                $class[] = 'check-column';
            } elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ), true ) ) {
                $class[] = 'num';
            }
            
            if ( $column_key === $primary ) {
                $class[] = 'column-primary';
            }
            
            if ( isset( $sortable[ $column_key ] ) ) {
                list( $orderby, $desc_first ) = $sortable[ $column_key ];
                
                if ( $current_orderby === $orderby ) {
                    $order = 'asc' === $current_order ? 'desc' : 'asc';
                    
                    $class[] = 'sorted';
                    $class[] = $current_order;
                } else {
                    $order = strtolower( $desc_first );
                    
                    if ( ! in_array( $order, array( 'desc', 'asc' ), true ) ) {
                        $order = $desc_first ? 'desc' : 'asc';
                    }
                    
                    $class[] = 'sortable';
                    $class[] = 'desc' === $order ? 'asc' : 'desc';
                }
                
                $column_display_name = sprintf(
                    '<a href="%s"><span>%s</span><span class="sorting-indicator"></span></a>',
                    esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ),
                    $column_display_name
                    );
            }
            
            $tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
            $scope = ( 'th' === $tag ) ? 'scope="col"' : '';
            $id    = $with_id ? "id='$column_key'" : '';
            
            if ( ! empty( $class ) ) {
                $class = "class='" . implode( ' ', $class ) . "'";
            }
            
            $title = '';
            $attributes = $this->getColumnsDescription();
            $attribute = '';
            if (isset($attributes[$column_key])) {
                $attribute = $attributes[$column_key];
            }
            if ('th' === $tag && $attribute) {                
                $title = "title='" . esc_attr($attribute) . "'";    
            }
            echo "<$tag $title $scope $id $class>$column_display_name</$tag>";
        }
    }
    
    /**
     * Prepare items
     * {@inheritDoc}
     * @see WP_List_Table::prepare_items()
     */
    public function prepare_items() 
    {        
        $per_page = 5; 
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
      
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->processBulkAction();
        $data = $this->getBackupsData();  
        
        $sort_params = $this->getSortRequestParameters();
        if (!empty($sort_params['orderby']) && !empty($sort_params['order'])) {
            usort($data, [$this, 'usortReorder']);
        }
        
        $current_page = $this->get_pagenum();
        $total_items = count($data);        
        $data = array_slice($data,(($current_page-1)*$per_page),$per_page);        
        
        $this->items = $data;        
        $this->set_pagination_args([
            'total_items' => $total_items,                  
            'per_page'    => $per_page,                     
            'total_pages' => ceil($total_items/$per_page)   
        ]);
    }
}