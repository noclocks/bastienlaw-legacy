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
use WP_Site;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Cleanup Class
 * Helper class for cleaning up and doing some maintenance related tasks.
 *
 */
class PrimeMoverCleanUp
{     
    private $prime_mover;
    private $alert_network_admin;
    private $lock_files;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->alert_network_admin = false;
        $this->lock_files = [
            '.prime_mover_processing_plugin',
            '.prime_mover_uploading_dropbox',
            '.prime_mover_uploading_gdrive'
            ];
    }
    
    /**
     * Get lock files
     * @return string[]
     */
    public function getLockFiles()
    {
        return $this->lock_files;
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
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getPrimeMover()->getSystemInitialization();
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
     * Initialize hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCleanUp::itChecksIfHooksAreOutdated()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverCleanUp::itAddsInitHooks()
     */
    public function initHooks()
    {
        add_action('admin_init', [$this, 'maybeCleanUpRedundantExportDirs'], 500);
        add_action('admin_init', [$this, 'maybeCleanUpRedundantTmpDownload'], 500);        
        add_action('admin_notices', [$this, 'maybeAlertNetworkAdminOfWrongBackups']);
        
        add_action('prime_mover_load_module_apps', [$this, 'maybeSetIfSubsite']);
        add_action('prime_mover_load_module_apps', [$this, 'maybeCleanUpRedundantExportDirs'], 500);
        add_action('prime_mover_load_module_apps', [$this, 'maybeCleanUpRedundantTmpDownload'], 500);
        
        add_action('wp_insert_site', [$this, 'autoCreateExportFolderOnBlogCreation'], 10, 1);
        add_filter('prime_mover_bailout_shutdown_procedure', [$this, 'cleanUpIfDiskIsFullToLogErrors'], 10, 2);
        add_filter('prime_mover_filter_runtime_error', [$this, 'userFriendlyNoDiskSpaceError'], 10, 1);
        
        add_action('prime_mover_before_streaming_errorlog', [$this, 'maybeCleanUpCorruptedPackage'], 10, 1);
        add_action('prime_mover_deactivated', [$this, 'maybeCleanupMuScript']);
        add_action('admin_init', [$this, 'maybeCleanUpOutdatedLockFiles'], 0);
    }
    
    /**
     * Maybe clean outdated lock files
     */
    public function maybeCleanUpOutdatedLockFiles()
    {
        if (wp_doing_ajax()) {
            return;
        }
        
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }       
        
        if (!$this->getSystemInitialization()->getLockFolderCreated()) {
            return;     
        }
        $this->getSystemFunctions()->initializeFs(false);
        global $wp_filesystem;  
        if (!$this->getSystemFunctions()->isWpFileSystemUsable($wp_filesystem)) {
            return;
        }
 
        $abspath = $wp_filesystem->abspath();
        if (!wp_is_writable($abspath)) {
            return;
        }
        
        foreach ($this->getLockFiles() as $lock_file) {
            $path = $abspath . $lock_file;
            if ($this->getSystemFunctions()->nonCachedFileExists($path)) {
                $this->getSystemFunctions()->primeMoverDoDelete($path);
            }           
        }       
    }
    
    /**
     * Cleanup MU script on deactivation
     */
    public function maybeCleanupMuScript()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $must_use_cli_script = $this->getSystemInitialization()->getCliMustUsePluginPath();
        if (!$this->getSystemFunctions()->nonCachedFileExists($must_use_cli_script)) {
            return;
        }
        $this->getSystemFunctions()->primeMoverDoDelete($must_use_cli_script, true);
    }
    
    /**
     * Clean corrupted package on export
     * @param number $blog_id
     */
    public function maybeCleanUpCorruptedPackage($blog_id = 0)
    {
        if (!$blog_id) {
            return;
        }
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }  
        $ret = apply_filters('prime_mover_get_export_progress', [], $blog_id);
        $tmp_exist = false;
        if (!empty($ret['target_zip_path']) && $this->getSystemFunctions()->nonCachedFileExists($ret['target_zip_path'])) {
            $tmp_exist = true;
        }
        $path = '';
        if ($tmp_exist) {
            $path = $ret['target_zip_path'];
        }
        if (!$this->getSystemFunctions()->hasTarExtension($path)) {
            return;
        }
        if ($path && !$this->getSystemFunctions()->isReallyTar($path)) {
            $this->getSystemFunctions()->unLink($path);
        }
    }
    
    /**
     * Make no disk space errors very clear to user.
     * @param array $error
     * @return string
     */
    public function userFriendlyNoDiskSpaceError($error = [])
    {
        if ($this->isDiskSpaceFull($error) && isset($error['message'])) {
            $error['message'] = esc_html__('ERROR: Insufficient Disk Space. Please contact your host to increase disk space and try again.', 'prime-mover');
            $error['diskfull'] = true;
            $error['type'] = 1;
        }
        
        return $error;
    }
    
    /**
     * Clean up if disk is full to temporarily allow logging of errors for user
     * @param boolean $bailout
     * @param array $error
     * @return string
     */
    public function cleanUpIfDiskIsFullToLogErrors($bailout = true, $error = [])
    {
        if (false === $bailout) {
            return $bailout;
        }       
        
        if ($this->isDiskSpaceFull($error)) {
            $bailout = false;
        }       
        
        return $bailout;
    }
    
    /**
     * Check if disk is full by analyzing file_put_contents error
     * @param string $msg
     * @return boolean
     */
    protected function isDiskSpaceFull($error = [])
    {
        if (empty($error['message'])) {
            return false;
        }
        
        $msg = $error['message'];
        return (false !== strpos($msg, 'possibly out of free disk space') && false !== strpos($msg, 'file_put_contents'));
    }
    
    /**
     * Auto create export folder on blog creation
     * @param WP_Site $site_object
     */
    public function autoCreateExportFolderOnBlogCreation(WP_Site $site_object)
    {
        if (!is_multisite()) {
            return;
        }
        if (!isset($site_object->blog_id)) {
            return;
        }
        $blog_id = (int)$site_object->blog_id;
        if (!$blog_id) {
            return;
        }
        
        $blogexport_path = $this->getSystemFunctions()->getExportPathOfThisSubsite($blog_id);     
        $created = false;
        if ($blogexport_path) {
            $created = wp_mkdir_p($blogexport_path);
        }
        if ($created) {
            $this->getSystemInitialization()->camouflageFolders($blogexport_path);
        }
    }
    
    /**
     * Set if subsite
     */
    public function maybeSetIfSubsite()
    {
        if (is_multisite() && false === $this->getSystemFunctions()->maybeCreateFoldersInMu()) {
            $this->getSystemInitialization()->setIsSubsite(true);
        }
    }
    
    /**
     * Show admin notice to admins to alert of wrong backups placement
     */
    public function maybeAlertNetworkAdminOfWrongBackups()
    {        
        if (!$this->getAlertAdmin()) {
            return;
        }
        
        $blog_id = get_current_blog_id();
        $incorrect = $this->getExportPathForCleanup($blog_id);
        $main_site_id = get_main_site_id();         
        
        $this->getSystemFunctions()->switchToBlog($main_site_id);  
        $correct = $this->getExportPathForCleanup($blog_id);
        $this->getSystemFunctions()->restoreCurrentBlog();
    ?>
        <div class="notice notice-warning is-dismissible">
           <p><?php echo esc_html__('Prime Mover plugin detected incorrectly placed backup packages for this subsite. 
Please move WPRIME packages FROM: ', 'prime-mover');?></p>

           <p><strong><?php echo $incorrect; ?></strong></p>           
           <p><?php echo esc_html__('TO :', 'prime-mover');?></p>           
           <p><strong><?php echo $correct; ?></strong></p>
           <p><?php echo esc_html__('If you do not need these packages, please delete them. 
Prime Mover plugin will auto-delete this wrong export directory. Thank you!', 'prime-mover'); ?></p> 
        </div>    
    <?php     
    }
    
    /**
     * Get export path
     * @param number $blog_id
     * @return string
     */
    protected function getExportPathForCleanup($blog_id = 0)
    {
        return trailingslashit(wp_normalize_path($this->getSystemInitialization()->getMultisiteExportFolder('', false, true))) . trailingslashit($blog_id); 
    }
    
    /**
     * Set alert admin
     * @param boolean $alert
     */
    public function setAlertAdmin($alert = false)
    {
        $this->alert_network_admin = $alert;
    }
    
    /**
     * Get alert admin
     * @return boolean
     */
    public function getAlertAdmin()
    {
        return $this->alert_network_admin;
    }
    
    /**
     * Validate redundant dirs processing
     * @return boolean
     */
    protected function validateRedundantDirsProcessing()
    {
        if (!is_multisite()) {
            return false;
        }
        if (wp_doing_ajax()) {
            return false;            
        }
        
        if (false === $this->getSystemInitialization()->getIsSubsite()) {
            return false;
        }
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }  
        
        return true;
    }

    /**
     * Maybe cleanup redundant tmp download dir
     * hooked to `admin_init`
     */
    public function maybeCleanUpRedundantTmpDownload()
    {
        if (false === $this->validateRedundantDirsProcessing()) {
            return;
        }
        $this->getSystemFunctions()->initializeFs(); 
        $downloads_folder = $this->getSystemInitialization()->getTmpDownloadsFolder();
        if (!$this->getSystemFunctions()->nonCachedFileExists($downloads_folder)) {
            return;
        }        
        $this->deleteRedundantDirs($downloads_folder, $this->getSystemInitialization()->getTmpDownloadsFolderSlug());
    }
    
    /**
     * Maybe cleanup redundant export dirs
     * Hooked to `admin_init`
     */
    public function maybeCleanUpRedundantExportDirs()
    {
        if (false === $this->validateRedundantDirsProcessing()) {
            return;    
        }
        
        $this->getSystemFunctions()->initializeFs();   
        $blog_id = get_current_blog_id();
        
        $export_folder = $this->getSystemInitialization()->getMultisiteExportFolder('', false, true);
        $package_folder = $this->getExportPathForCleanup($blog_id);
        
        if (!$this->getSystemFunctions()->nonCachedFileExists($export_folder)) {
            return;
        }
        
        if (!$this->getSystemFunctions()->nonCachedFileExists($package_folder)) {
            return;
        }
       
        $files = $this->getSystemFunctions()->readPrimeMoverDirectory($blog_id, $package_folder , []);
        if (empty($files)) {
            $this->deleteRedundantDirs($export_folder, $this->getSystemInitialization()->getMultisiteExportFolderSlug());
        } else {          
            $this->setAlertAdmin(true);            
        }        
    }
    
    /**
     * Delete redundant dirs
     * Used by `maybeCleanUpRedundantExportDirs` and 'maybeCleanUpRedundantTmpDownload` method
     * @param string $path
     * @param string $validate_string
     */
    protected function deleteRedundantDirs($path = '', $validate_string = '')
    {        
        if (!$path || !$validate_string) {
            return;
        }
        
        if (!$this->getSystemFunctions()->isDir($path)) {
            return;            
        }
        
        $basename = basename($path);
        if ($basename !== $validate_string) {
            return;    
        }
        
        $this->getSystemFunctions()->primeMoverDoDelete($path, true);        
    }    
}