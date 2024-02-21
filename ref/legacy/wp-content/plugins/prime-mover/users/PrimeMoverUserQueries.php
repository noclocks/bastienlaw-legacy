<?php
namespace Codexonics\PrimeMoverFramework\users;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Codexonics\PrimeMoverFramework\cli\PrimeMoverCLIArchive;
use WP_User_Query;
use wpdb;
use SplFixedArray;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover User Queries
 * Provides very basic user queries for user import and export processes.
 * Both single-site and multisite compatible
 */
class PrimeMoverUserQueries
{         
    private $cli_archiver;
    
    const PRIME_MOVER_USER_TAXONOMY_OPTION = 'prime_mover_user_taxonomy';
   
    /**
     * Constructor
     * @param PrimeMoverCLIArchive $cli_archiver
     */
    public function __construct(PrimeMoverCLIArchive $cli_archiver)
    {
        $this->cli_archiver = $cli_archiver;        
    }
    
    /**
     * Query user by seek method
     * @param WP_User_Query $wpuserquery_obj
     * @param number $left_off
     */
    public function queryUserBySeekMethod(WP_User_Query $wpuserquery_obj, $left_off = 0)
    {        
        $left_off = (int)$left_off;
        if ($left_off) {
            $wpuserquery_obj->query_where = str_replace('WHERE 1=1', "WHERE 1=1 AND ID > $left_off", $wpuserquery_obj->query_where); 
        }
        
        $wpuserquery_obj->query_limit = "LIMIT 5";       
    }
    
    /**
     * Get user taxonomies
     * @param number $blog_id
     * @return []
     */
    public function getUserTaxonomies($blog_id = 0)
    {        
        return get_option(self::PRIME_MOVER_USER_TAXONOMY_OPTION);
    }
    
    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->getCliArchiver()->getProgressHandlers();
    }
    
    /**
     * Update user taxonomy
     * @param array $ret
     * @param number $blog_id
     * @param number $start_time
     * @return array
     */
    public function updateUserTaxonomy($ret = [], $blog_id = 0, $start_time = 0)
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized() || ! $blog_id ) {
            return $ret;
        }
        
        if (! isset($ret['user_equivalence'])) {
            return $ret;
        }
        $user_equivalence = $ret['user_equivalence'];    
        $this->getSystemFunctions()->switchToBlog($blog_id);                
        list($ret, $taxonomies) = $this->getUsersTaxonomy($ret, $blog_id);
        
        if ( ! is_array($taxonomies) || empty($taxonomies)) {
            $this->getSystemFunctions()->restoreCurrentBlog();
            do_action('prime_mover_log_processed_events', 'No user taxonomies, skipping..', $blog_id, 'import', __FUNCTION__, $this);
            return $ret;
        }
        
        do_action('prime_mover_log_processed_events', 'User taxonomies for import: ', $blog_id, 'import', __FUNCTION__, $this);
        do_action('prime_mover_log_processed_events', $taxonomies, $blog_id, 'import', __FUNCTION__, $this);
        
        list($ret, $updated_count_array, $updated_count) = $this->doUpdatedCount($ret);       
        $this->doProgressUserTaxonomyImport($updated_count);        
        foreach ($taxonomies as $k => $taxonomy) {         
            
            list($ret, $offset) = $this->getUserTaxMainOffset($ret);
            list($ret, $processed_object_ids) = $this->getProcessedObjectIds($ret);
            
            while ($term_taxonomy_id = $this->getTermTaxonomyId($taxonomy, $offset)) {
                if (empty($term_taxonomy_id)) {
                    break;
                } else {                        
                    list($ret, $object_offset) = $this->getObjectOffset($ret);
                    $term_taxonomy_id = reset($term_taxonomy_id);                    
                    while($object_ids = $this->getUsersInTerms($term_taxonomy_id, $object_offset)) {                        
                        if ( ! empty($object_ids)) {
                            foreach ($object_ids as $old_user_id) {                                
                                if ( ! isset($user_equivalence[$old_user_id] ) ) {
                                    continue;
                                }                                
                                list($processed_object_ids, $updated_count_array, $updated_count) = $this->processObjectIds($user_equivalence, $old_user_id, $term_taxonomy_id, $processed_object_ids, $taxonomy, 
                                    $updated_count_array, $updated_count);
                            }                        
                        }
                       
                        $object_offset = $object_offset + 5;                              
                        $this->maybeEnableUserImportExportTestMode(10, false);
                        $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
                        if ( (microtime(true) - $start_time) > $retry_timeout) {
                            /**
                             * In multisite, the restore_current_blog is called on generateUserTaxRetryParameters method
                             */
                            return $this->generateUserTaxRetryParameters($ret, $object_offset, $offset, $taxonomies, $processed_object_ids, $updated_count_array, $retry_timeout, $blog_id);
                        }
                    }
                }               
                $offset = $offset + 1;                   
            }            
            unset($taxonomies[$k]); 
        }        
        
        $ret = $this->cleanUpReturnVariable($ret);       
        $this->getSystemFunctions()->restoreCurrentBlog(); 
        
        return $ret;
    }

    /**
     * Get users taxonomy
     * @param array $ret
     * @param number $blog_id
     * @return array
     */
    protected function getUsersTaxonomy($ret = [], $blog_id = 0)
    {
        $taxonomies = $this->getUserTaxonomies($blog_id);        
        if (isset($ret['users_taxonomy'])) {
            $taxonomies = $ret['users_taxonomy'];
            unset($ret['users_taxonomy']);
        }               
        
        return [$ret, $taxonomies];
    }
    
    /**
     * Get user taxonomy main offset
     * @param array $ret
     * @return array
     */
    protected function getUserTaxMainOffset($ret = [])
    {
        $offset = 0;        
        if (isset($ret['users_tax_main_offset'])) {
            $offset = $ret['users_tax_main_offset'];
            unset($ret['users_tax_main_offset']);
        } 
        
        return [$ret, $offset];
    }
    
    /**
     * Get processed object ids
     * @param array $ret
     * @return array
     */
    protected function getProcessedObjectIds($ret = [])
    {
        $processed_object_ids = [];        
        if (isset($ret['users_processed_object_ids'])) {
            $processed_object_ids = $ret['users_processed_object_ids'];
            unset($ret['users_processed_object_ids']);
        }
        
        return [$ret, $processed_object_ids];
    }
    
    /**
     * Get object offset
     * @param array $ret
     * @return []
     */
    protected function getObjectOffset($ret = [])
    {
        $object_offset = 0;
        if (isset($ret['users_tax_object_offset'])) {
            $object_offset = (int)$ret['users_tax_object_offset'];
            unset($ret['users_tax_object_offset']);
        } 
        
        return [$ret, $object_offset];
    }
    
    /**
     * Process object ids
     * @param array $user_equivalence
     * @param number $old_user_id
     * @param number $term_taxonomy_id
     * @param array $processed_object_ids
     * @param string $taxonomy
     * @param array $updated_count_array
     * @param number $updated_count
     * @return array
     */
    protected function processObjectIds($user_equivalence = [], $old_user_id = 0, $term_taxonomy_id = 0, $processed_object_ids = [], $taxonomy = '', $updated_count_array = [], $updated_count = 0)
    {
        $new_user_id = $user_equivalence[$old_user_id];
        $this->deleteUserTermAssociation($old_user_id, $term_taxonomy_id, $processed_object_ids);
        $processed_object_ids = $this->insertUserTermAssociation($new_user_id, $term_taxonomy_id, $processed_object_ids);
        
        $updated_count_array[$taxonomy] = count($processed_object_ids);
        $updated_count = array_sum($updated_count_array);  
        
        return [$processed_object_ids, $updated_count_array, $updated_count];
    }
    
    /**
     * Do updated count
     * @param array $ret
     * @return array
     */
    protected function doUpdatedCount($ret = [])
    {
        $updated_count_array = [];
        $updated_count = 0;
        
        if (isset($ret['users_processed_user_tax_ids_done'])) {
            $updated_count_array = $ret['users_processed_user_tax_ids_done'];
            $updated_count = array_sum($ret['users_processed_user_tax_ids_done']);
            unset($ret['users_processed_user_tax_ids_done']);
        }   
        
        return [$ret, $updated_count_array, $updated_count];
    }
    
    /**
     * Do progress user taxonomy import
     * @param number $updated_count
     */
    protected function doProgressUserTaxonomyImport($updated_count = 0)
    {
        $update_usertax_progress = '';
        if ($updated_count) {
            $update_usertax_progress = sprintf(esc_html__('%d completed', 'prime-mover'), $updated_count);
        }
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Importing user taxonomies.. %s', 'prime-mover'), $update_usertax_progress), 'import' );
    }
    
    /**
     * Generate user taxonomy parameters
     * @param array $ret
     * @param number $object_offset
     * @param number $offset
     * @param array $taxonomies
     * @param array $processed_object_ids
     * @param array $updated_count_array
     * @param number $retry_timeout
     * @param number $blog_id
     * @return array
     */
    protected function generateUserTaxRetryParameters($ret = [], $object_offset = 0, $offset = 0, $taxonomies = [], $processed_object_ids = [], $updated_count_array = [], $retry_timeout = 0, $blog_id = 0)
    {
        $ret['users_tax_object_offset'] = $object_offset;
        $ret['users_tax_main_offset'] = $offset;
        $ret['users_taxonomy'] = $taxonomies;
        
        $ret['users_processed_object_ids'] = $processed_object_ids;
        $ret['users_processed_user_tax_ids_done'] = $updated_count_array;
        $this->getSystemFunctions()->restoreCurrentBlog();
        do_action('prime_mover_log_processed_events', "$retry_timeout seconds time out on updating user taxonomy" , $blog_id, 'import', __FUNCTION__, $this);
        
        return $ret;
    }
    
    /**
     * Clean up variable
     * @param array $ret
     * @return array
     */
    protected function cleanUpReturnVariable($ret = [])
    {
        if (isset($ret['users_tax_object_offset'])) {
            unset($ret['users_tax_object_offset']);
        }
        
        if (isset($ret['users_tax_main_offset'])) {
            unset($ret['users_tax_main_offset']);
        }
        
        if (isset($ret['users_taxonomy'])) {
            unset($ret['users_taxonomy']);
        }
        
        if (isset($ret['users_processed_object_ids'])) {
            unset($ret['users_processed_object_ids']);
        }
        
        if (isset($ret['users_processed_user_tax_ids_done'])) {
            unset($ret['users_processed_user_tax_ids_done']);
        }
        
        return $ret;
    }
    
    /**
     * Insert new term association and track results
     * @param number $object_id
     * @param number $term_taxonomy_id
     * @param array $processed_object_ids
     * @return array
     */
    protected function insertUserTermAssociation($object_id = 0, $term_taxonomy_id = 0, $processed_object_ids = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $processed_object_ids;
        }        
        $object_id = (int)$object_id;
        $term_taxonomy_id = (int)$term_taxonomy_id;
        if ( ! $term_taxonomy_id || ! $object_id ) {
            return $processed_object_ids;
        }
        $result = false;
        global $wpdb;
        
        $data = ['object_id' => $object_id, 'term_taxonomy_id' => $term_taxonomy_id];
        $format = ['%d','%d'];
        
        $exist_query = "SELECT object_id FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id = %d";
        $exist_prepared = $wpdb->prepare($exist_query, $object_id, $term_taxonomy_id);
        $exist_call = $wpdb->get_var($exist_prepared);
        
        if ( $exist_call ) {            
            $processed_object_ids[$object_id] = $term_taxonomy_id;
            
        } else {
            $result = $wpdb->insert($wpdb->term_relationships, $data, $format);
        }
        
        if ($result) {
            $processed_object_ids[$object_id] = $term_taxonomy_id;
            
        }        
        return $processed_object_ids;
    }
    
    /**
     * Delete user term association
     * @param number $object_id
     * @param number $term_taxonomy_id
     * @param array $processed_object_ids
     * @return boolean|number|false
     */
    protected function deleteUserTermAssociation($object_id = 0, $term_taxonomy_id = 0, $processed_object_ids = [])
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }
        
        $term_taxonomy_id = (int)$term_taxonomy_id;
        $object_id = (int)$object_id;
        
        if ( ! $term_taxonomy_id || ! $object_id ) {
            return false;
        }
       
        if ( ! $this->maybeDeleteTermAssociation($processed_object_ids, $object_id, $term_taxonomy_id) ) {            
            return false;
        }        
        
        global $wpdb;        
        return $wpdb->delete($wpdb->term_relationships, ['object_id' => $object_id, 'term_taxonomy_id' => $term_taxonomy_id], ['%d', '%d']);
    }
    
    /**
     * Maybe delete term association
     * @param array $processed_object_ids
     * @param number $given_object_id
     * @param number $given_term_taxonomy_id
     * @return boolean
     */
    protected function maybeDeleteTermAssociation($processed_object_ids = [], $given_object_id = 0, $given_term_taxonomy_id = 0)
    {
        $associated_term_tax_id = 0;
        if (isset($processed_object_ids[$given_object_id]) && $given_term_taxonomy_id) {            
            $associated_term_tax_id = $processed_object_ids[$given_object_id];            
        }
        if ($associated_term_tax_id && $associated_term_tax_id === $given_term_taxonomy_id) {            
            return false;
        }
        return true;        
    }
    
    /**
     * Get terms wrapper, multisite global compatible
     * @param string $taxonomy
     * @param number $offset
     * @return []
     */
    protected function getTermTaxonomyId($taxonomy = '', $offset = 0)
    {        
        global $wpdb;
        
        $query = "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s ORDER BY term_taxonomy_id ASC LIMIT %d, 1";
        $prepared = $wpdb->prepare($query, $taxonomy, $offset);
        $results = $wpdb->get_results($prepared, ARRAY_A);        

        return wp_list_pluck($results, 'term_taxonomy_id');      
    }   
    
    /**
     * Get users in terms
     * @param number $term_taxonomy_id
     * @param number $offset
     * @return []
     */
    protected function getUsersInTerms($term_taxonomy_id = 0, $offset = 0)
    {
        global $wpdb;
        
        $query = "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d ORDER BY object_id ASC LIMIT %d, 5";
        $prepared = $wpdb->prepare($query, $term_taxonomy_id, $offset);
        $results = $wpdb->get_results($prepared, ARRAY_A);        
        
        return wp_list_pluck($results, 'object_id'); 
    }
    
    /**
     * Get system authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     */
    public function getSystemAuthorization()
    {
        return $this->getCliArchiver()->getSystemAuthorization();
    }    
    
    /**
     * Get system initialization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization
     */
    public function getSystemInitialization()
    {
        return $this->getCliArchiver()->getSystemInitialization();
    }
    
    /**
     * Get system functions
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemFunctions
     */
    public function getSystemFunctions()
    {
        return $this->getCliArchiver()->getSystemFunctions();
    }
    
    /**
     * Get cli archiver
     * @return \Codexonics\PrimeMoverFramework\cli\PrimeMoverCLIArchive
     */
    public function getCliArchiver()
    {
        return $this->cli_archiver;
    } 
    
    /**
     * Set user taxonomy so it can be exported
     * @param boolean $single_site
     */
    public function maybeSaveUserTaxonomy()
    {  
        if (wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        
        $multisite = false;
        $current_blog_id = 0;
        if (is_multisite()) {
            $multisite = true;
            $current_blog_id = get_current_blog_id();
        }
        
        if (!$current_blog_id && $multisite) {
            return;
        }    
       
        $this->getSystemFunctions()->switchToBlog($current_blog_id); 
        if (!$this->getSystemInitialization()->isAdministrator(false)) {
            $this->getSystemFunctions()->restoreCurrentBlog();
            return;
        }             
                        
        $taxonomies = get_object_taxonomies('user');
        if ( !is_array($taxonomies) || empty($taxonomies)) {
            delete_option(self::PRIME_MOVER_USER_TAXONOMY_OPTION);
            $this->getSystemFunctions()->restoreCurrentBlog();
            return;
        }
        
        $this->getSystemFunctions()->updateBlogOption($current_blog_id, self::PRIME_MOVER_USER_TAXONOMY_OPTION, $taxonomies, false);        
        do_action('prime_mover_do_anything_on_wp_loaded');
        
        $this->getSystemFunctions()->restoreCurrentBlog();
    }    
        
    /**
     * Maybe enable user import test mode
     * @param number $sleep
     * @param boolean $microsleep
     */
    public function maybeEnableUserImportExportTestMode($sleep = 0, $microsleep = false)
    {
        if ( ! defined('PRIME_MOVER_USERIMPORTEXPORT_TEST_MODE') ) {
            return;
        }
        if (  true === PRIME_MOVER_USERIMPORTEXPORT_TEST_MODE ) {
            $this->getSystemInitialization()->setProcessingDelay($sleep, $microsleep);
        }        
    }
    
    /**
     * Bail out and return $ret array
     * @param array $ret
     * @param string $leftoff_identifier
     * @param string $update_variable
     * @param boolean $last_processor
     * @param string $handle_unique_constraint
     * @param string $table
     * @param object $wpdb
     * @param boolean $non_user_adjustment
     * @return array
     */
    private function bailOutAndReturnRetArray($ret = [], $leftoff_identifier = '', $update_variable = '', $last_processor = false, 
        $handle_unique_constraint = '', $table = '', $wpdb = null, $non_user_adjustment = false)
    {       
        $ret = $this->cleanUpRetArrayAfterCustomerIdProcessing($ret, $leftoff_identifier, $update_variable, $last_processor,
            $handle_unique_constraint, $table, $wpdb, $non_user_adjustment);
        
        $this->getSystemFunctions()->restoreCurrentBlog();
        
        return $ret;
    }
    
    /**
     * dB Update Helper
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
        global $wpdb;        
        $this->getSystemFunctions()->switchToBlog($blogid_to_import);
        
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {  
            return $this->bailOutAndReturnRetArray($ret, $leftoff_identifier, $update_variable, $last_processor, $handle_unique_constraint, $table, $wpdb, $non_user_adjustment);
        }
        
        $wpdb->flush();
        if ($handle_unique_constraint) {
            $this->dropIndexesConstraint($table, $wpdb, $handle_unique_constraint);
        }
        
        $table_exists = false;
        if ($wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}{$table}';")) {
            $table_exists = true;
        }
        
        if (!$table_exists) {
            do_action('prime_mover_log_processed_events', "$table TABLE DOES NOT EXIST - SKIPPING THIS USER ADJUSTMENT TABLE PROCESSING." , $blogid_to_import, 'import', __FUNCTION__, $this);
            return $this->bailOutAndReturnRetArray($ret, $leftoff_identifier, $update_variable, $last_processor, $handle_unique_constraint, $table, $wpdb, $non_user_adjustment);
        }
        
        $query = $this->seekCustomersToUpdateQuery($ret, $leftoff_identifier, $table, $primary_index, $column_strings, $non_user_adjustment, $filter_clause);
        if (!$query) {
            return $this->bailOutAndReturnRetArray($ret, $leftoff_identifier, $update_variable, $last_processor, $handle_unique_constraint, $table, $wpdb, $non_user_adjustment);
        }
        
        $customers_updated = 0;
        if (isset($ret[$update_variable])) {
            $customers_updated = $ret[$update_variable];
        }
        
        $update_customers_progress = '';
        if ($customers_updated) {
            $update_customers_progress = sprintf(esc_html__('%d completed', 'prime-mover'), $customers_updated);
        }
        
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Updating %s.. %s', 'prime-mover'), $progress_identifier, $update_customers_progress), 'import' );
        $user_equivalence = $ret['user_equivalence'];
        
        $format = ARRAY_A;
        if ($non_user_adjustment) {
            $format = OBJECT;
        }
        
        while ($results = $wpdb->get_results($query, $format)) {
            if (empty($results)) {
                break;
            } else {
                $ret = $this->updateCustomerIds($results, $user_equivalence, $ret, $update_variable, $column_strings, $table, $leftoff_identifier, $non_user_adjustment, $filter_clause);
            }
            
            $query = $this->seekCustomersToUpdateQuery($ret, $leftoff_identifier, $table, $primary_index, $column_strings, $non_user_adjustment, $filter_clause);
            $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
            if ((microtime(true) - $start_time) > $retry_timeout) {
                $this->getSystemFunctions()->restoreCurrentBlog();                
                do_action('prime_mover_log_processed_events', "$retry_timeout seconds time out on updating {$progress_identifier}" , $blogid_to_import, 'import', __FUNCTION__, $this);
                
                $ret['prime_mover_thirdparty_processing_retry'] = true;
                return $ret;
            }
        }
        
        return $this->bailOutAndReturnRetArray($ret, $leftoff_identifier, $update_variable, $last_processor, $handle_unique_constraint, $table, $wpdb, $non_user_adjustment);
    }
    
    /**
     * Drop indexes constraint
     * Must be done on switched tables
     * @param string $table
     * @param wpdb $wpdb
     * @param string $index
     */
    protected function dropIndexesConstraint($table = '', wpdb $wpdb = null, $index = '')
    {           
        if (1 === $this->indexExists($table, $wpdb, $index)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}{$table} DROP INDEX {$index}");
        }        
    }

    /**
     * Check if index key exists before dropping and adding the constraint
     * @param string $table
     * @param wpdb $wpdb
     * @param string $index
     * @return number|boolean
     */
    protected function indexExists($table = '', wpdb $wpdb = null, $index = '')
    {
        $res = $wpdb->query($wpdb->prepare("SHOW KEYS FROM {$wpdb->prefix}{$table} WHERE Key_name=%s", $index));
        return $res;
    }
    
    /**
     * Query customers to update using MySQL seek method for faster performance
     * @param array $ret
     * @param string $leftoff_identifier
     * @param string $table
     * @param string $primary_index
     * @param string $column_strings
     * @param boolean $non_user_adjustment
     * @param array $filter_clause
     * @return string
     */
    protected function seekCustomersToUpdateQuery($ret = [], $leftoff_identifier = '', $table = '', $primary_index = '', $column_strings = '', $non_user_adjustment = false, $filter_clause = [])
    {
        $query = '';
        if ($non_user_adjustment) {
            $query = apply_filters('prime_mover_non_user_adjustment_select_query', '', $ret, $leftoff_identifier, $table, $primary_index, $column_strings);            
        } 
        
        if ($query) {
            return $query;
        }
                
        global $wpdb;
        $user_id_column = $this->parsePrimaryIndexUserColumns($column_strings, 'user');
        $where = "WHERE {$user_id_column} IS NOT NULL";
        
        $left_off = 0;
        if (isset($ret[$leftoff_identifier])) {
            $left_off = $ret[$leftoff_identifier];
        }
        
        if ($left_off) {
            $where .= $wpdb->prepare(" AND {$primary_index} < %d", $left_off);
        }
        
        if (is_array($filter_clause) && isset($filter_clause['where_clause']) && is_array($filter_clause['where_clause']) && !empty($filter_clause['where_clause'])) {
            $where_clause = $filter_clause['where_clause'];
            $where .= " AND (";
            foreach ($where_clause as $clause) {
                if (is_array($clause) && !empty($clause['field']) && !empty($clause['value'])) {
                    $field = $clause['field'];
                    $value = $clause['value'];
                    $condition = '';
                    if (!empty($clause['condition']) && in_array($clause['condition'], ['OR', 'AND'], true)) {
                        $condition = $clause['condition'];
                    }
                    $where .= $wpdb->prepare("{$field} = %s", $value);
                    if ($condition) {
                        $where .= " {$condition} ";
                    }
                }
            }
            $where .= " )";
        }
        
        $orderby = $wpdb->prepare("ORDER BY {$primary_index} DESC LIMIT %d", PRIME_MOVER_CUSTOMER_LOOKUP_LIMIT);            
        return "SELECT {$column_strings} FROM {$wpdb->prefix}{$table} {$where} {$orderby}";                
    } 
    
    /**
     * Parse primary index user columns
     * @param string $column_strings
     * @param string $res_mode
     * @return array
     */
    public function parsePrimaryIndexUserColumns($column_strings = '', $res_mode = 'array')
    {
        $column_names = array_map('trim', explode("," , $column_strings));
        list($primary_index, $user_id_column) = $column_names;
                
        if ('pri' === $res_mode) {
            return $primary_index;
        }
        
        if ('user' === $res_mode) {
            return $user_id_column;
        }
        
        return $column_names;        
    }
    
    /**
     * Update customer IDs to migrated user IDs
     * @param array $results
     * @param SplFixedArray $user_equivalence
     * @param array $ret
     * @param string $update_variable
     * @param string $column_strings
     * @param string $table
     * @param string $leftoff_identifier
     * @param boolean $non_user_adjustment
     * @param array $filter_clause
     * @return array
     */
    protected function updateCustomerIds($results = [], SplFixedArray $user_equivalence = null, $ret = [], $update_variable = '', $column_strings = '',
        $table = '', $leftoff_identifier = '', $non_user_adjustment = false, $filter_clause = [])
    {
        if (empty($results)) {
            return;
        }
        
        $customers_updated = 0;
        if (isset($ret[$update_variable])) {
            $customers_updated = (int)$ret[$update_variable];
        }
        
        list($primary_index, $user_id_column) = $this->parsePrimaryIndexUserColumns($column_strings);        
        $primary_index_id = 0;
        $processing_serialized = $this->isSerialized($filter_clause);
                
        foreach ($results as $result) {
            
            $this->maybeEnableUserImportExportTestMode(PRIME_MOVER_USER_ADJUSTMENT_TEST_DELAY, false);            
            if ($non_user_adjustment) {
                                
                if (!is_object($result)) {
                    continue;
                }
                                
                if (!isset($result->{$primary_index}) || !isset($result->{$user_id_column})) {
                    continue;
                }
                
                $primary_index_id = $result->{$primary_index};
                $value = $result->{$user_id_column};
                
                if (!$primary_index_id || !$value) {
                    continue;
                }                
                $primary_index_id = apply_filters('prime_mover_non_user_adjustment_update_data', $primary_index_id, $value, $table, $primary_index, $user_id_column, $leftoff_identifier, $ret, $filter_clause);                
                
            } else {
                
                if (!isset($result[$primary_index]) || !isset($result[$user_id_column])) {
                    continue;
                }
                
                $primary_index_id = (int)$result[$primary_index];
                if (!$primary_index_id) {
                    continue;
                }
                
                $migrated_user_id = 0;
                if ($processing_serialized) {
                    $migrated_user_id = apply_filters('prime_mover_process_serialized_data_user_adjustment', $migrated_user_id , $result, $user_equivalence, $update_variable);     
                    
                } else {                 
                    $user_id = (int)$result[$user_id_column];
                    if (!$user_id) {
                        continue;
                    }
                    
                    if (!isset($user_equivalence[$user_id])) {
                        continue;
                    }
                    
                    $migrated_user_id = (int)$user_equivalence[$user_id];
                    if (!$migrated_user_id) {
                        continue;
                    }
                    
                    if ($migrated_user_id === $user_id) {
                        continue;
                    }                    
                }
                
                if (!$migrated_user_id) {
                    continue;
                }
               
                $primary_index_id = $this->updateCustomerUserIdBySQL($primary_index_id, $migrated_user_id, $table, $primary_index, $user_id_column, '', $processing_serialized);                
            }   
            
            if (!is_wp_error($primary_index_id) || !$primary_index_id) {
                $customers_updated++;
            }
        }
        
        if ($primary_index_id) {            
            $ret[$leftoff_identifier] = $primary_index_id;
        }
        
        $ret[$update_variable] = $customers_updated;        
        return $ret;
    }

    /**
     * Checks if the callback is meant to process serialized data
     * This does not check if data is actually serialized.
     * @param array $filter_clause
     * @return boolean
     */
    protected function isSerialized($filter_clause = [])
    {
        return (isset($filter_clause['is_serialized']) && true === $filter_clause['is_serialized']);
    }
    
    /**
     * Update migrated customer user ID via SQL for best performance
     * @param number $primary_index_id
     * @param number $migrated_user_id
     * @param string $table
     * @param string $primary_index
     * @param string $user_id_column
     * @param string $query
     * @param boolean $is_serialized
     * @return \WP_Error|number
     */
    public function updateCustomerUserIdBySQL($primary_index_id = 0, $migrated_user_id = 0, $table = '', $primary_index = '', $user_id_column = '', $query = '', $is_serialized = false)
    {
        
        global $wpdb; 
        if (!$query && $is_serialized && is_string($migrated_user_id)) {            
            $query = $wpdb->prepare("
                   UPDATE {$wpdb->prefix}{$table}
                   SET {$user_id_column} = %s
                   WHERE {$primary_index} = %d",
                   $migrated_user_id, $primary_index_id
            );  
            
        } 
        
        if (!$query) {            
            $query = $wpdb->prepare("
                   UPDATE {$wpdb->prefix}{$table}
                   SET {$user_id_column} = %d
                   WHERE {$primary_index} = %d",
                   $migrated_user_id, $primary_index_id
            ); 
        }        
        
        $res = $wpdb->query($query);
        if (false === $res) {
            return new WP_Error('update_author_error', esc_html__( 'Error updating author', 'prime-mover'));
        }
        
        return $primary_index_id;
    }
    
    /**
     * Cleanup $ret array after processing.
     * @param array $ret
     * @param string $leftoff_identifier
     * @param string $update_variable
     * @param boolean $last_processor
     * @param string $handle_unique_constraint
     * @param string $table
     * @param wpdb $wpdb
     * @param boolean $non_user_adjustment
     * @return array
     */
    protected function cleanUpRetArrayAfterCustomerIdProcessing($ret = [], $leftoff_identifier = '', $update_variable = '',
        $last_processor = false, $handle_unique_constraint = '', $table = '', wpdb $wpdb = null, $non_user_adjustment = false)
    {
        if (isset($ret[$leftoff_identifier])) {
            unset($ret[$leftoff_identifier]);
        }
        
        if (isset($ret[$update_variable])) {
            unset($ret[$update_variable]);
        }
        
        if (isset($ret['3rdparty_current_function'])) {
            unset($ret['3rdparty_current_function']);
        }
        
        if (isset($ret['prime_mover_thirdparty_processing_retry']) && $last_processor) {
            unset($ret['prime_mover_thirdparty_processing_retry']);
        }
        
        if ($last_processor && false === $non_user_adjustment) {
            $this->invalidateReportCacheAfterMigration();
        }
        
        if ($handle_unique_constraint) {
            $this->enForceIndexesConstraint($table, $wpdb, $handle_unique_constraint);
        }
        
        return $ret;
    }
    
    /**
     * Invalidate WC Analytics report cache after migration
     */
    protected function invalidateReportCacheAfterMigration()
    {
        $transient_name  = 'woocommerce_reports-transient-version';
        $transient_value = (string) time();
        
        set_transient($transient_name, $transient_value);
    }
    
    /**
     * Add indexes constraint
     * Must be done on switched tables
     * @param string $table
     * @param wpdb $wpdb
     * @param string $index
     */
    protected function enForceIndexesConstraint($table = '', wpdb $wpdb = null, $index = '')
    {
        if (0 === $this->indexExists($table, $wpdb, $index)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}{$table} ADD CONSTRAINT {$index} UNIQUE ({$index})");
        }        
    }
    
    /**
     * Do flexible validation, if requisites not meet this returns $ret array
     * Otherwise return false, which by default enables user equivalence check.
     * IMPORTANT: Call this public method directly if you want to set $user_equivalence_check to FALSE.
     * @param array $ret
     * @param number $blogid_to_import
     * @param array $target_plugin
     * @param boolean $user_equivalence_check
     * @return string|boolean
     */
    public function maybeRequisitesNotMeetForAdjustment($ret = [], $blogid_to_import = 0, $target_plugin = [], $user_equivalence_check = true)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            $ret['error'] = esc_html__('Unauthorized', 'prime-mover');
            return $ret;
        }
        
        $valid_target_plugin = false;
        if (is_string($target_plugin) || is_array($target_plugin)) {
            $valid_target_plugin = true;
        }
        
        if (!$valid_target_plugin) {
            return $ret;
        }
        
        $exists = [];
        if (is_string($target_plugin) && isset($ret['imported_package_footprint']['plugins'][$target_plugin])) {
            $exists[] = $ret['imported_package_footprint']['plugins'][$target_plugin];
        }
        
        if (is_array($target_plugin)) {
            foreach ($target_plugin as $target) {
                if (isset($ret['imported_package_footprint']['plugins'][$target])) {
                    $exists[] = $ret['imported_package_footprint']['plugins'][$target];
                }
            }
        }
        
        if (empty($exists)) {
            return $ret;
        }
        
        if (!isset($ret['user_equivalence']) || !$blogid_to_import) {
            return $ret;
        }
        
        $mismatch_count = 0;
        if (isset($ret['user_mismatch_count'])) {
            $mismatch_count = $ret['user_mismatch_count'];
        }
        
        if (!$mismatch_count && $user_equivalence_check) {
            do_action('prime_mover_log_processed_events', "User equivalence check enabled - but post mismatch count is zero, skipping third party processing user update.", $blogid_to_import, 'import', __FUNCTION__, $this);
            return $ret;
        }
        
        return false;
    }
}
