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

use Codexonics\PrimeMoverFramework\utilities\PrimeMoverUserUtilities;
use WP_User_Query;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover User class handle users export and import functionality 
 *
 */
class PrimeMoverUsers
{    
    
    private $plugin_user_metas;
    private $user_utilities;
    private $is_exporting_user;
    private $left_off;
    private $core_user_metas;
    
    /**
     * Constructor
     * @param PrimeMoverUserUtilities $user_utilities
     */
    public function __construct(PrimeMoverUserUtilities $user_utilities) 
    {
        $this->user_utilities = $user_utilities;   
        $this->is_exporting_user = false;
        $this->left_off = 0;
        $this->core_user_metas = [
            'dashboard_quick_press_last_post_id',
            'user-settings',
            'user-settings-time',
            'media_library_mode'            
        ];
        
        $this->plugin_user_metas = [
            'yoast_notifications',
            'metronet_avatar_override',
            'woocommerce_product_import_mapping',
            'tablepress_user_options',
            'elementor_connect_common_data',
            'metronet_post_id',
            'metronet_image_id'
        ];
    }   
    
    /**
     * Get plugin metas
     * @return string[]
     */
    public function getPluginUserMetas()
    {
        return $this->plugin_user_metas;
    }
    
    /**
     * Get core metas
     * @return string[]
     */
    public function getCoreUserMetas()
    {
        return $this->core_user_metas;
    }
    /**
     * Check if we are exporting user
     * @return boolean
     */
    public function getIsExportingUser()
    {
        return $this->is_exporting_user;
    }
    
    /**
     * Set is exporting user
     * @param boolean $exporting_user
     */
    public function setIsExportingUser($exporting_user = false)
    {
        $this->is_exporting_user = $exporting_user;
    }
    
    /**
     * Get left off
     * @return number
     */
    public function getLeftOff()
    {
        return $this->left_off;
    }
    
    /**
     * Set left off
     * @param number $left_off
     */
    public function setLeftOff($left_off = 0)
    {
        $this->left_off = $left_off;
    }
    
    /**
     * Initialize hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUsers::itAddsInitHooks()
     */
    public function initHooks()
    {
        add_filter('prime_mover_filter_user_metas', [$this, 'changeUserCapToCorrectPrefix'], 10, 3);
        add_filter('prime_mover_filter_user_metas', [$this, 'changeOtherUserMetaPrefixesToCorrectPrefix'], 20, 3);
        add_filter('prime_mover_filter_user_metas', [$this, 'removeRedundantUserMetas'], 999, 3);
                   
        add_filter('prime_mover_after_user_meta_import', [$this, 'correctUserCapAfterMetaImport'], 10, 6);        
        add_filter('prime_mover_after_user_meta_import', [$this, 'correctOtherUserMetaPrefixes'], 20, 6);
        add_action('wp_loaded', [$this, 'maybeSaveUserTaxonomy']);  
        
        add_filter('prime_mover_tables_for_replacement', [$this, 'excludeUsersTableSrchReplace'], 10, 1);
        add_filter('prime_mover_excluded_meta_keys', [$this, 'excludeAdministratorCapKeys'], 99, 3);
        add_action('prime_mover_after_user_export', [$this, 'writeSpecialUserMetasToJson'], 10, 1);  
        
        add_action('prime_mover_before_db_processing', [$this, 'backupOriginalUserRole'], 99);
        add_filter('prime_mover_validate_thirdpartyuser_processing', [$this, 'maybeRequisitesNotMeetForAdjustment'], 10, 3);
        add_filter('prime_mover_process_userid_adjustment_db', [$this, 'dBCustomerUserIdsHelper'], 10, 13);      
    }   
    
    /**
     * Check requisites for user third party adjustment
     * @param array $ret
     * @param number $blogid_to_import
     * @param string $target_plugin
     * @return string|boolean
     */
    public function maybeRequisitesNotMeetForAdjustment($ret = [], $blogid_to_import = 0, $target_plugin = '')
    {
        return $this->getUserUtilities()->getUserFunctions()->getUserQueries()->maybeRequisitesNotMeetForAdjustment($ret, $blogid_to_import, $target_plugin);
    }

    /**
     * User ID adjustment filter callback
     * @param array $ret
     * @param string $table
     * @param number $blogid_to_import
     * @param string $leftoff_identifier
     * @param string $primary_index
     * @param string $column_strings
     * @param string $update_variable
     * @param string $progress_identifier
     * @param number $start_time
     * @param boolean $last_processor
     * @param string $handle_unique_constraint
     * @param boolean $non_user_adjustment
     * @param array $filter_clause
     * @return array|boolean
     */
    public function dBCustomerUserIdsHelper($ret = [], $table = '', $blogid_to_import = 0, $leftoff_identifier = '', $primary_index = '', $column_strings = '',
        $update_variable = '', $progress_identifier = '', $start_time = 0, $last_processor = false, $handle_unique_constraint = '', $non_user_adjustment = false, $filter_clause = [])
    {
        return $this->getUserUtilities()->getUserFunctions()->getUserQueries()->dBCustomerUserIdsHelper($ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint, $non_user_adjustment, $filter_clause);
    }
    
    /**
     * Backup original user role for emergencry restoration purposes.
     */
    public function backupOriginalUserRole()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (is_multisite()) {
            return;
        }        
        
        $current_user_id = get_current_user_id();
        $db_prefix = $this->getSystemFunctions()->getDbPrefixOfSite(1);
        $current_role_option = $db_prefix . 'user_roles';
        $option_value = $this->getSystemFunctions()->getOption($current_role_option);
      
        do_action('prime_mover_update_user_meta', $current_user_id, $this->getSystemInitialization()->getDefaultUserRole(), $option_value); 
    }
    
    /**
     * One shot loop to fix all redundant user metas
     * This improves user import perfornance and fixes capability isues.
     * @param array $user_meta
     * @param array $ret
     * @param number $blog_id
     * @return array
     * Hooked to `prime_mover_filter_user_metas`
     */
    public function removeRedundantUserMetas($user_meta = [], $ret = [], $blog_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $user_meta;
        }
        
        if (empty($ret['randomizedbprefixstring']) || ! $blog_id) {
            return $user_meta;
        }
        $target_prefix = $ret['randomizedbprefixstring'];
        $target = $this->getUserUtilities()->getUserCapabilityMetas($target_prefix);
        if (empty($target)) {
            return $user_meta;
        }
        
        list($target_cap_prefix, $target_level_prefix) = $target;   
        foreach(array_keys($user_meta) as $key) {
            if (!isset($user_meta[$key][0])) {
                continue;
            }            
            if ($this->getSystemFunctions()->endsWith($key, '_capabilities') && $key !== $target_cap_prefix && is_serialized($user_meta[$key][0])) {
                unset($user_meta[$key]);
            }  
            if  ($this->getSystemFunctions()->endsWith($key, '_user_level') && $key !== $target_level_prefix && (ctype_digit(strval($user_meta[$key][0])))) {
                unset($user_meta[$key]);
            }
            foreach ($this->getCoreUserMetas() as $coremeta) {
                if (($this->getSystemFunctions()->endsWith($key, '_' . $coremeta) && $key !== $target_prefix . $coremeta)) {
                    unset($user_meta[$key]);
                }                 
            }
            
            foreach ($this->getPluginUserMetas() as $plugin_usermeta) {
                if (($this->getSystemFunctions()->endsWith($key, '_' . $plugin_usermeta) && $key !== $target_prefix . $plugin_usermeta)) {
                    unset($user_meta[$key]);
                }
            }
            
            if (primeMoverIsShaString($key) && is_serialized($user_meta[$key][0])) {
                unset($user_meta[$key]);
            }  
        } 
        
        return $user_meta;
    }
    
    /**
     * Exclude administrator capability keys from restore
     * @param array $excluded
     * @param number $user_id
     * @param string $processed_meta_key
     * @return array
     */
    public function excludeAdministratorCapKeys($excluded = [], $user_id = 0, $processed_meta_key = '')
    {
        if (is_multisite() || !$this->getSystemAuthorization()->isPrimeMoverUser($user_id)) {
            return $excluded;
        }
        
        global $wpdb;
        if ($processed_meta_key === $wpdb->base_prefix . 'capabilities' || $processed_meta_key === $wpdb->base_prefix . 'user_level') {
            $excluded[] = $processed_meta_key;
        }       
        
        return $excluded;
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUsers::itExcludesUsersInSearchReplace()
     * Exclude users table in search replace
     * @param array $all_tables
     * @return array
     */
    public function excludeUsersTableSrchReplace($all_tables = [])
    {
        if (is_multisite() || empty($all_tables) || ! is_array($all_tables) ) {
            return $all_tables;
        }
        
        global $wpdb;
        $users_table = $wpdb->users;
        
        $key = array_search($users_table, $all_tables);
        if (false === $key) {
            return $all_tables;
        }
        
        if (isset($all_tables[$key])) {
            unset($all_tables[$key]);
        }
        
        return $all_tables;        
    }
    
    /**
     * Maybe save user taxonomy if its implemented
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUsers::itMaybeSavesUserTaxonomy() 
     */
    public function maybeSaveUserTaxonomy()
    {
        $this->getUserUtilities()->getUserFunctions()->getUserQueries()->maybeSaveUserTaxonomy();
    }
    
    /**
     * Write special user metas to json file
     * @param array $ret
     * Hooked to `prime_mover_after_user_export`
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUsers::itWritesSpecialUserMetasToJson() 
     */
    public function writeSpecialUserMetasToJson($ret = [])
    {
        $this->getUserUtilities()->writeSpecialUserMetasToJson($ret);
    }
    
    /**
     * Update other user meta prefixes to correct prefix during EXPORT process
     * Hooked to `prime_mover_filter_user_metas`
     * This method RUNS FOR EACH USER
     * @param array $user_meta
     * @param array $ret
     * @param number $blog_id
     * @return array
     */
    public function changeOtherUserMetaPrefixesToCorrectPrefix($user_meta = [], $ret = [], $blog_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $user_meta;
        }
        
        if (empty($ret['randomizedbprefixstring']) || ! $blog_id) {
            return $user_meta;
        }
        
        if ( ! isset($ret['usermeta_keys_export_adjust']) || ! is_array($ret['usermeta_keys_export_adjust'])) {
            return;
        }
        $keys_matrix = $ret['usermeta_keys_export_adjust'];
        $affected_meta_keys = array_keys($ret['usermeta_keys_export_adjust']);
        if ( ! is_array($affected_meta_keys) ) {
            return;
        }
     
        $capability_keys = $this->getUserUtilities()->getUserCapabilityMetas('', $blog_id);
        if (empty($capability_keys)) {
            return $user_meta;
        }
        
        if (empty($affected_meta_keys)) {
            return $user_meta;
        }
        $affected_meta_keys = array_diff($affected_meta_keys, $capability_keys);        
        return $this->getUserUtilities()->updateAllAffectedMetaKeys($user_meta, $affected_meta_keys, $blog_id, $keys_matrix);
    }
    
    /**
     * Correct other user meta prefixes during IMPORT process
     * @param array $ret
     * @param number $user_id
     * @param number $blog_id
     * @param number $start_time
     * @param array $retry_params
     * @return array
     * Hooked to `prime_mover_after_user_meta_import`
     * $ret, $user_id_updated, $blog_id, $start_time, $retry_params, $do_meta_loops
     */
    public function correctOtherUserMetaPrefixes($ret = [], $user_id = 0, $blog_id = 0, $start_time = 0, $retry_params = [], $do_meta_loops = true)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        if ( ! $user_id || ! $blog_id || empty($ret) ) {
            return $ret;
        }
        
        if ( ! isset($ret['origin_db_prefix'] ) ) {
            return $ret;
        }
       
        if ( ! isset($ret['usermeta_keys_import_adjust']) ) {
            return $ret;
        }
        
        $current_prefix = $this->getSystemFunctions()->getDbPrefixOfSite($blog_id);
        $origin_site_db_prefix = $ret['origin_db_prefix'];
        
        if (isset($ret['ongoing_usermeta_prefix_fix'])) {
            
            $affected = $ret['ongoing_usermeta_prefix_fix'];
            unset($ret['ongoing_usermeta_prefix_fix']);
            
        } else {
            
            $affected = $ret['usermeta_keys_import_adjust'];            
            if ( ! is_array($affected) ) {
                return $ret;
            }
            $affected = array_values($affected);
            $current = $this->getUserUtilities()->getUserCapabilityMetas('', $blog_id);
            if (empty($current)) {
                return $ret;
            }
            $affected = array_diff($affected, $current);
        }        
 
        $user_meta_prefix_processed = 0;
        if (isset($ret['users_meta_prefix_processed_count'])) {
            $user_meta_prefix_processed = (int)$ret['users_meta_prefix_processed_count'];
            
            unset($ret['users_meta_prefix_processed_count']);
        }
        
        foreach ($affected as $k => $meta_key) {            
            $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
            if ( (microtime(true) - $start_time) > $retry_timeout) {  
                
                do_action('prime_mover_log_processed_events', "$retry_timeout seconds time out on prefix fix." , $blog_id, 'import', __FUNCTION__, $this);
                return $this->doOtherMetaPrefixRetry($ret, $retry_params, $affected, $user_meta_prefix_processed);
            }
            if (is_array($meta_key) || is_object($meta_key)) {
                unset($affected[$k]);
                $user_meta_prefix_processed++;
                continue;
            }
            $existing = get_user_meta($user_id, $meta_key, true);
            if ( ! $existing) {
                unset($affected[$k]);
                $user_meta_prefix_processed++;
                continue;
            }
            $prefix_free = $this->getSystemFunctions()->removePrefix($origin_site_db_prefix, $meta_key);
            if ($prefix_free === $meta_key) {
                unset($affected[$k]);
                $user_meta_prefix_processed++;
                continue;
            }
            
            $new_meta_key = $current_prefix . $prefix_free;            
            do_action('prime_mover_update_user_meta', $user_id, $new_meta_key, $existing);
            
            delete_user_meta($user_id, $meta_key);
            unset($affected[$k]);
            $this->maybeDelayOtherMetaPrefix();   
            $user_meta_prefix_processed++;
        }  
     
        return $this->cleanRetryArrayParams($ret);
    }
    
    /**
     * Clean retry array parameters
     * @param array $ret
     * @return array
     */
    protected function cleanRetryArrayParams($ret = [])
    {
        if (isset($ret['users_import_offset'])) {
            unset($ret['users_import_offset']);
        }
        
        if (isset($ret['users_meta_processed_count'])) {
            unset($ret['users_meta_processed_count']);
        }
        
        if (isset($ret['user_id_updated_under_process'])) {
            unset($ret['user_id_updated_under_process']);
        }
        
        if (isset($ret['correctotherusermetaprefixes'])) {
            unset($ret['correctotherusermetaprefixes']);
        }
        
        if (isset($ret['ongoing_usermeta_prefix_fix'])) {
            unset($ret['ongoing_usermeta_prefix_fix']);
        }
        
        if (isset($ret['users_meta_prefix_processed_count'])) {
            unset($ret['users_meta_prefix_processed_count']);
        }
        
        return $ret;
    }
    
    /**
     * Do other meta prefix retry
     * @param array $ret
     * @param array $retry_params
     * @param array $affected
     * @param number $user_meta_prefix_processed
     * @return array
     * Should only applicable on a per-user basis. So its resetted for each user.
     */
    protected function doOtherMetaPrefixRetry($ret = [], $retry_params = [], $affected = [], $user_meta_prefix_processed = 0)
    {        
        list($orig_pos, $user_meta_processed, $user_id_updated, $users_imported) = $retry_params;
        $ret['users_import_offset'] = $orig_pos;
        $ret['users_meta_processed_count'] = $user_meta_processed;
        
        $ret['user_id_updated_under_process'] = $user_id_updated;       
        $ret['ongoing_usermeta_prefix_fix'] = $affected;
        $ret['correctotherusermetaprefixes'] = true;   
        
        $ret['total_users_imported'] = $users_imported;
        $ret['users_meta_prefix_processed_count'] = $user_meta_prefix_processed;
        
        return $ret;
    }
    
    /**
     * Maybe evaluate for delays
     */
    protected function maybeDelayOtherMetaPrefix()
    {
        if (defined('PRIME_MOVER_DELAY_USERMETA_PREFIXES') && PRIME_MOVER_DELAY_USERMETA_PREFIXES) {
            $delay = (int)PRIME_MOVER_DELAY_USERMETA_PREFIXES;
            $this->getSystemInitialization()->setProcessingDelay($delay, true);
        }
    }
    
    /**
     * Update post authors
     * @param array $ret
     * @param number $blog_id
     * @param number $start_time
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUsers::itUpdatesPostAuthors()
     */
    public function updatePostAuthors($ret = [], $blog_id = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized() || ! $blog_id ) {
            return $ret;
        }
        if (! isset($ret['user_equivalence']) || ! isset($ret['total_posts_update'] ) ) {
            do_action('prime_mover_log_processed_events', "User equivalence or total post update not set, skipping post authors update.", $blog_id, 'import', __FUNCTION__, $this);
            return $ret;
        }   
        
        $total_post_count = $ret['total_posts_update'];
        $user_equivalence = $ret['user_equivalence'];
        
        return $this->getUserUtilities()->getUserFunctions()->updatePostAuthors($user_equivalence, $total_post_count, $blog_id, $start_time, $ret);
    }
    
    /**
     * Generate user equivalence
     * @param array $ret
     * @param number $blog_id
     * @param number $start_time
     * @return array
     */
    public function generateUserEquivalence($ret = [], $blog_id = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized() || ! $blog_id ) {
            return $ret;
        }
       
        $this->getSystemFunctions()->switchToBlog($blog_id);
        $delete_tmp = false;
        $tmp = $ret['user_import_tmp_log'];                 
        $ret = $this->getUserUtilities()->getUserEquivalence($tmp, $blog_id, $ret, $start_time); 
        
        if ( ! empty($ret['user_equivalence']) && ! isset($ret['users_equivalence_offset'])) {
            $delete_tmp = true;
        }       
        $this->getSystemFunctions()->restoreCurrentBlog();
        if ($delete_tmp) {
            do_action('prime_mover_log_processed_events', "Done generating user equivalence, deleting tmp log path $delete_tmp . ", $blog_id, 'import', __FUNCTION__, $this);
            $this->getSystemFunctions()->primeMoverDoDelete($tmp, true);
        }
 
        return $ret;
    }
   
    /**
     * Correct user capability after import
     * @param array $ret
     * @param number $user_id
     * @param number $blog_id
     * @return array $ret
     * Hooked to `prime_mover_after_user_meta_import`
     */
    public function correctUserCapAfterMetaImport($ret = [], $user_id = 0, $blog_id = 0, $start_time = 0, $retry_params = [], $do_meta_loops = true)
    {
        if (! $do_meta_loops) {
            return $ret;
        }
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        if ( ! $user_id || ! $blog_id || empty($ret) ) {
            return $ret;
        }
        if ( ! isset($ret['origin_db_prefix'] ) ) {
            return $ret;
        }

        $current = $this->getUserUtilities()->getUserCapabilityMetas('', $blog_id);
        if (empty($current)) {
            return $ret;
        }
        
        list($current_cap_prefix, $current_level_prefix) = $current;       
        $target_prefix = $ret['origin_db_prefix'];
       
        $target = $this->getUserUtilities()->getUserCapabilityMetas($target_prefix);
        if (empty($target)) {
            return $ret;
        }
      
        list($target_cap_prefix, $target_level_prefix) = $target;         
        if ($this->getSystemAuthorization()->isPrimeMoverUser($user_id)) {
            $this->getUserUtilities()->getUserFunctions()->maybeAddUserToBlog($user_id, $target_cap_prefix, $target_level_prefix, $blog_id, $current_cap_prefix, $current_level_prefix);
            return $ret;
        }
        
        $this->getUserUtilities()->getUserFunctions()->updateUserRoleToTargetSite($user_id, $target_cap_prefix, $target_level_prefix, $current_cap_prefix, $current_level_prefix); 
        return $ret;
    }
    
    /**
     * Change user cap metas to user correct prefix
     * @param array $user_meta
     * @param array $ret
     * @param number $blog_id
     * @return array
     * Hooked to `prime_mover_filter_user_metas`
     */
    public function changeUserCapToCorrectPrefix($user_meta = [], $ret = [], $blog_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $user_meta;
        }
        
        if (empty($ret['randomizedbprefixstring']) || ! $blog_id) {
            return $user_meta;
        }
        $target_prefix = $ret['randomizedbprefixstring'];
        $target = $this->getUserUtilities()->getUserCapabilityMetas($target_prefix);
        if (empty($target)) {
            return $user_meta;
        }
        
        list($target_cap_prefix, $target_level_prefix) = $target;
        
        $current = $this->getUserUtilities()->getUserCapabilityMetas('', $blog_id);
        if (empty($current)) {
            return $user_meta;
        }
        
        list($current_cap_prefix, $current_level_prefix) = $current;

        return $this->getUserUtilities()->updateAllAffectedCaps($user_meta, $current_cap_prefix, $target_cap_prefix, $current_level_prefix , $target_level_prefix);
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getUserUtilities()->getSystemFunctions();
    }
    
    public function getCliArchiver()
    {
        return $this->getUserUtilities()->getUserFunctions()->getCliArchiver();
    }
    
    /**
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getUserUtilities()->getSystemInitialization();
    }
    
    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getUserUtilities()->getSystemAuthorization();
    }
    
    /**
     * Get user utilities
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverUserUtilities
     */
    public function getUserUtilities()
    {
        return $this->user_utilities;
    }
    
    /**
     * Add user json to zip archive
     * @param array $ret
     * @param number $start_time
     * @param number $blogid_to_export
     * @return array
     */
    public function addUserJsonToArchiveNonShellMode($ret = [], $start_time = 0, $blogid_to_export = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }  
        
        $tmp_folderpath = $ret['temp_folder_path'];          
        $source = wp_normalize_path($this->getUserUtilities()->returnPathToUserExportFile($tmp_folderpath));
        $user_export_filepath = wp_normalize_path($this->getUserUtilities()->returnPathToUserMetaExportFile($tmp_folderpath));

        if (! isset($ret['temp_folder_path']) || ! isset($ret['target_zip_path'])) {            
            return $ret;
        }
        
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Archiving user export files..', 'prime-mover'), 'export' );       
        if (! $this->getSystemFunctions()->nonCachedFileExists($source) || ! $this->getSystemFunctions()->nonCachedFileExists($user_export_filepath)) {
            return $ret;
        }        
        $user_files = [$source, $user_export_filepath];
        if (!empty($ret['userfiles_to_tar'])) {
            $user_files = $ret['userfiles_to_tar'];
        }
        $file_position = 0;
        if (!empty($ret['tar_add_file_offset'])) {
            $file_position = $ret['tar_add_file_offset'];
            unset($ret['tar_add_file_offset']);
        }
        foreach ($user_files as $k => $user_file) {
            $user_localname = trailingslashit(basename($tmp_folderpath)) . basename($user_file);
            $ret = apply_filters('prime_mover_add_file_to_tar_archive', $ret, $ret['target_zip_path'], 'ab', $user_file, $user_localname, $start_time, $file_position, $blogid_to_export, true, false);
            if (!empty($ret['tar_add_file_offset'])) {
                $ret['userfiles_to_tar'] = $user_files;
                return $ret;
            }
            if (!empty($ret['error'])) {
                return $ret;
            }
            $file_position = 0;
            unset($user_files[$k]);
        }        
        return $ret;   
    }
        
    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->getUserUtilities()->getUserFunctions()->getCliArchiver()->getProgressHandlers();
    }
    
    /**
     * Export site users
     * @param number $offset
     * @param number $blog_id
     * @param array $ret
     * @param number $start_time
     * @return void|boolean
     */
    public function exportSiteUsers($offset = 0, $blog_id = 0, $ret = [], $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }           
        $users_exported = 0;
        if (isset($ret['users_exported'])) {
            $users_exported = $ret['users_exported'];
        }
        
        $users_export_progress = '';
        if ($users_exported) {
            $users_export_progress = sprintf(esc_html__('%d completed', 'prime-mover'), $users_exported);            
        }
        
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Exporting users.. %s', 'prime-mover'), $users_export_progress), 'export' );
        $left_off = 0;
        if (isset($ret['users_export_leftoff'])) {
            $left_off = $ret['users_export_leftoff'];
        }
        
        while ($site_users = $this->getSiteUsers($offset, $blog_id, $left_off)) {            
            if (empty($site_users)) {
                break;                
            } else {
                $users_written = $this->writeUsersToJson($site_users, $ret, $blog_id);
                $users_exported = $users_exported + $users_written;                
                $ret['users_exported'] = $users_exported;                
            }
            
            $offset = $offset + 5;    
            $left_off = $this->getUserUtilities()->getUserFunctions()->getLeftOff($site_users);
            
            $this->getUserUtilities()->getUserFunctions()->getUserQueries()->maybeEnableUserImportExportTestMode(16, false);
            $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
            if (microtime(true) - $start_time > $retry_timeout) {
                
                do_action('prime_mover_log_processed_events', "$retry_timeout seconds time out on users export to resume on offset $offset" , $blog_id, 'import', __FUNCTION__, $this);
                $ret['users_export_query_offset'] = $offset;       
                $ret['users_export_leftoff'] = $left_off;
                
                return $ret;                
            }            
        }
        
        return $this->doCleanOffRet($ret, $blog_id);
    }
    
    /**
     * Clean off return array
     * @param array $ret
     * @param number $blog_id
     * @return array
     */
    protected function doCleanOffRet($ret = [], $blog_id = 0)
    {
        if (isset($ret['users_export_query_offset'])) {
            unset($ret['users_export_query_offset']);
        }
        
        if (isset($ret['users_export_leftoff'])) {
            unset($ret['users_export_leftoff']);
        }
        
        if (isset($ret['users_exported']) && $ret['users_exported']) {
            do_action('prime_mover_log_processed_events', "Done user export executing prime_mover_after_user_export hook." , $blog_id, 'import', __FUNCTION__, $this);
            do_action('prime_mover_after_user_export', $ret);
        }
        
        if (isset($ret['users_exported'])) {
            unset($ret['users_exported']);
        }
        
        do_action('prime_mover_log_processed_events', "Done user export, exiting method." , $blog_id, 'import', __FUNCTION__, $this);
        return $ret;
    }
    
    /**
     * Get site users
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUsers::itGetsSiteUsers() 
     * @param number $offset
     * @param number $blog_id
     * @param number $left_off
     * @return void|array
     */
    public function getSiteUsers($offset = 0, $blog_id = 0, $left_off = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return [];
        }
        
        $args = [
            'number' => 5,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC'
        ];        
        
        if (is_multisite()) {
            $args['blog_id'] = $blog_id;
        }
        
        return $this->getUsers($args, $left_off);
    }   
    
    /**
     * Helper method for WordPress get_users function
     * @param array $args
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUsers::itGetsSiteUsers()
     */
    protected function getUsers($args = [], $left_off = 0)
    {
        remove_all_actions('pre_user_query');
        add_action('pre_user_query', [$this, 'queryUserBySeekMethod']);
        $this->setIsExportingUser(true);
      
        $this->setLeftOff($left_off);
        $users = get_users($args);
        $this->setIsExportingUser(false);
        
        remove_action('pre_user_query', [$this, 'queryUserBySeekMethod']);
      
        return $users;
    }
    
    /**
     * Query users using seek method
     * This is always faster than native calls.
     * @param WP_User_Query $wpuserquery_obj
     */
    public function queryUserBySeekMethod(WP_User_Query $wpuserquery_obj)
    {
        $left_off = $this->getLeftOff();
        $this->getUserUtilities()->getUserFunctions()->getUserQueries()->queryUserBySeekMethod($wpuserquery_obj, $left_off);
    }
    
    /**
     * Write user to json and return number of users written
     * @param array $site_users
     * @param array $ret
     * @param number $blog_id
     * @return number
     */
    public function writeUsersToJson($site_users = [], $ret = [], $blog_id = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return 0;
        }
        if ( ! is_array($ret) ) {
            return 0;
        }
        if ( ! isset($ret['temp_folder_path']) ) {
            return 0;
        }
        $tmp_folderpath = $ret['temp_folder_path']; 
        $user_export_filepath = $this->getUserUtilities()->returnPathToUserExportFile($tmp_folderpath);
        $count = 0;
        foreach ($site_users as $user) {            
            $result = $this->getUserUtilities()->writeUserToJson($user->data, $user_export_filepath, $ret, $blog_id);
            if ($result !== false) {
                $count++;
            }
        } 
        return $count;
    }
    
    /**
     * Import site users
     * @param array $ret
     * @param number $blog_id
     * @param number $start_time
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUsers::itImportSiteUsers()
     */
    public function importSiteUsers($ret = [], $blog_id = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        if ( ! is_array($ret) ) {
            return $ret;
        }
        
        return $this->getUserUtilities()->processUserImport($ret, $blog_id, $start_time);        
    }
    
    /**
     * Checks if user export is disabled in wp-config.php
     * Returns TRUE if disabled, otherwise FALSE
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUsers::itChecksUserConfigDisabled() 
     */
    public function isExportUserDisabledInConfig()
    {        
        return (defined('PRIME_MOVER_DONT_EXPORT_USER') && true === PRIME_MOVER_DONT_EXPORT_USER);
    }
    
    /**
     * Check if maybe we need to export users
     * @param array $ret
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverUsers::itMaybeExportUsers() 
     */
    public function maybeExportUsers($ret = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }
        
        if ($this->isExportUserDisabledInConfig()) {
            return false;
        }
        
        if (false === apply_filters('prime_mover_is_loggedin_customer', false)) {
            return true;
        }
      
        $original_blogid = 0;
        
        if (is_multisite() && ! empty($ret['original_blogid'])) {
            $original_blogid = $ret['original_blogid'];
        }
        
        if ($original_blogid && false === apply_filters('prime_mover_multisite_blog_is_licensed', false, $original_blogid)) {  
            return true;
        }
        
        if ( ! isset($ret['prime_mover_userexport_setting']) ) {
            return false;
        }
        
        return ('true' === $ret['prime_mover_userexport_setting']);      
    }
}