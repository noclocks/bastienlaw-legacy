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

use Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions;
use Codexonics\PrimeMoverFramework\general\PrimeMoverMustUsePluginManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Lock Utilities
 * Helper functionality for locking resources during export/import processes
 *
 */
class PrimeMoverLockUtilities
{        
    private $system_functions;
    
    /**
     * Constructor
     * @param PrimeMoverSystemFunctions $system_functions
     */
    public function __construct(PrimeMoverSystemFunctions $system_functions)
    {
        $this->system_functions = $system_functions;
    }
    
    /**
     * Init hooks
     */
    public function initHooks()
    {
        add_filter('pre_get_ready_cron_jobs', [$this, 'maybeDisableCronSystemSchedulerOnMigration'], 10, 1);    
        add_action('prime_mover_load_module_apps', [$this, 'lockCanonicalUploads'], 0);
        
        add_filter('prime_mover_filter_other_information', [$this, 'lockRootUploads'], 10, 1);
        
        add_action( 'admin_enqueue_scripts', [$this, 'maybeDisableHeartBeat'], 99 );
        add_action( 'wp_enqueue_scripts', [$this, 'maybeDisableHeartBeat'], 99 );
    }

    /**
     * Disable heartbeat on any Prime Mover page
     * This is to prevent it from interring any locked processes.
     */
    public function maybeDisableHeartBeat()
    {
        if ($this->getSystemFunctions()->isPrimeMoverPage()) {
            wp_deregister_script( 'heartbeat' );
        }        
    }
    
    /**
     * Lock root uploads
     * @param array $ret
     * @return array
     */
    public function lockRootUploads($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        $ret['root_uploads_information'] = $this->getSystemInitialization()->getInitializedWpRootUploads(true);
        return $ret;
    }
    
    /**
     * Lock canonical uploads
     */
    public function lockCanonicalUploads()
    {
        $this->maybeLockToCanonicalUploadsDir();
        $this->hasCronAction();
    }
    
    /**
     * Maybe lock to canonical uploads directory during restore
     * @param array $ret
     */
    protected function maybeLockToCanonicalUploadsDir()
    {
        $prime_mover_plugin_manager = $this->getSystemInitialization()->getPrimeMoverPluginManagerInstance();
        if (!is_object($prime_mover_plugin_manager)) {
            return;
        }
        
        if (!method_exists($prime_mover_plugin_manager, 'getBlogId')) {
            return;
        }
        
        if (!$this->isLockingPrimeMoverProcesses($prime_mover_plugin_manager, false)) {
            return;
        }
        
        $blog_id = $prime_mover_plugin_manager->getBlogId();
        if (!is_multisite()) {
            $blog_id = 1;
        }
        if (!$blog_id) {
            return;
        }
        
        $ret = apply_filters('prime_mover_get_import_progress', [], $blog_id);
        if (!empty($ret['canonical_uploads_information'])) {
            $this->getSystemInitialization()->setCanonicalUploadsInfo($ret['canonical_uploads_information']);
        }
        
        if (!empty($ret['root_uploads_information'])) {
            $this->getSystemInitialization()->setRootUploadsInfo($ret['root_uploads_information']);
        }        
    }
    
    /**
     * Checks if we are locking any Prime Mover processses
     * @param PrimeMoverMustUsePluginManager $prime_mover_plugin_manager
     * @param boolean $check_lock_file
     * @return boolean
     */
    public function isLockingPrimeMoverProcesses(PrimeMoverMustUsePluginManager $prime_mover_plugin_manager, $check_lock_file = false)
    {        
        $is_locking_prime_mover_process = false;
        if ($prime_mover_plugin_manager->primeMoverMaybeLoadPluginManager()) {
            $is_locking_prime_mover_process = true;
        }
        
        if (!$check_lock_file) {
            return $is_locking_prime_mover_process;
        }
        
        $doing_migration_lock = $this->getDoingMigrationLockFile();        
        if (!$is_locking_prime_mover_process && $this->getSystemFunctions()->nonCachedFileExists($doing_migration_lock)) {
            $is_locking_prime_mover_process = true;
        }
        
        return $is_locking_prime_mover_process;
    }
    
    /**
     * Check if WP cron action is enabled so we can use this to determine if we running a Prime Mover process
     */
    protected function hasCronAction()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $doing_migration_lock = $this->getDoingMigrationLockFile();
        $has_cron_action = false;
        if (has_action('init', 'wp_cron')) {
            $has_cron_action = true;
        }
        
        if ($has_cron_action && $this->getSystemFunctions()->nonCachedFileExists($doing_migration_lock)) {
            $this->getSystemFunctions()->unLink($doing_migration_lock);
        }
        
        if (!$has_cron_action && !$this->getSystemFunctions()->nonCachedFileExists($doing_migration_lock)) {
            $this->getSystemFunctions()->filePutContentsAppend($doing_migration_lock, 'ongoing migration..');
        }
    }
    
    /**
     * Disable cron jobs via direct system scheduler call if we are running migrations
     * @param mixed $cronjobs
     * @return array|NULL
     */
    public function maybeDisableCronSystemSchedulerOnMigration($cronjobs = null)
    {
        if ($this->getSystemFunctions()->nonCachedFileExists($this->getDoingMigrationLockFile())) {
            return [];
        } 
        return null;
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->system_functions;
    }
    
    /**
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getSystemFunctions()->getSystemInitialization();
    }
    
    /**
     * Get system authorizations
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getSystemFunctions()->getSystemAuthorization();
    }

    /**
     * Get doing migration lock file
     * @return string
     */
    public function getDoingMigrationLockFile()
    {
        $lock_files_directory = trailingslashit(wp_normalize_path($this->getSystemInitialization()->getLockFilesFolder()));
        return $lock_files_directory . '.prime_mover_doing_migration';        
    }
    
    /**
     * Open lock file
     * @param string $lock_file
     * @return boolean|resource handle
     * @codeCoverageIgnore
     */
    public function openLockFile($lock_file = '', $render_absolute = true)
    {
        if ( ! $lock_file ) {
            return false;
        }
        global $wp_filesystem;
        if ($render_absolute) {
            $lock_file_path = $wp_filesystem->abspath() . $lock_file;
        } else {
            $lock_file_path = $lock_file;
        }
        
        return @fopen($lock_file_path, "wb");
    }
    
    /**
     * Create lock file using native PHP flock
     * @param $fp
     * @return boolean
     * @codeCoverageIgnore
     */
    public function createProcessLockFile($fp)
    {
        return flock($fp, LOCK_EX);
    }
    
    /**
     * Unlock file after processing
     * @codeCoverageIgnore
     */
    public function unLockFile($fp)
    {
        return flock($fp, LOCK_UN);
    }
    
    /**
     * Close dropbox lock
     * @param $fp
     * @codeCoverageIgnore
     */
    public function closeLock($fp)
    {
        @fclose($fp);
    }    
}