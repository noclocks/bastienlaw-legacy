<?php
namespace Codexonics\PrimeMoverFramework\utilities;

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
 * Prime Mover Backups Utilities
 * Helper functionality for backup related tasks
 *
 */
class PrimeMoverBackupUtilities
{       
    private $prime_mover;
    private $is_refreshing_backup;
    private $prime_mover_in_progress_package_option;
    
    /**
     * Constructor
     * @param PrimeMover $prime_mover
     */
    public function __construct(PrimeMover $prime_mover)
    {
        $this->prime_mover = $prime_mover;
        $this->is_refreshing_backup = false;
        $this->prime_mover_in_progress_package_option = 'prime_mover_in_progress_packages';
    }
 
    /**
     * Maybe remove WIP status too on error when package is deleted automatically
     * @param string $path_to_delete
     * @param number $blog_id
     */
    public function maybeRemoveWipStatusOnError($path_to_delete = '', $blog_id = 0)
    {
        if (!$path_to_delete || !$blog_id) {
            return;
        }
        $this->updateInProgressPackagesDb($path_to_delete, $blog_id); 
    }
    
    /**
     * Maybe mark package completed - remove WIP status
     * @param string $results
     * @param string $hash
     * @param number $blogid_to_export
     */
    public function maybeMarkPackageCompleted($results = '', $hash = '', $blogid_to_export = 0)
    {        
        if (!$results || !$blogid_to_export) {
            return;
        }
        
        $this->updateInProgressPackagesDb($results, $blogid_to_export);        
    }
    
    /**
     * Update in-progress packages in dB
     * @param string $results
     * @param number $blogid_to_export
     */
    protected function updateInProgressPackagesDb($results = '', $blogid_to_export = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        if (!$results || !$blogid_to_export) {
            return;
        }

        $package = basename($results);
        $in_progress = $this->getInProgressPackages();
        $option_name = $this->getInProgressPackageOption();
        $hash = sha1($package);

        if ($this->packageIsInProgress($in_progress, $package, $blogid_to_export)) {
            unset($in_progress[$blogid_to_export][$hash]);
            $this->getSystemFunctions()->updateSiteOption($option_name, $in_progress);
        }
    }
    
    /**
     * Get in-progress packages
     * @return mixed|boolean|NULL|array
     */
    public function getInProgressPackages()
    {
        return $this->getSystemFunctions()->getSiteOption($this->getInProgressPackageOption());
    }
    
    /**
     * Get package in-progress
     * @param array $in_progress_packages
     * @param string $package_filename
     * @param number $blog_id
     * @return boolean
     */
    public function packageIsInProgress($in_progress_packages = [], $package_filename = '', $blog_id = 0)
    {
        if (!is_array($in_progress_packages) || !$package_filename || !$blog_id) {
            return false;
        }
      
        $package_filename = basename($package_filename);
        $hash = sha1($package_filename);        
        if (isset($in_progress_packages[$blog_id][$hash])) {
            return true;
        }
      
        return false;        
    }
    
    /**
     * Logged in-progress packages
     * @param array $ret
     * @param number $blogid_to_export
     * @return array
     */
    public function logInProgressPackage($ret = [], $blogid_to_export = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !$blogid_to_export) {
            return $ret;
        }
       
        if (!isset($ret['target_zip_path'])) {
            return $ret;
        }
       
        $wprime = basename($ret['target_zip_path']);
        if (!$this->getSystemFunctions()->hasTarExtension($wprime)) {
            return $ret;
        }
       
        $option_name = $this->getInProgressPackageOption();
        $existing_packages = $this->getSystemFunctions()->getSiteOption($option_name);
        if (!is_array($existing_packages)) {
            $existing_packages = [];
        }       
        
        $hash = sha1($wprime);
        if (!isset($existing_packages[$blogid_to_export][$hash])) {
            $existing_packages[$blogid_to_export][$hash] = $wprime;
        }
       
        $this->getSystemFunctions()->updateSiteOption($option_name, $existing_packages);         
        return $ret;
    }
    
    /**
     * Get in-progress package option
     * @return string
     */
    public function getInProgressPackageOption()
    {
        return $this->prime_mover_in_progress_package_option;
    }    
    
    /**
     * Is refreshing backup
     * @param boolean $is_refreshing_backup
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupUtilities::itChecksIfRefreshingbackup() 
     */
    public function setIsRefreshingBackup($is_refreshing_backup = false)
    {
        $this->is_refreshing_backup = $is_refreshing_backup;
    }
    
    /**
     * Checks refresh backup status
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupUtilities::itChecksIfRefreshingbackup() 
     */
    public function isRefreshingBackup()
    {
        return $this->is_refreshing_backup;
    }
    
    /**
     * Maybe Refresh backup data
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupUtilities::itMaybeRefreshBackupsData() 
     * @param string $backups_hash_db
     * @param string $current_backup_hash
     * @param boolean $markup
     * @param number $blog_id
     * @param boolean $update
     * @return boolean
     */
    public function maybeRefreshBackupData($backups_hash_db = '', $current_backup_hash = '', $markup = false, $blog_id = 0, $update = false)
    {                
        if ($this->isUserRequestingBackupRefresh($markup, $blog_id, $update)) {
            return true;
        }
        return ( ! $backups_hash_db || $current_backup_hash !== $backups_hash_db );      
    }
    
    /**
     * Is user requesting backup refresh
     * @param boolean $markup
     * @param number $blog_id
     * @param boolean $update
     * @return boolean|mixed|NULL|array
     */
    protected function isUserRequestingBackupRefresh($markup = false, $blog_id = 0, $update = false)
    {
        $get = $this->getSystemInitialization()->getUserInput('get', ['prime_mover_refresh_backups' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()], 'prime_mover_refresh_backups',
            '', 0, true, true);
        $current_user_id = get_current_user_id();
        if ( $this->getSystemAuthorization()->isUserAuthorized() && isset($get['prime_mover_refresh_backups']) && 
            $this->getSystemFunctions()->primeMoverVerifyNonce($get['prime_mover_refresh_backups'], 'refresh_backups_' . $current_user_id)
            ) {
                $this->setIsRefreshingBackup(true);
                return true;
        }
        
        return apply_filters('prime_mover_force_backup_refresh', false, $markup, $blog_id, $update);
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
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getPrimeMover()->getSystemFunctions();
    }
    
    /**
     * Get backups hash in dB
     * @param array $backups
     * @param number $blog_id
     * @return boolean|mixed
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupUtilities::itGetsBackupHashInDb()
     */
    public function getBackupsHashInDb($backups = [], $blog_id = 0)
    {
        if ( ! $backups || ! is_array($backups)) {
            return false;
        }
        if ( empty($backups[$blog_id]) ) {
            return false;
        }
        
        return key($backups[$blog_id]);
    }
    
    /**
     * Get validated backups saved in dB
     * @param string $option_name
     * @return array|mixed|boolean|NULL|array
     */
    public function getValidatedBackupsArrayInDb($option_name = '', $legacy = false)
    {
        if ( ! $option_name ) {
            return [];
        }

        if ($legacy) {
            $main_site = $this->getSystemInitialization()->getMainSiteBlogId();
            $this->getSystemFunctions()->switchToBlog($main_site);
            
            wp_cache_delete('alloptions', 'options');
            $backups = get_option($option_name);
            $this->getSystemFunctions()->restoreCurrentBlog();
        } else {
            $backups = $this->getSystemFunctions()->getSiteOption($option_name, false, true, true);
        }
        
        return $backups;
    }
    
    /**
     * Updated validated backups array
     * @param array $backups_array
     * @param string $backups_hash
     * @param string $option_name
     * @param number $blog_id
     * @param string $previous_hash
     * @return void|boolean
     */
    public function updateValidatedBackupsArrayInDb($backups_array = [], $backups_hash = '', $option_name = '', $blog_id = 0, $previous_hash = '')
    {
        if ( ! $backups_hash || ! $option_name || ! $blog_id ) {
            return;
        }
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $value = [];
        if (is_multisite()) {
            $value = $this->getValidatedBackupsArrayInDb($option_name);
        }        
        if ( false === $value ) {
            $value = [];
        }
        
        if (is_string($previous_hash) && isset($value[$blog_id][$previous_hash])) {
            unset($value[$blog_id][$previous_hash]);
        }
        
        $value[$blog_id][$backups_hash] = $backups_array;
        return $this->getSystemFunctions()->updateSiteOption($option_name, $value, true);
    } 
    
    /**
     * Compute backup hash based on latest backup files scenario and customer licensing
     * @param array $backups
     * @param number $blog_id
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupUtilities::itComputesBackupHash() 
     */
    public function computeBackupHash($backups = [], $blog_id = 0)
    {
        $mode = 'free';
        if ( true === apply_filters('prime_mover_is_loggedin_customer', false)) {
            $mode = 'pro';
        }
        $subsite_licensed = '';
        if (is_multisite() && $blog_id) {
            $subsite_licensed = 'free';
            if (true === apply_filters('prime_mover_multisite_blog_is_licensed', false, $blog_id)) {
                $subsite_licensed = 'pro';
            }
        }
        $string_to_hash = json_encode($backups) . $mode . $subsite_licensed;
        return sha1($string_to_hash);
    }
    
    /**
     * Checks if blog is usable provided by blog ID
     * @param number $blog_id
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupUtilities::itChecksIfBlogIdIsUsable()
     * @mainsitesupport_affected
     * 
     * Since 1.2.0, its possible to have blog ID of 1 and that is on a multisite main site.
     * Remove the > 1 check and simply just check if $blog_id is truth value.
     */
    public function blogIsUsable($blog_id = 0)
    {
        return $this->getSystemFunctions()->blogIsUsable($blog_id);
    }
    
    /**
     * Show delete section
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupUtilities::itAppendsBlogIdOnBackupMenuUrlOnMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupUtilities::itRendersBackupSectionWhenItsEmpty()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupUtilities::itRendersBackupSection()
     */
    public function primeMoverManageBackupsSection($blog_id = 0)
    {        
        $note = '';
        $backups_menu_url = $this->getSystemFunctions()->getBackupMenuUrl($blog_id);
        
        if (is_multisite()) {
            $note = '(' . sprintf(esc_html__('blog ID : %d', 'prime-mover'), $blog_id) . ')';
        }
        ?>
        <h3><?php echo sprintf( esc_html__('Manage packages %s', 'prime-mover'), $note );?></h3>	    
	    <p class="prime-mover-managebackups-<?php echo esc_attr($blog_id); ?>"><a href="<?php echo esc_url($backups_menu_url);?>" class="button button-secondary"><?php esc_html_e('Go to Package Manager', 'prime-mover'); ?></a></p> 	
    <?php    
    }
}