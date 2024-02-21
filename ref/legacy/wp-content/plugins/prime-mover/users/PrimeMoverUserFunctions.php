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

use SplFixedArray;
use WP_Post;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover User Functions
 * Provides very basic functions for user import and export processes.
 */
class PrimeMoverUserFunctions
{         
    private $user_queries;
    private $is_updating_authors;
    
    const DEFAULT_USER_FILE = 'users.json';
    const ENCRYPTED_USER_FILE = 'users.json.enc';
    const SPECIAL_USER_META_FILE = 'usersmeta.json';
    
    /**
     * Constructor
     * @param PrimeMoverUserQueries $user_queries
     */
    public function __construct(PrimeMoverUserQueries $user_queries)
    {  
        $this->user_queries = $user_queries;
        $this->is_updating_authors = false;
    }
    
    /**
     * Get user queries
     * @return \Codexonics\PrimeMoverFramework\users\PrimeMoverUserQueries
     */
    public function getUserQueries()
    {
        return $this->user_queries;
    }
    
    /**
     * Set if updating authors
     * @param boolean $is_updating_authors
     */
    public function setIsUpdatingAuthors($is_updating_authors = false)
    {
        $this->is_updating_authors = $is_updating_authors;
    }
    
    /**
     * Is updating authors?
     * @return boolean
     */
    public function getIsUpdatingAuthors()
    {
        return $this->is_updating_authors;
    }
    
    /**
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->getUserQueries()->getCliArchiver()->getProgressHandlers();
    }
    
    /**
     * Get left off of the last user exported
     * @param array $site_users
     * @return number
     */
    public function getLeftOff($site_users = [])
    {
        $left_off = 0;
        if (!is_array($site_users) || empty($site_users)) {
            return $left_off;
        }
        
        $last_user = array_pop($site_users);
        if (!isset($last_user->ID)) {
            return $left_off;
        }
        
        return $last_user->ID;
    }
    
    /**
     * Update user taxonomy
     * @param array $ret
     * @param number $blog_id
     * @param number $start_time
     * @return array|number
     */
    public function updateUserTaxonomy($ret = [], $blog_id = 0, $start_time = 0)
    {
        return $this->getUserQueries()->updateUserTaxonomy($ret, $blog_id, $start_time);
    }
    
    /**
     * Get special user meta keys from json file
     * @param array $ret
     * @param number $blogid_to_import
     * @return array
     */
    public function getSpecialUserMetaKeysFromJsonFile($ret = [], $blogid_to_import = 0)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        global $wp_filesystem;
        if (empty($ret['unzipped_directory'])) {
            return $ret;
        }
        $unzipped_dir = $ret['unzipped_directory'];
        $path = $this->getSpecialMetaKeysImportFile($unzipped_dir);
        if ( ! $path) {  
            do_action('prime_mover_log_processed_events', "Special meta keys path does not exist, skipping..", $blogid_to_import, 'import', __FUNCTION__, $this);
            return $ret;            
        }
        $this->getProgressHandlers()->updateTrackerProgress(esc_html__('Preparing user meta keys..', 'prime-mover'));
        $special_user_meta_keys = $wp_filesystem->get_contents($path);
        $special_user_meta_keys = trim($special_user_meta_keys);
        if ( ! $special_user_meta_keys ) {
            do_action('prime_mover_log_processed_events', "No special meta keys path found, skipping..", $blogid_to_import, 'import', __FUNCTION__, $this);
            return $ret;
        }
        $decoded = json_decode($special_user_meta_keys, true); 
        if ( ! is_array($decoded) ) {
            return $ret;
        }
        $ret['usermeta_keys_import_adjust'] = $decoded;
        return $ret;
    }
    
    /**
     * Restore pass
     * @param string $original_pass
     * @param array $ret
     */
    public function restoreOriginalPass($original_pass = '', $ret = [])
    {
        add_filter('wp_pre_insert_user_data', function($data = [], $update = false, $id = null) use ($original_pass, $ret) {
            if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
                return $data;
            }
            if ( ! $original_pass || empty($ret)) {
                return $data;
            }
            if (empty($data['user_email'])) {
                return $data;
            }
            if ($this->isUserCurrentlyLoggedIn($data['user_email'])) {
                return $data;
            }
            if ( ! isset($data['user_pass'] ) ) {
                return $data;
            }
            $data['user_pass'] = $original_pass;
            $import_blog_id = $this->getSystemInitialization()->getImportBlogID();
            
            do_action('prime_mover_log_processed_events', $data['user_email'] . " user pass restored", $import_blog_id, 'import', 'restoreOriginalPass', $this, false, true);
            return $data;
            
        }, 23, 3);
    }
    
    /**
     * Returns true if current logged-in user email matches with the given email
     * @param string $given_email
     * @return boolean
     */
    protected function isUserCurrentlyLoggedIn($given_email = '')
    {
        $current_user = wp_get_current_user();
        return $current_user->user_email === $given_email;        
    }
    
    /**
     * Remove original pass filter
     */
    public function removeOriginalPass()
    {
        remove_all_filters('wp_pre_insert_user_data', 23);
    }
    
    /**
     * Query posts to update using MySQL seek method for faster performance
     * @param array $ret
     * @return string
     */
    protected function seekPostsToUpdateQuery($ret = [])
    {
        global $wpdb;
        $where = '';
        
        $left_off = 0;
        if (isset($ret['post_authors_leftoff'])) {
            $left_off = $ret['post_authors_leftoff'];
        }
        
        if ($left_off) {            
            $where .= $wpdb->prepare("WHERE ID > %d", $left_off);
        }
        
        $orderby = $wpdb->prepare("ORDER BY ID ASC LIMIT %d", PRIME_MOVER_POSTAUTHORS_UPDATE_LIMIT); 
        
        return "SELECT ID, post_author, post_type FROM {$wpdb->posts} {$where} {$orderby}";  
    }
    
    /**
     * Update post authors
     * @param SplFixedArray $user_equivalence
     * @param number $total_post_count
     * @param number $blog_id
     * @param number $start_time
     * @param array $ret
     * @return void|number|boolean
     */
    public function updatePostAuthors(SplFixedArray $user_equivalence, $total_post_count = 0, $blog_id = 0, $start_time = 0, $ret = [])
    {        
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $this->getSystemFunctions()->switchToBlog($blog_id);
        global $wpdb;        
        $wpdb->flush();
             
        $query = $this->seekPostsToUpdateQuery($ret);
        $posts_updated = 0;
        if (isset($ret['posts_updated'])) {
            $posts_updated = $ret['posts_updated'];
        }
        
        $update_authors_progress = '';
        if ($posts_updated) {            
            $update_authors_progress = sprintf(esc_html__('%d completed', 'prime-mover'), $posts_updated);
        }
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Updating authors.. %s', 'prime-mover'), $update_authors_progress), 'import' );        
        while ( $results = $wpdb->get_results($query, ARRAY_A) ) {   
            if (empty($results)) {
                break;                
            } else { 
                $ret = $this->updatePostAuthor($results, $user_equivalence, $ret);             
            }         
            
            $query = $this->seekPostsToUpdateQuery($ret);                          
            $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
            if ( (microtime(true) - $start_time) > $retry_timeout) {
                $this->getSystemFunctions()->restoreCurrentBlog();                
                do_action('prime_mover_log_processed_events', "$retry_timeout seconds time out on updating user authors" , $blog_id, 'import', __FUNCTION__, $this);
               
                return $ret;
            }
        }
        
        if (isset($ret['post_authors_leftoff'])) {
            unset($ret['post_authors_leftoff']);    
        }
        
        $this->getSystemFunctions()->restoreCurrentBlog();
        return $ret;
    }
    
    /**
     * Update post author
     * @param array $results
     * @param SplFixedArray $user_equivalence
     * @param array $ret
     * @return array
     */
    protected function updatePostAuthor($results = [], SplFixedArray $user_equivalence = null, $ret = [])
    {
        if (empty($results)) {
            return;
        }
        $posts_updated = 0;
        if (isset($ret['posts_updated'])) {
            $posts_updated = (int)$ret['posts_updated'];
        }   
        
        $post_id = 0;
        foreach ($results as $result) {
            if (!isset($result['ID']) || !isset($result['post_author']) || !isset($result['post_type'])) {
                continue;
            }
            
            $post_id = (int) $result['ID'];            
            $post_author = (int) $result['post_author'];
            $post_type = $result['post_type'];
            
            if (!$post_id || !$post_author || !$post_type) {
                continue;
            }
            
            if (!isset($user_equivalence[$post_author])) {
                continue;
            }
            
            $new_author = (int)$user_equivalence[$post_author];
            if (!$new_author) {
                continue;
            }
                 
            /**
             * Notes: refer to developer notes for using this action.
             */
            do_action('prime_mover_before_post_author_update', $post_id, $post_author, $new_author, $ret, $user_equivalence, $post_type);
            
            if ($new_author === $post_author) {
                continue;
            }            
            $this->getUserQueries()->maybeEnableUserImportExportTestMode(100000, true);
                        
            $this->doBeforeUpdatingPosts();            
            $post_id = $this->updateAuthorBySQL($post_id, $new_author);
            $this->doAfterUpdatingPosts();
            
            if ( ! is_wp_error($post_id) || ! $post_id ) {
                $posts_updated++;                
            }
        }
        
        if ($post_id) {
            $ret['post_authors_leftoff'] = $post_id;
        }        
        
        $ret['posts_updated'] = $posts_updated;        
        return $ret;
    }

    /**
     * Update author via SQL for best performance
     * We don't need to run any 3rd party hooks as only Prime Mover plugin is activated
     * @param number $post_id
     * @param number $new_author
     * @return \WP_Error|number
     */
    private function updateAuthorBySQL($post_id = 0, $new_author = 0)
    {
        global $wpdb; 
        $prep = $wpdb->prepare("
            UPDATE $wpdb->posts
            SET post_author = %d
            WHERE ID = %d",
            $new_author, $post_id
            );
        
        $res = $wpdb->query($prep);        
        if (false === $res) {
            return new WP_Error('update_author_error', esc_html__( 'Error updating author', 'prime-mover'));
        }        
        return $post_id;
    }
    
    /**
     * Do before updating posts
     */
    protected function doBeforeUpdatingPosts()
    {
        $this->setIsUpdatingAuthors(true);
    }
    
    /**
     * Do after updating posts
     */
    protected function doAfterUpdatingPosts()
    {
        $this->setIsUpdatingAuthors(false);
    }
    
    /**
     * Is page template overwritten?
     * @param string $original_template
     * @param WP_Post $post
     * @return boolean
     */
    protected function isPageTemplateOverwritten($original_template = '', WP_Post $post = null)
    {          
        $overwritten = false;
        $page_templates = wp_get_theme()->get_page_templates($post);            
        if ( 'default' !== $original_template && !isset($page_templates[$original_template])) {
            $overwritten = true;
        }
        
        return $overwritten;        
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
        return $this->getUserQueries()->getCliArchiver();
    }   
    
    /**
     * Get user maximum author ID
     * @return string|NULL
     */
    public function countUserMaxId()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT MAX(post_author) FROM {$wpdb->prefix}posts");
    }
    
    /**
     * Count total posts
     * @return string|NULL
     */
    public function countTotalPosts()
    {
        global $wpdb;
        return $wpdb->get_var("SELECT count(ID) FROM {$wpdb->prefix}posts");
    }
    
    /**
     * add New element to SPLFixedArray
     * @param SplFixedArray $array
     * @param number $index
     * @param number $data
     * @return SplFixedArray
     */
    public function addNewElement(SplFixedArray $array, $index = 0, $data = 0)
    {                
        return $this->getSystemFunctions()->addNewElement($array, $index, $data);
    }
    
    /**
     * Generate user export file name
     * @return string
     */
    public function generateUserExportFileName()
    {        
        if ($this->getSystemInitialization()->getMaybeEncryptExportData()) {
            return self::ENCRYPTED_USER_FILE;
        } else {
            return self::DEFAULT_USER_FILE;
        }
    }
    
    /**
     * Generate user meta export file name
     * @return string
     */
    public function generateUserMetaExportFileName()
    {
        return self::SPECIAL_USER_META_FILE;
    }
    
    /**
     * Get user export 
     * @param string $unzipped_dir
     * @return boolean|boolean[]|string[]
     */
    public function getUsersExportFilePath($unzipped_dir = '')
    {
        global $wp_filesystem;                
        if ( ! $unzipped_dir || ! $wp_filesystem->exists($unzipped_dir)) {
            return false;
        }
        
        $default_file = $unzipped_dir . self::DEFAULT_USER_FILE;
        $encrypted_file = $unzipped_dir . self::ENCRYPTED_USER_FILE;
        
        if ($wp_filesystem->exists($default_file)) {
            return [$default_file, false];
        }
        
        if ($wp_filesystem->exists($encrypted_file)) {
            return [$encrypted_file, true];
        }
        
        return false;
    }
    
    /**
     * Get special meta keys import file
     * @param string $unzipped_dir
     * @return boolean|string
     */
    public function getSpecialMetaKeysImportFile($unzipped_dir = '')
    {
        global $wp_filesystem;
        if ( ! $unzipped_dir || ! $wp_filesystem->exists($unzipped_dir)) {
            return false;
        }
        
        $usermeta_file = $unzipped_dir . self::SPECIAL_USER_META_FILE;        
        if ($this->getSystemFunctions()->nonCachedFileExists($usermeta_file)) {
            return $usermeta_file;
        }        
        return false;
    }
    
    /**
     * Gets affected user meta keys on a database table matching the "given_db_prefix"
     * @param string $given_db_prefix
     * @param number $blog_id
     * @return array
     * @mainsitesupport_affected
     */
    public function getAffectedUserMetaGivenPrefix($given_db_prefix = '', $blog_id = 0)
    {
        if (! $blog_id ) {
            return [];
        }

        if ( ! $given_db_prefix ) {
            $given_db_prefix = $this->getSystemFunctions()->getDbPrefixOfSite($blog_id);
        }
        
        global $wpdb;      
        $escaped_like = $wpdb->esc_like($given_db_prefix);
        $target_prefix = $escaped_like . '%';        
        $usermeta_table = $this->getSystemFunctions()->getUserMetaTableName();
        
        if ($this->getSystemFunctions()->isMultisiteMainSite($blog_id, true)) {
            $regex = $escaped_like . '[0-9]+';
            $db_search = "SELECT DISTINCT meta_key FROM {$usermeta_table} where meta_key LIKE %s AND meta_key NOT REGEXP %s";
            $prepared = $wpdb->prepare($db_search, $target_prefix, $regex);
            $user_meta_keys = $wpdb->get_results($prepared, ARRAY_A);
            
        } else {           
            $db_search = "SELECT DISTINCT meta_key FROM {$usermeta_table} where meta_key LIKE %s";
            $user_meta_keys = $wpdb->get_results($wpdb->prepare($db_search, $target_prefix), ARRAY_A);            
        }        
        
        if (empty($user_meta_keys)) {
            return [];
        }
        
        return wp_list_pluck($user_meta_keys, 'meta_key');
    }
    
    /**
     * Maybe add user to blog and then delete old role/capability user metas
     * @param number $user_id
     * @param string $target_cap_prefix
     * @param string $target_level_prefix
     * @param number $blog_id
     * @param string $current_cap_prefix
     * @param string $current_level_prefix
     */
    public function maybeAddUserToBlog($user_id = 0, $target_cap_prefix = '', $target_level_prefix = '', $blog_id = 0, $current_cap_prefix = '', $current_level_prefix = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $add_user_to_blog = false;
        if (is_multisite() && $user_id && $blog_id && !is_user_member_of_blog($user_id, $blog_id)) {
            $add_user_to_blog = true;
        }              
        
        if ($add_user_to_blog) {
            do_action('prime_mover_log_processed_events', "Adding current Prime Mover user to this multisite blog.", $blog_id, 'import', __FUNCTION__, $this);
            $this->updateUserRoleToTargetSite($user_id, $target_cap_prefix, $target_level_prefix, $current_cap_prefix, $current_level_prefix);  
        } else {
            $this->deleteCapUserMetas($user_id, $target_cap_prefix, $target_level_prefix);
        }             
    }
    
    /**
     * Delete cap user metas
     * @param number $user_id
     * @param string $target_cap_prefix
     * @param string $target_level_prefix
     */
    public function deleteCapUserMetas($user_id = 0, $target_cap_prefix = '', $target_level_prefix = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        delete_user_meta($user_id, $target_cap_prefix);
        delete_user_meta($user_id, $target_level_prefix);
    }
    
    /**
     * Get user roles from cap prefix
     * @param string $target_cap_prefix
     * @param number $user_id
     */
    protected function getUserRolesFromCapPrefix($target_cap_prefix = '', $user_id = 0)
    {
        $roles = [];
        $capability_from_source = get_user_meta($user_id, $target_cap_prefix, true);
        if (!is_array($capability_from_source)) {
            return [];
        }
        foreach ($capability_from_source as $role => $logic) {
            if ($logic) {
                $roles[] = $role;
            }
        }
        return $roles;
    }
    
    /**
     * Update Cap user metas
     * @param number $user_id
     * @param string $current_cap_prefix
     * @param string $new_cap
     * @param string $current_level_prefix
     * @param string $new_user_level
     */
    public function updateCapUserMetas($user_id = 0, $current_cap_prefix = '', $new_cap = '', $current_level_prefix = '', $new_user_level = '')
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        do_action('prime_mover_update_user_meta', $user_id, $current_cap_prefix, $new_cap);
        do_action('prime_mover_update_user_meta', $user_id, $current_level_prefix, $new_user_level);
    }
    
    /**
     * Update user role to target site
     * @param number $user_id
     * @param string $target_cap_prefix
     * @param string $target_level_prefix
     * @param string $current_cap_prefix
     * @param string $current_level_prefix
     */
    public function updateUserRoleToTargetSite($user_id = 0, $target_cap_prefix = '', $target_level_prefix = '', $current_cap_prefix = '', $current_level_prefix = '')
    {
        $new_cap = get_user_meta($user_id, $target_cap_prefix, true);
        $new_user_level = get_user_meta($user_id, $target_level_prefix, true);
        
        $this->updateCapUserMetas($user_id, $current_cap_prefix, $new_cap, $current_level_prefix, $new_user_level);
        $this->deleteCapUserMetas($user_id, $target_cap_prefix, $target_level_prefix);
    }
    
    /**
     * Generate user meta keys to adjust on export
     * @param array $ret
     * @param number $blog_id
     * @return array
     */
    public function generateUserMetaKeysToAdjustOnExport($ret = [], $blog_id = 0)
    {
        $user_meta_keys = $this->getAffectedUserMetaGivenPrefix('', $blog_id);
        if ( ! is_array($user_meta_keys) || empty($user_meta_keys) || ! isset($ret['randomizedbprefixstring'])) {
            return $ret;
        }
        
        $new_user_meta_keys = [];
        $current_db_prefix = $this->getSystemFunctions()->getDbPrefixOfSite($blog_id);
        $randomized_db_prefix = $ret['randomizedbprefixstring'];
        
        foreach ($user_meta_keys as $user_meta_key) {
            $prefix_free = $this->getSystemFunctions()->removePrefix($current_db_prefix, $user_meta_key);
            if ($prefix_free === $user_meta_key) {
                continue;
            }
            
            $new_meta_key = $randomized_db_prefix . $prefix_free;
            $new_user_meta_keys[$user_meta_key] = $new_meta_key;
        }
        
        return $new_user_meta_keys;
    }
    
    /**
     * Checks if zip package now includes user files
     * @param string $tmp_path
     * @param string $ret_mode
     * @return string|boolean|string
     */
    public function isZipPackageIncludeUsers($tmp_path = '', $ret_mode = 'bool')
    {
        $pos = true;
        $neg = false;
        if ('txt' === $ret_mode) {
            $pos = esc_html__('Yes', 'prime-mover');
            $neg = esc_html__('No', 'prime-mover');
        }
        if ( ! $tmp_path ) {
            return $neg;
        }
  
        $user_files = [self::DEFAULT_USER_FILE, self::ENCRYPTED_USER_FILE, self::SPECIAL_USER_META_FILE];       
        $za = $this->getSystemFunctions()->getZipArchiveInstance();
        
        $zip = $za->open($tmp_path);
        $opened = false;
        if (true === $zip) {
            $opened = true;
        }
        if ( ! $opened ) {
            return $neg;
        }
        foreach ($user_files as $user_file) {
            if (false !== $za->locateName($user_file, \ZIPARCHIVE::FL_NODIR)) {
                return $pos;
            }
        }
        return $neg;
    }
}
