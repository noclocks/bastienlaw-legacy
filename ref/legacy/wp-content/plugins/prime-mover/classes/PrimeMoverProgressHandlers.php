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

use Codexonics\PrimeMoverFramework\utilities\PrimeMoverShutdownUtilities;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The Prime Mover Progress handling class
 *
 * The Prime Mover Progress Handler Class handles tracking the import and export progress for user tracking migration progress.
 *
 */
class PrimeMoverProgressHandlers
{       
    private $shutdown_utilities;
    private $active_processes;
    
    const SINGLESITE_PLUGINS = 'prime_mover_standalone_plugins';
    const PRIMEMOVER_MAINTENANCE = 'prime_mover_maintenance';
    
    /**
     *
     * Constructor
     */
    public function __construct(
        PrimeMoverShutdownUtilities $shutdown_utilities
        ) 
    {
            $this->shutdown_utilities = $shutdown_utilities;
            $this->active_processes = [];
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itGetsSystemfunctions() 
     */
    public function getSystemFunctions()
    {
        return $this->getShutDownUtilities()->getSystemFunctions();
    }
    
    /**
     * Get active processess
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDoesNotDuplicateActiveProcessesIfAlreadySet() 
     */
    public function getActiveProcesses()
    {
        return $this->active_processes;
    }
    
    /**
     * Get active process ID
     * @param string $process_id
     * @return number|mixed
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itReturnsBlogIdOfActiveProcessIfSet() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDoesNotDuplicateActiveProcessesIfAlreadySet() 
     */
    public function getActiveProcessBlogId($process_id = '')
    {
        $active_processes = $this->active_processes;
        $blog_id = 0;
        if ( ! empty($active_processes[$process_id] ) ) {
            $blog_id = $active_processes[$process_id];
        }
        return $blog_id;
    }
    
    /**
     * Set active processes
     * @param string $process_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itReturnsBlogIdOfActiveProcessIfSet() 
     */
    public function setActiveProcesses($process_id = '', $blog_id = 0)
    {
        if (array_key_exists($process_id, $this->active_processes)) {
            return;
        }
        $this->active_processes[$process_id] = $blog_id;
    }
    
    /**
     * Initialize hooks
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itAddsShutdownProgressInitHooks() (1.0.4 updated)
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itAddsBootUpProgressHooks() (1.0.4 updated)
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itAddsOtherProgressHooks() (1.0.4 updated)
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itAddsMaintenanceHooksWhenEnabled() (1.0.4 updated)
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDoesNotAddMaintenanceHooksWhenDisabled() (1.0.4 updated)
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itChecksIfHooksAreOutdated()
     */
    public function initHooks() 
    {
        /**
         * On shutdown progress hooks
         */
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteImportId'], 10, 2);
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteExportId'], 10, 2);        
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteExportResultId'], 10, 2);
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteImportResultId'], 10, 2);        
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteDownloadResultId'], 10, 2);        
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteDownloadSizeId'], 10, 2);
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteDownloadTmpFileId'], 10, 2);
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteUploadDropBoxSize'], 10, 2);
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteUploadGdriveSize'], 10, 2);        
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteMaintenanceOption'], 10, 2);
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteAssemblyOption'], 10, 2);
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteAssembledOption'], 10, 2);
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteUploadProgressOption'], 10, 2);
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteUserProgressMeta'], 10, 2);
        add_action('prime_mover_shutdown', [$this,'deleteCliTmpFiles'], 10, 1);
        add_action('prime_mover_shutdown', [$this,'primeMoverDeleteThirdPartyPluginsOption'], 10, 2);
        
        /**
         * On boot-up progress hooks
         */
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteRunTimeErrorLog'], 0, 2);        
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteImportId'], 10, 2);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteExportId'], 10, 2);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteExportResultId'], 10, 2);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteImportResultId'], 10, 2);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteDownloadResultId'], 10, 2);        
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteDownloadSizeId'], 10, 2);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteDownloadTmpFileId'], 10, 2);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteUploadDropBoxSize'], 10, 2);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteUploadGdriveSize'], 10, 2);        
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteMaintenanceOption'], 10, 2);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteAssemblyOption'], 10, 2);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteAssembledOption'], 10, 2);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteUploadProgressOption'], 10, 2);        
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteUserProgressMeta'], 10, 2);
        add_action('prime_mover_bootup', [$this,'deleteCliTmpFiles'], 10, 1);
        add_action('prime_mover_bootup', [$this,'primeMoverDeleteThirdPartyPluginsOption'], 10, 2);
        
        /**
         * Must be after all boot-up actions
         */
        add_action('prime_mover_bootup', [$this,'primeMoverBooted'], 999, 3);
        
        /**
         * Other hooks
         */
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setProgressShutDownNonces'], 10, 1 );        
        add_filter('prime_mover_ajax_rendered_js_object', [$this, 'setProgressError'], 35, 1 );        
        add_action('prime_mover_doing_package_download', [$this, 'setTempFilePath'], 10, 1);
        
        add_action('prime_mover_before_doing_import', [$this,'primeMoverDeactivateAllPlugins'], 10, 2);
        add_action('prime_mover_before_doing_import', [$this,'putPublicSiteToMaintenance'], 15, 1);
        
        add_action('prime_mover_do_things_tmp_deleted', [$this,'primeMoverReactivateAllPlugins'], 10, 1);
        add_action('prime_mover_after_renamedb_prefix', [$this,'putPublicSiteToMaintenance'], 10, 1);
        add_action('prime_mover_shutdown_actions', [$this, 'removePublicMaintenanceOnRunTimeErrors']);
        
        add_action('prime_mover_before_db_dump_export', [$this,'putPublicSiteToMaintenance'], 10, 1);
        add_action('prime_mover_after_db_dump_export', [$this,'deleteMaintenanceOption'], 10, 1);
        
        $this->getSystemFunctions()->switchToBlog(get_current_blog_id());
        $enabled = get_option(self::PRIMEMOVER_MAINTENANCE);

        $this->getSystemFunctions()->restoreCurrentBlog();
        
        if ($enabled) {
            add_action('get_header', [$this, 'publicMaintenanceDuringRestore']);
            add_action('admin_init', [$this, 'publicMaintenanceDuringRestore']);
        }        
        
        add_action('init', [ $this, 'maintenanceModeControl' ], 35);
        
        /**
         * Localized progress texts
         */
        add_filter( 'prime_mover_ajax_rendered_js_object', [$this, 'setLocalizedProgressTexts'], 130, 1 );
    }
 
    /**
     * Delete CLI tmp files on boot and shutdown sequence
     * @param string $process_id
     */
    public function deleteCliTmpFiles($process_id = '')
    {       
        if ( ! $process_id) {
            return;
        }

        $cli_progress_keys = $this->getSystemInitialization()->getCliProgressKeys();
        if ( ! is_array($cli_progress_keys) ) {
            return;
        }
        
        foreach ($cli_progress_keys as $cli_progress_key) {
            $cli_tmpname_path = $this->getSystemInitialization()->generateCliReprocessingTmpName([], $process_id, $cli_progress_key, true);
            if ($this->getSystemFunctions()->nonCachedFileExists($cli_tmpname_path)) {                
                $this->getSystemFunctions()->primeMoverDoDelete($cli_tmpname_path, true);
            }
        }
    }
    
    /**
     * Set export methods list
     * @param array $args
     * @return array
     */
    public function setLocalizedProgressTexts($args = [])
    {
        $args['prime_mover_download_percent_progress'] = esc_attr__('Download percent progress', 'prime-mover');
        $args['prime_mover_dropbox_upload_progress'] = esc_attr__('Upload Dropbox progress', 'prime-mover');
        $args['prime_mover_file_transfer_progress'] = esc_attr__('File transfer progress', 'prime-mover');
        $args['prime_mover_overall_percent_progress'] = esc_attr__('Overall percent progress', 'prime-mover');
        $args['prime_mover_gdrive_upload_progress'] = esc_attr__('Upload Google Drive progress', 'prime-mover');
        
        return $args;
    }
    
    /**
     * Get parameters
     * @return array
     */
    private function getParameters()
    {
        $args = [
            'prime_mover_maintenance' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'prime_mover_blogid' => FILTER_SANITIZE_NUMBER_INT,
            'prime_mover_disable_wpnonce' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter()
        ];
        return $this->getSystemInitialization()->getUserInput('get', $args, 'maintenance_control_parameters', '', 0, false);  
    }
    
    /**
     * Maintenance mode control
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itImplementsMaintenanceModeControl() 
     */
    public function maintenanceModeControl()
    {
        $params = $this->getParameters();
        if (empty($params['prime_mover_maintenance']) || empty($params['prime_mover_blogid']) || empty($params['prime_mover_disable_wpnonce'])) {
            return;
        }
        $nonce = $params['prime_mover_disable_wpnonce'];
        $maintenance = $params['prime_mover_maintenance'];
        $blog_id = (int)$params['prime_mover_blogid'];
        
        if ( ! $maintenance || ! $blog_id ) {
            return;
        }
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (!$this->getSystemFunctions()->primeMoverVerifyNonce($nonce, 'prime-mover-disable-maintenance' ) ) {
            return;
        } 
        if ('off' === $maintenance) {
            $this->deleteMaintenanceOption($blog_id);
        }
        if ('on' === $maintenance) {
            $this->putPublicSiteToMaintenance($blog_id);
        }
    }
    
    /**
     * Checks status of public maintenance
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itChecksIfNowPublicMaintenance()
     * @param number $blog_id
     * @return boolean
     */
    public function isNowPublicMaintenance($blog_id = 0)
    {
        if ( ! $blog_id && is_multisite()) {
            return false;
        }
        wp_cache_delete('alloptions', 'options');
        $enabled = $this->getSystemFunctions()->getBlogOption($blog_id, self::PRIMEMOVER_MAINTENANCE);
        if ($enabled) {
            return true;
        }
        return false;
    }
    
    /**
     * Delete maintenance option
     * @param number $process_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesMaintenanceOption()
     */
    public function primeMoverDeleteMaintenanceOption($process_id = 0, $blog_id = 0)
    {
        $this->deleteMaintenanceOption($blog_id); 
    }
    
    /**
     * Delete assembly option on boot
     * @param number $process_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesAssemblyOption()
     */
    public function primeMoverDeleteAssemblyOption($process_id = 0, $blog_id = 0)
    {
        $assembly_option = '_assembly_' . $process_id;
        delete_site_option($assembly_option);
    }
    
    /**
     * Delete assembly option on boot
     * @param number $process_id
     * @param number $blog_id
     */
    public function primeMoverDeleteThirdPartyPluginsOption($process_id = 0, $blog_id = 0)
    {
        $option = '_thirdpartyplugins_' . $process_id;
        delete_site_option($option);
    }
    
    /**
     * Delete assembled option on boot
     * @param number $process_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesAssembledOption()
     */
    public function primeMoverDeleteAssembledOption($process_id = 0, $blog_id = 0)
    {
        $assembled_option = '_assembled_' . $process_id;
        delete_site_option($assembled_option);
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesUploadProgressOption()
     * Delete upload progress option on boot
     * @param number $process_id
     * @param number $blog_id
     */
    public function primeMoverDeleteUploadProgressOption($process_id = 0, $blog_id = 0)
    {
        $uploadtracker_option = '_upload_progress_' . $process_id;
        delete_site_option($uploadtracker_option);
    }
    
    /**
     * Remove public maintenance mode on runtime errors
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itRemovesPublicMaintenanceOnRuntimeErrors()
     */
    public function removePublicMaintenanceOnRunTimeErrors()
    {
        $blog_id = $this->getShutDownUtilities()->primeMoverGetProcessedID();
        $this->deleteMaintenanceOption($blog_id);        
    }
    
    /**
     * Delete maintenance option
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesMaintenanceOptionInDb() 
     */
    public function deleteMaintenanceOption($blog_id = 0)
    {
        $this->getSystemFunctions()->switchToBlog($blog_id);
        delete_option(self::PRIMEMOVER_MAINTENANCE);
        $this->getSystemFunctions()->restoreCurrentBlog();
    }
    
    /**
     * When doing migrations or site restoration.
     * Make sure the public page of the site is showing its under maintenance
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itPutsPublicSiteToMaintenance()
     */
    public function putPublicSiteToMaintenance($blog_id = 0)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $this->getSystemFunctions()->switchToBlog($blog_id);
        update_option(self::PRIMEMOVER_MAINTENANCE, 'yes');
        $this->getSystemFunctions()->restoreCurrentBlog();
    }
    
    /**
     * Reactivate all plugins when user rejects diff
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itReactivatesAllPlugins() 
     */
    public function primeMoverReactivateAllPlugins($delete_tmpfile_post = [])
    {
        if (is_multisite() || empty($delete_tmpfile_post['diff_reject'])) {
            return;
        }
        $diff_reject = $delete_tmpfile_post['diff_reject'];
        if ('false' === $diff_reject) {
            return;
        }
        
        $this->reactivatePlugins();
    }
    
    /**
     * Reactivate plugins helper after an error or diff reject.
     */
    public function reactivatePlugins()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $singlesite_plugins = get_option(self::SINGLESITE_PLUGINS);
        $this->getSystemFunctions()->activatePlugin(0, '', $singlesite_plugins, false, false);
        
        delete_option(self::SINGLESITE_PLUGINS);
    }
    
    /**
     * Deactivate all plugins before import
     * @param number $blogid_to_import
     * @param boolean $import_initiated
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeactivatesAllPlugins()
     */
    public function primeMoverDeactivateAllPlugins($blogid_to_import = 0, $import_initiated = false)
    {
        if (is_multisite()) {
            return;
        }
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        /**
         * This must be a single site
         * We want to deactivate plugins only if import is not yet initiated
         */
        if ($import_initiated) {
            return;
        }
        
        $activated_plugins = $this->getSystemFunctions()->getActivatedPlugins();
        $corecomponents = $this->getSystemInitialization()->getCoreComponents();
        
        $thirdparty_plugins = array_diff($activated_plugins, $corecomponents);      
        update_option(self::SINGLESITE_PLUGINS, $thirdparty_plugins);
        
        if ( empty($thirdparty_plugins) ) {
            return;
        }
        $this->getSystemFunctions()->deactivatePlugins($thirdparty_plugins, true, false);        
    }
    
    /**
     * Delete runtime error log on fresh boot
     * @param string $process_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesRunTimeErrorLog()
     */
    public function primeMoverDeleteRunTimeErrorLog($process_id = '', $blog_id = 0)
    {
        $this->getShutDownUtilities()->primeMoverDeleteErrorLog($blog_id, false);
    }
    
    /**
     * Get shutdown utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverShutdownUtilities
     */
    public function getShutDownUtilities()
    {
        return $this->shutdown_utilities;
    }
    
    /**
     * Catch fatal runtime errors on progress handlers
     * @param number $blog_id
     * @return array|boolean[]|string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itGetsFatalRunTimeErrors() 
     */
    public function getFatalRunTimeErrors($blog_id = 0)
    {        
        $response = [];
        if ( ! $blog_id ) {
            return $response;
        }
        $error_log = $this->getSystemInitialization()->getErrorLogFile($blog_id);
        if ( $this->getShutDownUtilities()->primeMoverErrorLogExist( false, $blog_id, $error_log) ) {
            $errorlog_url = $this->getShutDownUtilities()->getDownloadErrorLogURL( $blog_id );
            
            $response['logexist'] = true;
            $response['error_msg'] = esc_html__('Runtime error : ', 'prime-mover' ) . '  ' . '<a href="' . esc_url($errorlog_url) . '">' .
                esc_html__( 'Report error', 'prime-mover') . '</a>';
        }
        return $response;
    }
    
    /**
     * Mark that the Boot process has started
     * @param string $process_id
     * @param number $blog_id
     * @param boolean $diffmode
     * @param string $mode
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itSetsPrimeMoverBooted()
     */
    public function primeMoverBooted($process_id = '', $blog_id = 0, $diffmode = false, $mode = 'import')
    {
        if ( ! $process_id || ! $mode ) {
            return;
        }
        $this->updateTrackerProgress('boot', $mode, $process_id);
    }
    
    /**
     * Setup progress error
     * @param array $args
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itSetsProgressError() 
     */
    public function setProgressError( array $args )
    {
        $args['prime_mover_progress_error_message'] = esc_js(
            sprintf(__('Progress reporting fails for blog ID {{BLOGID}}. Retry is attempted but still fails. %s',
                'prime-mover'),
                '<strong>' . __('Server Error : {{PROGRESSSERVERERROR}}', 'prime-mover') . '</strong>'));
                return $args;
    }
    
    /**
     * Get System Initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getShutDownUtilities()->getSystemInitialization();
    }
    
    /**
     * Get System authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getShutDownUtilities()->getSystemAuthorization();
    }

    /**
     * Set Shutdown nonces
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itSetsProgressShutdownNonces()
     */
    public function setProgressShutDownNonces( array $args )
    {
        $args['prime_mover_import_shutdown_nonce'] = $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_import_shutdown_nonce');
        $args['prime_mover_export_shutdown_nonce'] = $this->getSystemFunctions()->primeMoverCreateNonce('prime_mover_export_shutdown_nonce');
        
        return $args;
    }
    
    /**
     * Common shutdown processor
     * @param string $mode
     * @compatibility 5.6
     */
    public function commonShutDownProcessor($mode = 'import')
    {
        //Initialize response
        $response = [];        
        $shutdown_nonce = 'prime_mover_' . $mode . '_shutdown_nonce';
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            wp_send_json( $response );
        }
        
        //Initialize args
        $args = [
            $shutdown_nonce => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'process_id' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'blog_id' => FILTER_SANITIZE_NUMBER_INT
        ];
        
        //Validate shutdown ajax
        $shutdown_post = $this->getSystemInitialization()->getUserInput('post', $args, 'common_shutdown_processor', $mode, 0, true);
        if ( ! isset($shutdown_post[$shutdown_nonce]) || ! isset($shutdown_post[ 'process_id' ]) ||  ! isset($shutdown_post[ 'blog_id' ])) {
            wp_send_json( $response );
        }
        if ( ! $this->getSystemFunctions()->primeMoverVerifyNonce($shutdown_post[$shutdown_nonce], $shutdown_nonce)) {
            wp_send_json( $response );
        }
        
        //Get process id
        $process_id = $shutdown_post[ 'process_id' ];
        if ( ! $process_id) {
            wp_send_json( $response );
        }

        //Get blog_id
        $blog_id = $shutdown_post[ 'blog_id' ];
        if ( ! $blog_id) {
            wp_send_json( $response );
        }
  
        $this->getSystemFunctions()->switchToBlog($blog_id);
        delete_option(self::PRIMEMOVER_MAINTENANCE);
        $this->getSystemFunctions()->restoreCurrentBlog();

        do_action('prime_mover_log_processed_events', "Shutdown request fully validated, ready to shutdown $mode process", $blog_id, $mode, 'commonShutDownProcessor', $this);        
        do_action('prime_mover_shutdown', $process_id, $blog_id);
        $response['status'] = 'shutdown_processed';
        
        do_action('prime_mover_log_processed_events', "Shutdown request completed, response:", $blog_id, $mode, 'commonShutDownProcessor', $this);
        do_action('prime_mover_log_processed_events', $response, $blog_id, $mode, 'commonShutDownProcessor', $this);
        
        wp_send_json( $response );
    }
    
    /**
     * On public facing pages, return 503 maintenance mode at the official start of restoration.
     * @mainsitesupport_affected
     */
    public function publicMaintenanceDuringRestore()
    {
        if (wp_doing_ajax()) {
            return;
        }
        
        $blog_id = 1;
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
        }
        $nonce = $this->getSystemFunctions()->primeMoverCreateNonce('prime-mover-disable-maintenance');
        $arr_params = ['prime_mover_maintenance' => 'off', 'prime_mover_blogid' => $blog_id, 'prime_mover_disable_wpnonce' => $nonce];
        $url = esc_url(add_query_arg($arr_params));
        
        $message = '';
        
        if ($this->isNowPublicMaintenance($blog_id)) {
            $message .= '<p>' . esc_html__('Site is currently undergoing maintenance work. Please come back later.', 'prime-mover') . '</p>';
            if ($this->getSystemAuthorization()->isUserAuthorized()) {
                $message .= '<p><a href="' . $url . '">' .
                    esc_html__('Administrators : Click this link if you want to DISABLE MAINTENANCE MODE NOW', 'prime-mover') .
                    '</a>.</p>';
            }
        } else {            
            $message .= '<p>' . esc_html__('Prime Mover Maintenance Mode is now disabled. PLEASE RELOAD THIS PAGE.', 'prime-mover') . '</p>';
        }
        
        wp_die(
            $message, 
            esc_html__('Maintenance Mode', 'prime-mover'),             
            ['response' => 503]
        );        
    }
    
    /**
     * Common progress processor
     * @param string $mode
     * @compatibility 5.6
     */
    public function commonProgressProcessor($mode = 'import')
    {
        //Initialize response
       
        
        $response = [];
        $status_string = $mode . '_status';
        $output_string = $mode . '_result';
        $download_string = 'download_result';
        
        $progress_nonce = 'prime_mover_' . $mode . '_progress_nonce';
        
        $response[$status_string] = '';
        $response[$output_string] = '';
        $response[$download_string] = '';
        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return wp_send_json( $response );
        }
        
        //Initialize args
        $args = [
            $progress_nonce => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'blog_id' => FILTER_SANITIZE_NUMBER_INT,
            'action' => $this->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(),
            'trackercount' => FILTER_SANITIZE_NUMBER_INT,
            'diffmode' => FILTER_VALIDATE_BOOLEAN
        ];
        
        //Validate progress ajax
        $progress_post = $this->getSystemInitialization()->getUserInput('post', $args, 'common_progress_processor', $mode, 0, true);
        
        if ( ! isset($progress_post[$progress_nonce]) || ! isset($progress_post[ 'blog_id' ]) ) {
            return wp_send_json( $response );
        }
        if ( ! $this->getSystemFunctions()->primeMoverVerifyNonce($progress_post[$progress_nonce], $progress_nonce)) {
            return wp_send_json( $response );
        }
        
        //Get blog ID processed
        $blog_id = $progress_post[ 'blog_id' ];
        if ( ! $blog_id) {
            return wp_send_json( $response );
        }
        
        //Get user process ID
        $process_id = $this->generateTrackerId($blog_id, $mode);
        
        if ( ! empty($progress_post['trackercount']) && isset($progress_post['diffmode']) ) {
            $trackercount = (int) $progress_post['trackercount']; 
            $diffmode = (bool)$progress_post['diffmode'];
            if (1 === $trackercount) {                
                do_action('prime_mover_bootup', $process_id, $blog_id, $diffmode, $mode);
                do_action('prime_mover_log_processed_events', "Tracker count: " . $trackercount . ' BOOT-UP EVENT -DIFF MODE: ' . $diffmode,  $blog_id, $mode, 'commonProgressProcessor', $this);
                $is_local = $this->getSystemInitialization()->isLocalServer();
                do_action('prime_mover_log_processed_events', "Local server: " . $is_local ,  $blog_id, $mode, 'commonProgressProcessor', $this);
                $response['bootup'] = true;
                return wp_send_json( $response );                
            }
            do_action('prime_mover_log_processed_events', "Tracker count: " . $trackercount,  $blog_id, $mode, 'commonProgressProcessor', $this);
        }        
        
        //Catch fatal runtime errors
        $runtime_errors = $this->getFatalRunTimeErrors($blog_id);
        if ( ! empty($runtime_errors) ) {
            return wp_send_json( $runtime_errors );
        }
        
        $latest = $this->getTrackerProgressNonCached($blog_id, $mode);
        do_action('prime_mover_log_processed_events', "Getting latest progress: $latest with process id: $process_id", $blog_id, $mode, 'commonProgressProcessor', $this);
        if ($latest) {
            $response[$status_string] = $latest;            
        }
        $standard_processes = ['diffdetected', 'stoptracking'];
        if (in_array($latest, $standard_processes)) {
            $response[$output_string] = $this->getSystemFunctions()->getSiteOption($mode . '_' . $process_id);
            do_action('prime_mover_log_processed_events', "Standard process detected: $latest", $blog_id, $mode, 'commonProgressProcessor', $this);             
            
            if ('stoptracking' === $latest) {                
                do_action('prime_mover_log_processed_events', $response, $blog_id, $mode, 'commonProgressProcessor', $this);                
            }            
        }
        if ('package_downloaded' === $latest) {            
            
            $download_process_id = 'download_' . $process_id;
            $response[$download_string] = $this->getSystemFunctions()->getSiteOption($download_process_id);
            
            do_action('prime_mover_log_processed_events', "Package download event detected: $download_process_id", $blog_id, $mode, 'commonProgressProcessor', $this);           
            do_action('prime_mover_log_processed_events', $response, $blog_id, $mode, 'commonProgressProcessor', $this);
        }       
        
        $response = apply_filters('prime_mover_filter_progress_response', $response, $latest, $process_id, $blog_id, $mode);
        
        //Update client of the status
        wp_send_json( $response );
    }
    
    /**
     * Get tracker progress non cached
     * @param number $blog_id
     * @param string $mode
     * @return string|mixed|boolean|NULL|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itGetsTrackerProgressNonCached()
     */
    public function getTrackerProgressNonCached($blog_id = 0, $mode = 'import')
    {
        if ( ! $blog_id || ! $mode ) {
            return '';
        }
        
        $process_id = $this->generateTrackerId($blog_id, $mode);  
        $network_id = get_current_network_id();
        
        $notoptions_key = "$network_id:notoptions";
        wp_cache_delete( $notoptions_key, 'site-options' );
        
        $progress = $this->getSystemFunctions()->getSiteOption($process_id, '', false);
        
        do_action('prime_mover_log_processed_events', "Tracker progress non-cached request", $blog_id, $mode, 'commonProgressProcessor', $this);
        do_action('prime_mover_log_processed_events', "getSiteOption($process_id) = $progress", $blog_id, $mode, 'commonProgressProcessor', $this);
        
        return $progress;
    }
    
    /**
     * Generate tracker ID for monitoring the progress of a specific import/export process.
     * @param int $blog_id
     * @param string $mode
     * @return number|string
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itGeneratesTrackerId()
     */
    public function generateTrackerId( $blog_id = 0, $mode = 'import')
    {
        $process_id = '';
        if ( ! $blog_id ) {
            return $process_id; 
        }        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $process_id;
        }
        $user_ip = $this->getSystemInitialization()->getUserIp();
        if (! $user_ip) {
            return $process_id;
        }
        $user_id = get_current_user_id();
        $browser = $this->getSystemInitialization()->getUserAgent();
        
        $string = $browser . $user_ip . $user_id . $blog_id . $mode;        
        
        return hash('sha256', $string);
    }
    
    /**
     * Progress trackers initialize
     * @param int $blog_id
     * @param string $mode
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itInitializesProgressTrackers() 
     */
    public function initializeProgressTracker( $blog_id = 0, $mode = 'import')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized() || ! $blog_id) {
            return;
        }
        
        do_action('prime_mover_log_processed_events', "Initializing progress tracker", $blog_id, $mode, 'initializeProgressTracker', $this); 
        
        //Generate import id for this import
        $process_id = $this->generateTrackerId($blog_id, $mode);
        
        $this->setActiveProcesses($process_id, $blog_id);
        
        //Initialize the import ID property
        if ('import' === $mode) {
            $this->getSystemInitialization()->setImportId($process_id);
        } 
        
        if ('export' === $mode) {
            $this->getSystemInitialization()->setExporttId($process_id);
        } 
    }
    
    /**
     * Save tracker progress
     * @param string $progress
     * @param string $mode
     * @param string $process_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itUpdatesTrackerProgress()
     */
    public function updateTrackerProgress($progress = '', $mode = 'import', $process_id = '')
    {        
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $input_id = '';
        if ('import' === $mode) {
            $input_id = $this->getSystemInitialization()->getImportId();
        }
        if ('export' === $mode) {
            $input_id = $this->getSystemInitialization()->getExportId();
        }      
            
        if ($process_id) {
            $input_id = $process_id;
        }            
        if ($input_id) {
            add_action( "update_site_option_{$input_id}", [$this, 'dontCache'], 10, 1);
            $this->getSystemFunctions()->updateSiteOption($input_id , $progress, true);
            $blog_id = $this->getActiveProcessBlogId($input_id);
            do_action('prime_mover_log_processed_events', "Update tracker progress: updateSiteOption($input_id, $progress);", $blog_id, $mode, 'updateTrackerProgress', $this);
        }  
    }
 
    /**
     * Dont cache tracker progress for reporting accuracy
     * @param string $option
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDontCache() 
     */
    public function dontCache($option = '')
    {
        $network_id = get_current_network_id();
        $cache_key = "$network_id:$option";
        
        wp_cache_delete( $cache_key, 'site-options');
    }

    /**
     * Delete download result ID on PHP shutdown
     * @param number $import_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesDownloadResultId()
     */
    public function primeMoverDeleteDownloadResultId($import_id = 0, $blog_id = 0)
    {
        $option = 'download_' . $import_id;
        delete_site_option($option);    
        
        do_action('prime_mover_log_processed_events', "Deleted download result ID: $option", $blog_id, 'import', __FUNCTION__, $this);        
    }
    
    /**
     * Delete export result ID on PHP shutdown
     * @param number $export_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesExportResultId()
     */
    public function primeMoverDeleteExportResultId($export_id = 0, $blog_id = 0)
    {
        $export_result_id = 'export_' . $export_id;
        
        delete_site_option($export_result_id); 
        do_action('prime_mover_log_processed_events', "Deleted export result ID: $export_id", $blog_id, 'export', __FUNCTION__, $this);
    }
    
    /**
     * Delete import result ID on PHP shutdown
     * @param number $import_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesImportResultId()
     */
    public function primeMoverDeleteImportResultId($import_id = 0, $blog_id = 0)
    {
        $import_result_id = 'import_' . $import_id;
        delete_site_option($import_result_id);
        do_action('prime_mover_log_processed_events', "Deleted import result ID:  $import_result_id", $blog_id, 'import', __FUNCTION__, $this);
    }
    
    /**
     * Delete import ID just before PHP shuts down execution.
     * @compatibility 5.6
     * @param number $import_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesImportId()
     */
    public function primeMoverDeleteImportId($import_id = 0, $blog_id = 0)
    {
        delete_site_option($import_id);
        do_action('prime_mover_log_processed_events', "Deleted import ID:  $import_id", $blog_id, 'import', __FUNCTION__, $this);
    }
    
    /**
     * 
     * Delete export ID just before PHP shuts down execution.
     * @compatibility 5.6
     * @param number $export_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesExportId()
     */
    public function primeMoverDeleteExportId($export_id = 0, $blog_id = 0)
    {        
        delete_site_option($export_id);
        do_action('prime_mover_log_processed_events', "Deleted export ID:  $export_id", $blog_id, 'export', __FUNCTION__, $this);
    }
    
    /**
     * Delete download size ID
     * @param number $import_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesDownloadSizeId()
     */
    public function primeMoverDeleteDownloadSizeId($import_id = 0, $blog_id = 0)
    {
        $option = 'download_size_' . $import_id;
        delete_site_option($option);
        
        do_action('prime_mover_log_processed_events', "Deleted download size ID: $option", $blog_id, 'import', __FUNCTION__, $this);         
    }
    
    /**
     * Delete dropbox upload size ID
     * @param number $export_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeleteDropBoxUploadSizeOption()
     */
    public function primeMoverDeleteUploadDropBoxSize($export_id = 0, $blog_id = 0)
    {
        $option = 'dropboxupload_size_' . $export_id;
        delete_site_option($option);
        
        do_action('prime_mover_log_processed_events', "Deleted dropbox upload size ID: $option", $blog_id, 'export', __FUNCTION__, $this);
    }
 
    /**
     * Delete dropbox upload size ID
     * @param number $export_id
     * @param number $blog_id
     */
    public function primeMoverDeleteUploadGdriveSize($export_id = 0, $blog_id = 0)
    {
        $option = 'gdriveupload_size_' . $export_id;
        delete_site_option($option);
        
        do_action('prime_mover_log_processed_events', "Deleted GDrive upload size ID: $option", $blog_id, 'export', __FUNCTION__, $this);
    }
    
    /**
     * Delete download tmp file ID
     * @param number $import_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesDownloadTmpFileId()
     */
    public function primeMoverDeleteDownloadTmpFileId($import_id = 0, $blog_id = 0)
    {
        $option = 'download_tmp_path_' . $import_id;
        delete_site_option($option);
        
        do_action('prime_mover_log_processed_events', "Deleted download tmp file ID: $option", $blog_id, 'import', __FUNCTION__, $this);
    }
    
    /**
     * Set downloaded package
     * @param string $process_id
     * @param number $content_length
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itSetsDownloadPackageSize()
     */
    public function setDownloadedPackageSize($process_id = '', $content_length = 0)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ( ! $process_id || ! $content_length ) {
            return;
        }
        
        $key = 'download_size_' . $process_id;
        add_action( "update_site_option_{$key}", [$this, 'dontCache'], 10, 1);
        
        $this->getSystemFunctions()->updateSiteOption($key, $content_length);
    }
    
    /**
     * Set upload size for Dropbox package
     * @param string $process_id
     * @param number $content_length
     * @param number $fileSize
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itSetsUploadSizeDropbox() 
     */
    public function setUploadSizeDropBox($process_id = '', $content_length = 0, $fileSize = 0) 
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ( ! $process_id || ! $content_length || ! $fileSize) {
            return;
        }
        
        $key = 'dropboxupload_size_' . $process_id;        
        $this->setUploadSizeHelper($key, $content_length, $fileSize);
    }

    /**
     * Set upload size for GDrive package
     * @param string $process_id
     * @param number $content_length
     * @param number $fileSize
     */
    public function setUploadSizeGdrive($process_id = '', $content_length = 0, $fileSize = 0)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if ( ! $process_id || ! $content_length || ! $fileSize) {
            return;
        }
        
        $key = 'gdriveupload_size_' . $process_id;
        $this->setUploadSizeHelper($key, $content_length, $fileSize);
    }
    
    /**
     * Upload size helper
     * @param string $key
     * @param string $content_length
     * @param number $fileSize
     */
    protected function setUploadSizeHelper($key = '', $content_length = '', $fileSize = 0)
    {
        add_action( "update_site_option_{$key}", [$this, 'dontCache'], 10, 1);
        $value = ['current_upload_size' => $content_length, 'total_upload_size' => $fileSize];
        
        $this->getSystemFunctions()->updateSiteOption($key, $value);
    }
    
    /**
     * Set temp file path
     * @param array $r
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itSetsTmpFilePath()
     */
    public function setTempFilePath($r = [])
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (empty($r['filename'])) {
            return;
        }
        
        $import_blog_id = $this->getSystemInitialization()->getImportBlogID();
        if ( ! $import_blog_id ) {
            return;
        }
        
        $process_id = $this->generateTrackerId($import_blog_id, 'import');        
        $key = 'download_tmp_path_' . $process_id;
        
        add_action( "update_site_option_{$key}", [$this, 'dontCache'], 10, 1);        
        $this->getSystemFunctions()->updateSiteOption($key, $r['filename']);         
    }
    
    /**
     * Delete user progress meta
     * @param number $process_id
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDeletesUserProgressMetaWhenUserIsSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverProgressHandlers::itDoesNotDeletesUserProgressMetaWhenUserIsNotSet() 
     */
    public function primeMoverDeleteUserProgressMeta($process_id = 0, $blog_id = 0)
    {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }
        delete_user_meta($user_id, $process_id);
    }
    
    /**
     * Upload progress helper
     * @param string $action
     * @param string $identifier
     * @param array $response
     * @param string $mode
     * @param string $process_id
     * @param string $latest
     * @param number $blog_id
     * @return array
     */
    public function monitorUploadProgressHelper($action = '', $identifier = '', $response = [], $mode = '', $process_id = '', $latest = '', $blog_id = 0)
    {
        if ('import' === $mode) {
            return $response;
        }
        $upload_process_id = $identifier . $process_id;
        $upload_progress = $this->getSiteOptionNoCache($upload_process_id);
        if (empty($upload_progress['current_upload_size']) || empty($upload_progress['total_upload_size'])) {
            return $response;
        }
        $current_upload_size = (int)$upload_progress['current_upload_size'];
        $total_upload_size = (int)$upload_progress['total_upload_size'];
        
        if ( ! $current_upload_size || ! $total_upload_size ) {
            return $response;
        }
        
        if ($action !== $latest) {
            return $response;
        }
        
        do_action('prime_mover_log_processed_events', 'Upload process ID: ' . $upload_process_id,  $blog_id, $mode, __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', 'Total upload package size',  $blog_id, $mode, __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', $total_upload_size,  $blog_id, $mode,  __FUNCTION__, $this);
        
        do_action('prime_mover_log_processed_events', 'Current upload progress size',  $blog_id, $mode,  __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', $current_upload_size,  $blog_id, $mode,  __FUNCTION__, $this);
        
        $response['total_upload_size'] = $total_upload_size;
        $response['ongoing_size'] = $current_upload_size;
        
        return $response;
    }
    
    /**
     * Do not cache site options site option retrieval in progress processes
     */
    private function noCache()
    {
        $network_id = get_current_network_id();
        $notoptions_key = "$network_id:notoptions";
        wp_cache_delete( $notoptions_key, 'site-options' );
    }
    
    /**
     * Get site option no cache
     * @param string $key
     * @return mixed|boolean|NULL|array
     */
    private function getSiteOptionNoCache($key = '')
    {
        $this->noCache();
        return $this->getSystemFunctions()->getSiteOption($key);
    }
}