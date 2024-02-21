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
use SplFixedArray;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover BuddyPress / BuddyBoss Plugin Compatibility Class
 * Helper class for interacting with BuddyPress / BuddyBoss plugin
 *
 */
class PrimeMoverBuddyPressCompat
{     
    private $prime_mover;
    private $buddypress_plugin;
    private $buddyboss_plugin;
    private $callbacks;
    private $bp_random_db_prefix;    
    private $bp_random_prefix_prop;
    private $getbaseprefix_bp;    
    private $tables;
    private $force_processors;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->buddypress_plugin = 'buddypress/bp-loader.php';        
        $this->buddyboss_plugin = 'buddyboss-platform/bp-loader.php';        
        
        $this->callbacks = [
            'maybeAdjustUserIdsBpActivityTable' => 50,
            'maybeAdjustInitiatorUserIdsFriendsTable' => 51,
            'maybeAdjustFriendUserIdsFriendsTable' => 52,
            'maybeAdjustCreatorIdsGroupTable' => 53,
            'maybeAdjustUserIdsGroupMembers' => 54,
            'maybeAdjustInviterIdsGroupMembers' => 55,
            'maybeAdjustUserIdsInvitations' => 56,
            'maybeAdjustInviterIdsInvitations' => 57,
            'maybeAdjustSenderIdsMessages' => 58,
            'maybeAdjustUserIdsMessageRecipients' => 59,
            'maybeAdjustUserIdsNotifications' => 60,
            'maybeAdjustUserIdsOptOut' => 61,
            'maybeAdjustUserIdsUserBlogs' => 62,
            'maybeAdjustUserIdsXprofileData' => 63,
            'maybeAdjustUserIdDocumentTable' => 64,
            'maybeAdjustUserIdDocumentFolder' => 65,
            'maybeAdjustUserIdsMediaTable' => 66,
            'maybeAdjustUserIdsMediaAlbum' => 67,
            'maybeAdjustUserIdsModerationTable' => 68,
            'maybeAdjustUserIdsSuspendDetails' => 69,
            'maybeAdjustUserIdsZoomMeetings' => 70,
            'maybeAdjustUserIdsZoomWebinars' => 71,
            'maybeAdjustUserIdsNotificationsSubscriptions' => 72,
            'maybeAdjustNotificationsComponent' => 73,
            'maybeAdjustItemIdsActivityTable' => 74,
            'maybeAdjustSecondaryItemIdsActivityTable' => 75,
            'maybeAdjustNotificationsSecondaryItem' => 76,
            'maybeAdjustStarredMessages' => 77,
            'maybeAdjustSerializedIdsActivityMeta' => 78,
            'maybeAdjustItemIdMembersComponent' => 79,
            'maybeAdjustChangePasswordNotificationComponent' => 80,
            'maybeAdjustSecondaryItemIdGroupsComponent' => 81,
            'maybeAdjustBlogIdNotificationsSubscriptions' => 82,
            'maybeAdjustUserIdsProfileFolders' => 999
        ];
        
        $this->force_processors = [
            'maybeAdjustBlogIdNotificationsSubscriptions'
        ];        
        
        $this->bp_random_db_prefix = '';
        $this->getbaseprefix_bp = '';        
        $this->bp_random_prefix_prop = '';  
        $this->tables = [
            'bp_activity',
            'bp_activity_meta',
            'bp_friends',
            'bp_groups',
            'bp_groups_groupmeta',
            'bp_groups_members',
            'bp_invitations',
            'bp_messages_messages',
            'bp_messages_meta',
            'bp_messages_notices',
            'bp_messages_recipients',
            'bp_notifications',
            'bp_notifications_meta',
            'bp_optouts',
            'bp_user_blogs',
            'bp_user_blogs_blogmeta',
            'bp_xprofile_data',
            'bp_xprofile_fields',
            'bp_xprofile_groups',
            'bp_xprofile_meta',
            'bp_document',
            'bp_document_folder',
            'bp_document_folder_meta',
            'bp_document_meta',
            'bp_follow',
            'bp_groups_membermeta',
            'bp_invitations_invitemeta',
            'bp_media',
            'bp_media_albums',
            'bp_moderation',
            'bp_moderation_meta',
            'bp_suspend',
            'bp_suspend_details',
            'bp_zoom_meeting_meta',
            'bp_zoom_meetings',
            'bp_zoom_recordings',
            'bp_zoom_webinar_meta',
            'bp_zoom_webinar_recordings',
            'bp_zoom_webinars',
            'bb_notifications_subscriptions'
        ];
    }
    
    /**
     * Get force processor which does not depend on user equivalence check.
     * @return string[]
     */
    public function getForceProcessors()
    {
        return $this->force_processors;
    }
    
    /**
     * Get tables
     * @return string[]
     */
    public function getTables()
    {
        return $this->tables;
    }
    
    /**
     * Get Base BuddyPress dB prefix
     * @return string
     */
    public function getBpBaseDbPrefix()
    {
        return $this->getbaseprefix_bp;
    }
    
    /**
     * Get random prefix of BuddyPress dB tables
     * @return string
     */
    public function getBpRandomPrefixProp()
    {
        return $this->bp_random_prefix_prop;
    }
 
    /**
     * Set BuddyPress random prefix
     * @param string $bp_random_prefix
     */
    public function setBpRandomDbPrefix($bp_random_prefix = '')
    {
        $this->bp_random_db_prefix = strtolower($bp_random_prefix);
    }
    
    /**
     * Gets BuddyPress random prefix
     * @param array $ret
     * @return string
     */
    public function getBpRandomPrefix($ret = [])
    {
        $blog_id = $this->getSystemInitialization()->getExportBlogID(); 
        if (!$blog_id && !is_multisite()) {
            $blog_id = 1;
        }
        
        if (!$this->bp_random_db_prefix && $blog_id) {
            if (empty($ret)) {
                $ret = apply_filters('prime_mover_get_export_progress', [], $blog_id);
            }
            if (isset($ret['bp_randomizedbprefixstring'])) {
                return $ret['bp_randomizedbprefixstring'];
            }
        }
        return $this->bp_random_db_prefix;
    }
    
    /**
     * Get BuddyPress plugin
     * @return string
     */
    public function getBuddyPressPlugin()
    {
        return $this->buddypress_plugin;
    }
    
    /**
     * Get BuddyBoss plugin
     * @return string
     */
    public function getBuddyBossPlugin()
    {
        return $this->buddyboss_plugin;
    }
    
    /**
     * Get callbacks
     * @return number[]
     */
    public function getCallBacks()
    {
        return $this->callbacks;
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
     * Get progress handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->getPrimeMover()->getHookedMethods()->getProgressHandlers();
    }
    
    /**
     * Get user queries
     * @return \Codexonics\PrimeMoverFramework\users\PrimeMoverUserQueries
     */
    public function getUserQueries()
    {
        return $this->getPrimeMover()->getImporter()->getUsersObject()->getUserUtilities()->getUserFunctions()->getUserQueries();    
    }
    
    /**
     * Initialize hooks
     */
    public function initHooks()
    {        
        foreach ($this->getCallBacks() as $callback => $priority) {
            add_filter('prime_mover_do_process_thirdparty_data', [$this, $callback], $priority, 3);
        }
        
        add_action('prime_mover_before_thirdparty_data_processing', [$this, 'removeProcessorHooksWhenDependencyNotMeet'], 10, 2); 
        add_action('prime_mover_before_actual_import', [$this, 'maybeRemoveBuddyPressAvatarProfileDirectory'], 999, 3);
        add_filter('prime_mover_process_serialized_data_user_adjustment', [$this, 'maybeUserAdjustSerialized'], 10, 4);
        
        add_filter('prime_mover_inject_thirdparty_app_prefix', [$this, 'maybeInjectdBuddyPressTablePrefix'], 10, 3);         
        add_filter('prime_mover_tables_to_export', [$this, 'maybeAppendBuddyPressTables'], 10, 3);    
        add_filter('prime_mover_filter_ret_after_db_dump', [$this, 'filterTablesForWriting'], 1, 3);
        
        add_filter('prime_mover_filter_export_db_data', [$this, 'randomizeBpDbPrefix'], 6, 1); 
        add_filter('prime_mover_before_mysqldump_php', [$this, 'initializeBuddyPressRandomPrefix'], 40, 1); 
        add_filter('prime_mover_before_mysqldump_php', [$this, 'initializeGetBaseDbPrefixBP'], 16, 1);   
        
        add_filter('prime_mover_filter_export_footprint', [$this, 'maybeAppendBuddyPrefixInfoOnExportFootPrint'], 99, 3);
        add_filter('prime_mover_tables_for_replacement', [$this, 'maybeAddBuddyPressTablesForReplacement'], 20, 3);
        add_filter('prime_mover_filter_converted_db_tables_before_rename', [$this, 'maybeConvertBuddyPressTableToCompatPrefix'], 10, 3);
        
        add_filter('prime_mover_non_user_adjustment_update_data', [$this, 'updateBlogIddDataNotificationSubscriptions'], 11, 8);
        add_filter('prime_mover_excluded_filesfolders_export', [$this, 'maybeExcludeBBPlatformPreviews'], 1000, 1);
    }
    
    /**
     * Maybe exclude redundant BB Platform Previews folder
     * @param array $skip
     * @return array
     */
    public function maybeExcludeBBPlatformPreviews($skip = [])
    {
        if (!is_array($skip)) {
            return $skip;
        }
        
        if (defined('PRIME_MOVER_ADD_BBPLATFORM_PREVIEWS') && true === PRIME_MOVER_ADD_BBPLATFORM_PREVIEWS) {
            return $skip;
        }
        
        if (!in_array('bb-platform-previews', $skip)) {
            $skip[] = 'bb-platform-previews';
        }
        
        return $skip;       
    }  
    
    /**
     * Hooked to `prime_mover_non_user_adjustment_update_data`
     * @param number $primary_index_id
     * @param string $value
     * @param string $table
     * @param string $primary_index
     * @param string $user_id_column
     * @param string $leftoff_identifier
     * @param array $ret
     * @param array $filter_clause
     * @return number
     */
    public function updateBlogIddDataNotificationSubscriptions($primary_index_id = 0, $value = '', $table = '', $primary_index = '', $user_id_column = '', $leftoff_identifier = '', $ret = [], $filter_clause = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $primary_index_id;
        }
        
        $table = 'bb_notifications_subscriptions';
        if ("3rdparty_{$table}_two_leftoff" !== $leftoff_identifier) {
            return $primary_index_id;
        }
        
        if (empty($ret['imported_package_footprint']['footprint_blog_id'])) {
            return $primary_index_id;            
        }
        
        $source_blog_id = $ret['imported_package_footprint']['footprint_blog_id'];
        $source_blog_id = (int)$source_blog_id;
        $source_blog_id_db_value = (int)$value;
        $target_blog_id = 1;
        
        if (is_multisite() && isset($filter_clause['target_blog_id'])) {
            $target_blog_id = $filter_clause['target_blog_id'];
        }
        
        if (!is_multisite()) {
            $target_blog_id = 1;
        }
        
        $target_blog_id = (int)$target_blog_id;    
        if ($source_blog_id !== $source_blog_id_db_value) {
            $target_blog_id = 0;
        }        
       
        global $wpdb;        
        $notifications_table = $this->getBasePrefix() . $table;        
        $query = $wpdb->prepare("
                   UPDATE {$notifications_table}
                   SET {$user_id_column} = %d
                   WHERE {$primary_index} = %d",
                   $target_blog_id, $primary_index_id
        ); 
       
        return $this->getUserQueries()->updateCustomerUserIdBySQL($primary_index_id, 0, '', '', '', $query);        
    }
    
    /**
     * Maybe adjust blog ID on notifications subscriptions table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 82
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustBlogIdNotificationsSubscriptions($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = $this->getUserQueries()->maybeRequisitesNotMeetForAdjustment($ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()], false);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bb_notifications_subscriptions';
        $primary_index = 'id';
        $column_strings = 'id, blog_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        $filter_clause = [];
        $filter_clause['target_blog_id'] = $blogid_to_import;
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_two_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_two_updated", "{$table} two table", $start_time, $last_processor, '', true, $filter_clause);
    }
    
    /**
     * Maybe adjust secondary item ID for groups notifications component
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 81
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustSecondaryItemIdGroupsComponent($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_notifications';
        $primary_index = 'id';
        $column_strings = 'id, secondary_item_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        $filter_clause = [];
        $filter_clause['where_clause'][] = ['field' => 'component_name', 'value' => 'groups'];
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_six_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_six_updated", "{$table} six table", $start_time, $last_processor, '', false, $filter_clause);
    }
    
    /**
     * Maybe adjust secondary item ID for change password notifications component
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 80
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustChangePasswordNotificationComponent($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_notifications';
        $primary_index = 'id';
        $column_strings = 'id, secondary_item_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        $filter_clause = [];
        $filter_clause['where_clause'][] = ['field' => 'component_name', 'value' => 'members', 'condition' => 'AND'];
        $filter_clause['where_clause'][] = ['field' => 'component_action', 'value' => 'bb_account_password'];
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_five_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_five_updated", "{$table} five table", $start_time, $last_processor, '', false, $filter_clause);
    }
    
    /**
     * Maybe adjust item ID for member notifications component
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 79
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustItemIdMembersComponent($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_notifications';
        $primary_index = 'id';
        $column_strings = 'id, item_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        $filter_clause = [];
        $filter_clause['where_clause'][] = ['field' => 'component_name', 'value' => 'members'];

        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_four_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_four_updated", "{$table} four table", $start_time, $last_processor, '', false, $filter_clause);
    }
    
    /**
     * Maybe adjust serialized user ID data
     * @param number $migrated_user_id
     * @param array $result
     * @param SplFixedArray $user_equivalence
     * @param string $update_variable
     * @return number|string[]
     */
    public function maybeUserAdjustSerialized($migrated_user_id = 0, $result = [], SplFixedArray $user_equivalence = null, $update_variable = '')
    {
        if ('3rdparty_bp_activity_meta_updated' !== $update_variable) {
            return $migrated_user_id;
        }
       
        if (!is_array($result) || empty($result['meta_value'])) {
            return $migrated_user_id;
        }
        
        $raw_data = $result['meta_value'];
        if (!is_serialized($raw_data)) {
            return $migrated_user_id;
        }
        
        $raw_data = maybe_unserialize($raw_data);
        if (!is_array($raw_data)) {
            return $migrated_user_id;
        }
      
        $adjusted = [];
        foreach ($raw_data as $data) {
            $data = (int)$data;
            if (!$data) {
                continue;
            }
          
            if (!isset($user_equivalence[$data])) {
                continue;
            }
            
            $new_id = $user_equivalence[$data];
            $new_id = (int)$new_id;
            $adjusted[] = $new_id;
        }
        
        if (empty($adjusted)) {
            return $migrated_user_id;
        }
             
        return maybe_serialize($adjusted);        
    }
    
    /**
     * Maybe adjust serialized activity meta
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 78
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustSerializedIdsActivityMeta($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_activity_meta';
        $primary_index = 'id';
        $column_strings = 'id, meta_value';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        $filter_clause = [];
        $filter_clause['where_clause'][] = ['field' => 'meta_key', 'value' => 'bp_favorite_users'];
        $filter_clause['is_serialized'] = true;
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '', false, $filter_clause);
    }
    
    /**
     * Maybe adjust starred messages
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 77
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustStarredMessages($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_messages_meta';
        $primary_index = 'id';
        $column_strings = 'id, meta_value';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        $filter_clause = [];
        $filter_clause['where_clause'][] = ['field' => 'meta_key', 'value' => 'starred_by_user'];
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '', false, $filter_clause);
    }
    
    /**
     * Maybe adjust notifications secondary item ID
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 76
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustNotificationsSecondaryItem($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_notifications';
        $primary_index = 'id';
        $column_strings = 'id, secondary_item_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);        
        
        $filter_clause = [];
        $filter_clause['where_clause'][] = ['field' => 'component_name', 'value' => 'friends', 'condition' => 'OR'];
        $filter_clause['where_clause'][] = ['field' => 'component_name', 'value' => 'messages', 'condition' => 'OR'];
        $filter_clause['where_clause'][] = ['field' => 'component_name', 'value' => 'forums', 'condition' => 'OR'];
        $filter_clause['where_clause'][] = ['field' => 'component_name', 'value' => 'activity'];
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_three_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_three_updated", "{$table} three table", $start_time, $last_processor, '', false, $filter_clause);
    }
    
    /**
     * Maybe adjust secondary item activity table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 75
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustSecondaryItemIdsActivityTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_activity';
        $primary_index = 'id';
        $column_strings = 'id, secondary_item_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);        

        $filter_clause = [];
        $filter_clause['where_clause'][] = ['field' => 'component', 'value' => 'friends'];        
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_three_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_three_updated", "{$table} three table", $start_time, $last_processor, '', false, $filter_clause);
    }
    
    /**
     * Maybe adjust item ids activity table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 74
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustItemIdsActivityTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_activity';
        $primary_index = 'id';
        $column_strings = 'id, item_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);

        $filter_clause = [];
        $filter_clause['where_clause'][] = ['field' => 'component', 'value' => 'friends'];        
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_two_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_two_updated", "{$table} two table", $start_time, $last_processor, '', false, $filter_clause);
    }
    
    /**
     * Maybe adjust notifications component
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 73
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustNotificationsComponent($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_notifications';
        $primary_index = 'id';
        $column_strings = 'id, item_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        $filter_clause = [];
        $filter_clause['where_clause'][] = ['field' => 'component_name', 'value' => 'friends'];        
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_two_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_two_updated", "{$table} two table", $start_time, $last_processor, '', false, $filter_clause);
    }
    
    /**
     * Maybe remove BuddyPress Avatar profile before migrating package from another site
     * Hooked to `prime_mover_before_actual_import` - priority 10
     * @param array $ret
     * @param number $blogid_to_import
     */
    public function maybeRemoveBuddyPressAvatarProfileDirectory($ret = [], $blogid_to_import = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !is_array($ret) || !$blogid_to_import) {
            return;
        }  
        
        if (!empty($ret['copydir_processed'])) {
            return;
        } 
        
        $blog_id = (int)$blogid_to_import;
        if (!$this->maybeRestoringBuddyPressPackage($ret)) {
            return;
        }
        
        $done = false;
        $avatar_dir = $this->getProfileDirectory($ret, true, $blog_id);
        $buddypress_dir = $this->getProfileDirectory($ret, false, $blog_id);
        if ($avatar_dir && $this->getSystemFunctions()->nonCachedFileExists($avatar_dir)) {
            $this->getSystemFunctions()->primeMoverDoDelete($avatar_dir, true);
            $done = true;
        }
        
        if ($buddypress_dir && $this->getSystemFunctions()->nonCachedFileExists($buddypress_dir)) {
            $this->getSystemFunctions()->primeMoverDoDelete($buddypress_dir, true);
            $done = true;
        } 
        
        if ($done) {
            do_action('prime_mover_log_processed_events', "Successfully deleted outdated BuddyPress and avatar profile directories"  , $blogid_to_import, 'import', __FUNCTION__, $this);
        }        
    }
    
    /**
     * Get profile directory
     * @param array $ret
     * @param boolean $avatar_mode
     * @param number $blog_id
     * @return boolean|string
     */
    protected function getProfileDirectory($ret = [], $avatar_mode = true, $blog_id = 0)
    {
        if (!is_array($ret)) {
            return false;
        }
        
        $base_dir = '';
        if (!empty($ret['canonical_uploads_information']['basedir'])) {            
            $base_dir = $ret['canonical_uploads_information']['basedir'];            
        } 
        
        $uploads_info = [];
        if (!$base_dir && $blog_id) {          
            $this->getSystemFunctions()->switchToBlog($blog_id);   
            $uploads_info = $this->getSystemInitialization()->getWpUploadsDir(true, true);
            $this->getSystemFunctions()->restoreCurrentBlog($blog_id);
        }
        
        if (!empty($uploads_info['basedir'])) {
            $base_dir = $uploads_info['basedir'];
        }
        
        if (!$base_dir) {
            return false;
        }    
        
        $base_dir = untrailingslashit(wp_normalize_path($base_dir));
        if ($avatar_mode) {
            $avatar_dir = $base_dir . '/avatars/';
            return $avatar_dir;
        }
        
        $buddypress_dir = $base_dir . '/buddypress/members/';        
        return $buddypress_dir;
    }
    
    /**
     * Maybe adjust user IDs in avatar folders
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 999
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsProfileFolders($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
      
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
       
        $ret['3rdparty_current_function'] = __FUNCTION__;        
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        if (empty($ret['canonical_uploads_information']['basedir'])) {
            return $ret;
        }
       
        if (!empty($ret['skipped_media'])) {
            return $ret;
        }  
        
        $avatar_dir = $this->getProfileDirectory($ret, true);
        $buddypress_dir = $this->getProfileDirectory($ret, false);
        if (false === $avatar_dir || false === $buddypress_dir) {
            return $ret;
        }
        
        $leftoff_identifier = '3rdparty_bp_avatarbp_dir_leftoff';
        $this->getSystemFunctions()->initializeFs(false);
        global $wp_filesystem;
        if (!$this->getSystemFunctions()->isWpFileSystemUsable($wp_filesystem)) {
            return $ret;
        }
       
        $avatars_writable = false;
        if (win_is_writable($avatar_dir)) {
            $avatars_writable = true;
        }
        
        $bp_dir_writable = false;
        if (win_is_writable($buddypress_dir)) {
            $bp_dir_writable = true;
        }
        
        if (!$avatars_writable) {
            do_action('prime_mover_log_processed_events', "ERROR: {$avatar_dir} is not writable - could not update avatar user IDs in this directory"  , $blogid_to_import, 'import', __FUNCTION__, $this);
        }
        
        if (!$bp_dir_writable) {
            do_action('prime_mover_log_processed_events', "ERROR: {$bp_dir_writable} is not writable - could not update user IDs in this directory."  , $blogid_to_import, 'import', __FUNCTION__, $this);
        }
        
        $update_variable = '3rdparty_bp_avatars_updated';
        $progress_identifier = 'avatar folders';             
        $directories_updated = 0;
        if (isset($ret[$update_variable])) {
            $directories_updated = $ret[$update_variable];
        }
        
        $update_directories_progress = '';
        if ($directories_updated) {
            $update_directories_progress = sprintf(esc_html__('%d completed', 'prime-mover'), $directories_updated);
        }
        
        $this->getProgressHandlers()->updateTrackerProgress(sprintf(esc_html__('Updating %s.. %s', 'prime-mover'), $progress_identifier, $update_directories_progress), 'import' );
        $user_equivalence = $ret['user_equivalence'];         
        $start_index = 0;
        
        if (isset($ret[$leftoff_identifier])) {
            $start_index = $ret[$leftoff_identifier];
            $start_index = (int)$start_index;
            do_action('prime_mover_log_processed_events', "Resuming renaming of profile directories at index {$start_index}" , $blogid_to_import, 'import', __FUNCTION__, $this);
        }
        $count = count($user_equivalence);
        for ($i = $start_index; $i < $count; $i++) {   
            $old_id = $i;
            $new_id = 0;
            if (isset($user_equivalence[$old_id])) {
                $new_id = $user_equivalence[$old_id];
            } 
            
            if (!$old_id || !$new_id) {
                continue;
            }
            
            $old_id = (int)$old_id;
            $new_id = (int)$new_id;
            if ($old_id === $new_id) {
                continue;
            }
            $avatar_result = $this->processRename($avatar_dir, 'avatar', $old_id, $new_id, $directories_updated);
            if ($avatar_result) {
                $directories_updated = $avatar_result;
            }
            $buddypress_result = $this->processRename($buddypress_dir, 'buddypress', $old_id, $new_id, $directories_updated);
            if ($buddypress_result) {
                $directories_updated = $buddypress_result;
            }
            
            $this->maybeEnableTestMode();
            $retry_timeout = apply_filters('prime_mover_retry_timeout_seconds', PRIME_MOVER_RETRY_TIMEOUT_SECONDS, __FUNCTION__);
            if ((microtime(true) - $start_time) > $retry_timeout) {
                $ret[$leftoff_identifier] = $i + 1;
                $ret[$update_variable] = $directories_updated;
                
                do_action('prime_mover_log_processed_events', "$retry_timeout seconds time out on updating {$progress_identifier}" , $blogid_to_import, 'import', __FUNCTION__, $this);
                $ret['prime_mover_thirdparty_processing_retry'] = true;
                
                return $ret;
            }            
        }        
        
        return $this->bailOutAndReturnRetArray($ret, $update_variable, $last_processor, $leftoff_identifier);
    }    

    /**
     * Enable test mode in profile directory renaming
     * Devs only.
     */
    protected function maybeEnableTestMode()
    {
        if (!defined('PRIME_MOVER_BP_PROFILEDIR_TEST_MODE') ) {
            return;
        }
        
        if (true === PRIME_MOVER_BP_PROFILEDIR_TEST_MODE ) {
            $this->getSystemInitialization()->setProcessingDelay(10, false);
        }  
    }
    
    /**
     * Process rename
     * @param string $profile_dir
     * @param string $mode
     * @param number $old_id
     * @param number $new_id
     * @param number $directories_updated
     * @return boolean|number
     */
    protected function processRename($profile_dir = '', $mode = 'avatar', $old_id = 0, $new_id = 0, $directories_updated = 0)
    {
        global $wp_filesystem;
        $source_orig = $profile_dir . $old_id;
        $source_tmp = $profile_dir . "{$old_id}-tmp-{$mode}";
        $source = '';
        
        if ($this->getSystemFunctions()->nonCachedFileExists($source_orig)) {
            $source = $source_orig;
        }
        
        if ($this->getSystemFunctions()->nonCachedFileExists($source_tmp)) {
            $source = $source_tmp;
        }
        
        if (!$source) {
            return false;
        }
        
        $target = $profile_dir . $new_id;
        $rename_ready = false;
        if ($this->getSystemFunctions()->nonCachedFileExists($target)) {
            $target_tmp = $profile_dir . "{$new_id}-tmp-{$mode}";
            $rename_ready = $wp_filesystem->move($target, $target_tmp, false);
        } else {
            $rename_ready = true;
        }
        
        $rename_success = false;
        if ($rename_ready) {
            $rename_success = $wp_filesystem->move($source, $target, false);
        }
        
        if ($rename_success) {
            $directories_updated++;
        }  
        return $directories_updated;
    }
    
    /**
     * Bail out and return $ret array
     * @param array $ret
     * @param string $update_variable
     * @param boolean $last_processor
     * @param string $leftoff_identifier
     * @return array
     */
    private function bailOutAndReturnRetArray($ret = [], $update_variable = '', $last_processor = false, $leftoff_identifier = '')
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
        
        return $ret;
    }
 
    /**
     * Maybe adjust user IDs notifications subscriptions
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 72
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsNotificationsSubscriptions($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bb_notifications_subscriptions';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs Zoom webinars
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 71
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsZoomWebinars($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_zoom_webinars';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs Zoom meetings
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 70
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsZoomMeetings($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_zoom_meetings';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs suspend details
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 69
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsSuspendDetails($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_suspend_details';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs moderation table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 68
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsModerationTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_moderation';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs media album
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 67
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsMediaAlbum($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_media_albums';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs media table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 66
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsMediaTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_media';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs document folder
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 65
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdDocumentFolder($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_document_folder';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs document table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 64
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdDocumentTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_document';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs Xprofile data table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 63
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsXprofileData($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_xprofile_data';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user ids on user blogs
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 62
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsUserBlogs($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_user_blogs';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user Ids in opt out table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 61
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsOptOut($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_optouts';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user ids in notifications table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 60
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsNotifications($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_notifications';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs in message recipients table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 59
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsMessageRecipients($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_messages_recipients';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust sender IDs in messages table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 58
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     */
    public function maybeAdjustSenderIdsMessages($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_messages_messages';
        $primary_index = 'id';
        $column_strings = 'id, sender_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust inviter IDs in invitations table.
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 57
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     */
    public function maybeAdjustInviterIdsInvitations($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_invitations';
        $primary_index = 'id';
        $column_strings = 'id, inviter_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_two_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_two_updated", "{$table} two table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs in invitations table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 56
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsInvitations($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_invitations';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_one_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_one_updated", "{$table} one table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust inviter IDs in group members table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 55
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustInviterIdsGroupMembers($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_groups_members';
        $primary_index = 'id';
        $column_strings = 'id, inviter_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_two_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_two_updated", "{$table} two table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs in group members table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 54
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsGroupMembers($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_groups_members';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust creator user IDs in group table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 53
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustCreatorIdsGroupTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_groups';
        $primary_index = 'id';
        $column_strings = 'id, creator_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust friend user IDs in friends table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 52
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustFriendUserIdsFriendsTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_friends';
        $primary_index = 'id';
        $column_strings = 'id, friend_user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_second_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_second_updated", "{$table} second table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust intiator user IDs in friends table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 51
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustInitiatorUserIdsFriendsTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_friends';
        $primary_index = 'id';
        $column_strings = 'id, initiator_user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_first_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_first_updated", "{$table} first table", $start_time, $last_processor, '');
    }
    
    /**
     * Maybe adjust user IDs activity table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 50
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsBpActivityTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'bp_activity';
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, 1, "3rdparty_{$table}_leftoff", $primary_index, $column_strings,
        "3rdparty_{$table}_updated", "{$table} table", $start_time, $last_processor, '');
    }
    
    /**
     * Convert BuddyPress tables to correct prefix at target site
     * Hooked to `prime_mover_filter_converted_db_tables_before_rename`
     * @param array $converted
     * @param array $clean_tables
     * @param array $ret
     * @return array
     */
    public function maybeConvertBuddyPressTableToCompatPrefix($converted = [], $clean_tables = [], $ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized() || !is_array($converted) || !is_array($clean_tables) || !is_array($ret)) {
            return $converted;
        }        
       
        if (!$this->maybeRestoringBuddyPressPackage($ret)) {
            return $converted;
        }
       
        $buddypress_tables = $ret['imported_package_footprint']['buddypress_tables'];
        $buddypress_prefix = $ret['imported_package_footprint']['buddypress_db_prefix'];    
        $target_prefix = $this->getBasePrefix();
     
        foreach ($converted as $origin_table_name => $new_table_name) {
            if (in_array($origin_table_name, $buddypress_tables)) {               
                $new_table_name = str_replace($buddypress_prefix, $target_prefix, $origin_table_name);
                $converted[$origin_table_name] = $new_table_name;
            }
        }
        
        return $converted;
    }
    
    /**
     * Checks if restoring BuddyPress package
     * @param array $ret
     * @return boolean
     */
    protected function maybeRestoringBuddyPressPackage($ret = [])
    {
        if (empty($ret['imported_package_footprint']['buddypress_tables'])) {
            return false;
        }
        
        if (empty($ret['imported_package_footprint']['buddypress_db_prefix'])) {
            return false;
        }
        return true;
    }
    
    /**
     * Add BuddyPress tables for replacement (during import)
     * @param array $all_tables
     * @param number $blog_id
     * @param array $ret
     * @return array
     */
    public function maybeAddBuddyPressTablesForReplacement($all_tables = [], $blog_id = 0, $ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $all_tables;
        }
        
        if (!is_multisite()) {
            $blog_id = 1;
        }
        
        if (!$blog_id || !is_array($all_tables) || !is_array($ret)) {
            return $all_tables;
        }
    
        if (true === $this->maybeBasePrefixSameWithSite($blog_id)) {
            return $all_tables;
        }
        
        if (!$this->maybeRestoringBuddyPressPackage($ret)) {
            return $all_tables;
        }
       
        $buddypress_tables = $this->getBuddyPressTables();
        if (!is_array($buddypress_tables) || empty($buddypress_tables)) {
            return $all_tables;
        }
             
        return array_merge($all_tables, $buddypress_tables);
    }
    
    /**
     * Append BuddyPress prefix on system footprint
     * @param array $export_system_footprint
     * @param array $ret
     * @param number $blogid_to_export
     * @return array
     */
    public function maybeAppendBuddyPrefixInfoOnExportFootPrint($export_system_footprint = [], $ret = [], $blogid_to_export = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $export_system_footprint;
        }
        if (!is_array($export_system_footprint) || !is_array($ret)) {
            return $export_system_footprint;
        }
        
        if (!isset($ret['bp_randomizedbprefixstring']) || empty($ret['buddypress_tables'])) {
            return $export_system_footprint;
        }
        
        $export_system_footprint['buddypress_tables'] = $ret['buddypress_tables'];
        $export_system_footprint['buddypress_db_prefix'] = $ret['bp_randomizedbprefixstring'];
        
        return $export_system_footprint;
    }
    
    /**
     * Filter `exported_db_tables` and make sure BuddyPress table prefix are adjusted for compatibility
     * Hooked to `prime_mover_filter_ret_after_db_dump` executed at the end of dB dump process.
     * @param array $ret
     * @param array $clean_tables
     * @param number $blogid_to_export
     * @return array
     */
    public function filterTablesForWriting($ret = [], $clean_tables = [], $blogid_to_export = 0)
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        if (!$this->getSystemInitialization()->getMaybeRandomizeDbPrefix() || !is_array($ret)) {                
            return $ret;
        }

        if (!isset($ret['bp_randomizedbprefixstring']) || empty($ret['buddypress_tables'])) {
            return $ret;
        }
            
        $origin_bp_prefix = $this->getBuddyPressBasePrefix();
        $target_randomized_bp_prefix = $this->getBpRandomPrefix($ret);       
        if (!$origin_bp_prefix || !$target_randomized_bp_prefix) {
            return $ret;
        }      
        
        $buddypress_tables = $ret['buddypress_tables'];
        $current_prefix = $this->getSystemFunctions()->getDbPrefixOfSite($blogid_to_export);
        $random_prefix = $this->getSystemInitialization()->getRandomPrefix();
        
        $clean_tables_adjusted = [];
        $buddypress_tables_adjusted = [];
        
        foreach ($clean_tables as $clean_table) {            
            if (in_array($clean_table, $buddypress_tables)) {                
                $randomized = str_replace($origin_bp_prefix, $target_randomized_bp_prefix, $clean_table);
                $clean_tables_adjusted[] = $randomized;
                $buddypress_tables_adjusted[] = $randomized;                
            } else {
                $clean_tables_adjusted[] = str_replace($current_prefix, $random_prefix, $clean_table);
            }
        }
     
        $ret['exported_db_tables'] = $clean_tables_adjusted;
        $ret['buddypress_tables'] = $buddypress_tables_adjusted;
        
        return $ret;        
    }
    
    /**
     * Inject BuddyPress table prefix if implemented
     * Hooked to `prime_mover_inject_thirdparty_app_prefix`
     * @param array $ret
     * @param number $blogid_to_export
     * @param string $original_prefix
     * @return array
     */
    public function maybeInjectdBuddyPressTablePrefix($ret = [], $blogid_to_export = 0, $original_prefix = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        if (!is_multisite()) {
            $blogid_to_export = 1;
        }
        
        if (!is_array($ret) || !$blogid_to_export || !$original_prefix)  {
            return $ret;
        }
       
        if (!$this->maybeExportedSiteImplementsBuddyPress($blogid_to_export)) {
            return $ret;
        }
       
        if (!isset($ret['mayberandomizedbprefix'])) {
            return $ret;
        }
        
        $maybe_randomized = $ret['mayberandomizedbprefix'];
        if (!$maybe_randomized) {
            return $ret;
        }
        
        $original_prefix = strtolower($original_prefix);        
        $this->setBpRandomDbPrefix($original_prefix);
        $ret['bp_randomizedbprefixstring'] = $original_prefix;
        $ret['buddypress_tables'] = $this->getBuddyPressTables();
        
        return $ret;
    }
    
    /**
     * Append BuddyPress/BuddyBoss tables to database table export when implemented
     * Note blog is still not switched at this point.
     * Hooked to `prime_mover_tables_to_export`
     * @param array $tables
     * @param number $blogid_to_export
     * @param array $ret
     * @return array
     */
    public function maybeAppendBuddyPressTables($tables = [], $blogid_to_export = 0, $ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $tables;
        }
        
        if (!is_array($tables) || empty($tables) || !is_array($ret)) {
            return $tables;
        }
        
        if (!isset($ret['bp_randomizedbprefixstring']) || empty($ret['buddypress_tables'])) {
            return $tables;
        }

        $buddypress_tables = $ret['buddypress_tables'];   
        if ($this->maybeBasePrefixSameWithSite($blogid_to_export)) {
            return $tables;
        }
        
        if (is_array($buddypress_tables) && !empty($buddypress_tables)) {
            return array_merge($tables, $buddypress_tables);
        }
        
        return $tables;
    }
    
    /**
     * Get BuddyPress tables
     * @return string[]
     */
    protected function getBuddyPressTables()
    {
        global $wpdb;
      
        $db_search = "SHOW TABLES LIKE %s";        
        $prefixed_tables = [];
        foreach ($this->getTables() as $table) {
            $prefixed_table = $this->getBasePrefix() . $table;
            $sql = $wpdb->prepare($db_search , $prefixed_table);
            if ($wpdb->get_var($sql) === $prefixed_table) {
                $prefixed_tables[] = $prefixed_table;
            }            
        }
        
        return $prefixed_tables;        
    }
    
    /**
     * Initialize get base db prefix of site for BuddyPress sites
     * Hooked to `prime_mover_before_mysqldump_php` ACTION executed before PHP-MySQL dump process to set reusable object properties
     * @param array $ret
     * @return array
     */
    public function initializeGetBaseDbPrefixBP($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
        
        if (!isset($ret['bp_randomizedbprefixstring'])) {
            return $ret;
        }
        
        $this->getbaseprefix_bp = $this->getBuddyPressBasePrefix();
        return $ret;
    }
    
    /**
     * Get BuddyPress base prefix
     * @return string
     */
    public function getBuddyPressBasePrefix()
    {
        $base_prefix = $this->getBasePrefix();
        $prefix = $base_prefix;
        
        return $prefix;
    }
    
    /**
     * Initialize BuddyPress prefix on export
     * Hooked on `prime_mover_before_mysqldump_php`
     * @param array $ret
     * @return array
     */
    public function initializeBuddyPressRandomPrefix($ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $ret;
        }
      
        if (!isset($ret['bp_randomizedbprefixstring'])) {
            return $ret;
        }
        
        $this->bp_random_prefix_prop = $this->getBpRandomPrefix($ret);
        return $ret;
    }
    
    /**
     * Randomize BuddyPress dB Prefix data if implemented
     * Hooked on `prime_mover_filter_export_db_data`
     * @param string $data
     * @return string|mixed
     */
    public function randomizeBpDbPrefix($data = '')
    {                
        $origin_bp_prefix = $this->getBpBaseDbPrefix();
        $target_randomized_bp_prefix = $this->getBpRandomPrefixProp();
        
        if (!$origin_bp_prefix || !$target_randomized_bp_prefix) {
            return $data;
        }
        
        $search = "`$origin_bp_prefix";
        $replace = "`$target_randomized_bp_prefix";
        
        return str_replace($search, $replace, $data);
    }
        
    /**
     * Check if exported site implements BuddyPress that needs special processing.
     * @param number $blogid_to_export
     * @return boolean
     */
    protected function maybeExportedSiteImplementsBuddyPress($blogid_to_export = 0)
    {
        if (!is_multisite()) {
            $blogid_to_export = 1;
        }
        
        if (!$blogid_to_export) {
            return false;
        }
        
        $is_plugin_implemented = $this->isNetworkImplemented($blogid_to_export);
        if (!$is_plugin_implemented) {
            $is_plugin_implemented = $this->isSubSiteImplemented($blogid_to_export);
        }
        
        if (!$is_plugin_implemented) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Checks if base prefix is the same as site prefix
     * @param number $export_blog_id
     * @return boolean
     */
    protected function maybeBasePrefixSameWithSite($export_blog_id = 0)
    {
        if (!is_multisite()) {
            $export_blog_id = 1;
        }
        $prefix_of_site = $this->getSystemFunctions()->getDbPrefixOfSite($export_blog_id); 
        $base_prefix = $this->getBasePrefix();
        
        return ($prefix_of_site === $base_prefix);        
    }
    
    /**
     * Checks if BuddyPress / BuddyBoss is implemented on a network level.
     * @param number $blogid_to_export
     * @return boolean
     */
    protected function isNetworkImplemented($blogid_to_export = 0)
    {       
        $blogid_to_export = (int)$blogid_to_export;
        if (!$blogid_to_export) {
            return false;
        }
       
        if (!is_multisite()) {
            return false;
        }
        
        $bp_network_active = false;
        if ($this->getSystemFunctions()->isPluginActive($this->getBuddyPressPlugin(), true)) {
            $bp_network_active = true;
        }
        
        $bb_network_active = false;
        if ($this->getSystemFunctions()->isPluginActive($this->getBuddyBossPlugin(), true)) {
            $bb_network_active = true;
        }
        
        if (!$bp_network_active && !$bb_network_active) {
            return false;
        }
    
        $root_blog_id = 0;
        if (defined('BP_ROOT_BLOG') && BP_ROOT_BLOG) {
            $root_blog_id = (int)BP_ROOT_BLOG;
        }
        
        if ($root_blog_id === $blogid_to_export) {
            return true;
        }
                
        if (defined('BP_ENABLE_MULTIBLOG') && true === BP_ENABLE_MULTIBLOG) {
            return true;            
        }
             
        if ($this->getSystemFunctions()->isMultisiteMainSite($blogid_to_export, true)) { 
            return true;
        }
       
        return false;       
    }
    
    /**
     * Checks if BuddyPress / BuddyBoss is implemented on a subsite level.
     * @param number $blogid_to_export
     * @return boolean
     */
    protected function isSubSiteImplemented($blogid_to_export = 0)
    {
        if (!is_multisite()) {
            $blogid_to_export = 1;
        }
        
        $blogid_to_export = (int)$blogid_to_export;
        if (!$blogid_to_export) {
            return false;
        }
        
        $this->getSystemFunctions()->switchToBlog($blogid_to_export);
        $bp_active = false;
        if ($this->getSystemFunctions()->isPluginActive($this->getBuddyPressPlugin())) {
            $bp_active = true;
        }
        
        $bb_active = false;
        if ($this->getSystemFunctions()->isPluginActive($this->getBuddyBossPlugin())) {
            $bb_active = true;
        }
        
        if (!$bp_active && !$bb_active) {
            $this->getSystemFunctions()->restoreCurrentBlog();
            return false;
        }
        
        $this->getSystemFunctions()->restoreCurrentBlog();
        return true;
    }
    
    /**
     * Get base prefix
     * @return string
     */
    protected function getBasePrefix()
    {
        global $wpdb;        
        $base_prefix = $wpdb->base_prefix;
        return $base_prefix;
    }
    
    /**
     * Remove processor hooks when BuddyPress/BuddyBoss plugin is not activated
     * @param array $ret
     * @param number $blogid_to_import
     */    
    public function removeProcessorHooksWhenDependencyNotMeet($ret = [], $blogid_to_import = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, [$this->getBuddyPressPlugin(),$this->getBuddyBossPlugin()]);
        if (is_array($validation_error)) {
            foreach ($this->getCallBacks() as $callback => $priority) {
                if (!in_array($callback, $this->getForceProcessors())) {
                    remove_filter('prime_mover_do_process_thirdparty_data', [$this, $callback], $priority, 3);
                }                
            }
        }
    }    
}