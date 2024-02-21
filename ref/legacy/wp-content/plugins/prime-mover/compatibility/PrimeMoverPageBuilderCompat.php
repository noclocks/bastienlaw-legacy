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

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover Page Builders Compatibility Class
 * Helper class for interacting with different page builders encoded data
 * This is a non-user adjustment implementation
 *
 */
class PrimeMoverPageBuilderCompat
{     
    private $prime_mover;
    private $tagdiv_plugin;
    private $callbacks;
    private $leftoff_identifier;
    private $replaceables;
    private $system_utilities;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->tagdiv_plugin = 'td-composer/td-composer.php';
        
        $this->callbacks = [
            'maybeAdjustTagDivEncodedData' => 35            
        ];
        $this->leftoff_identifier = '3rdparty_tagdiv_leftoff';
        $this->replaceables = [        
            'wpupload_url_alternative',
            'generic_legacy_upload_scheme',
            'generic_alternative_upload_scheme',
            'generic_upload_scheme',
            'alt_wpcontent_urls',
            'generic_content_scheme',
            'generic_domain_scheme',
            'generic_alt_domain_scheme',
            'scheme_replace',
            'scheme_replace_domain_current_site',
            'relative_upload_scheme',
            'relative_content_schem'
         ];
        $this->system_utilities = $utilities['sys_utilities'];
    }
    
    /**
     * Get system utilities
     * @return array
     */
    public function getSystemUtilities()
    {
        return $this->system_utilities;
    }
    
    /**
     * Get replaceables
     * @return string[]
     */
    public function getReplaceables()
    {
        return $this->replaceables;
    }
    
    /**
     * Get left off identifier
     * @return string
     */
    public function getLeftOffIdentifier()
    {
        return $this->leftoff_identifier;
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
     * Get tagdiv plugin
     * @return string
     */
    public function getTagDivPlugin()
    {
        return $this->tagdiv_plugin;
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
     * Get hooked methods
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverHookedMethods
     */
    public function getPrimeMoverHookedMethods()
    {
        return $this->getPrimeMover()->getHookedMethods();
    }
    
    /**
     * Get progess handlers
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverProgressHandlers
     */
    public function getProgressHandlers()
    {
        return $this->getPrimeMoverHookedMethods()->getProgressHandlers();
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
        add_filter('prime_mover_non_user_adjustment_select_query', [$this, 'seekPostToUpdateQuery'], 10, 6);
        add_filter('prime_mover_non_user_adjustment_update_data', [$this, 'updateEncodedPageBuilderEncodedData'], 10, 7);
        
        add_filter('prime_mover_filter_export_footprint', [$this, 'maybeExportingThemeLessSite'], 500, 2);
        add_action('prime_mover_after_actual_import', [$this, 'maybeDisableThemeIfThemeLessSite'], 10, 2);
    }    
    
    /**
     * Maybe disable theme is theme less restoration
     * @param array $ret
     * @param number $blogid_to_import
     */
    public function maybeDisableThemeIfThemeLessSite($ret = [], $blogid_to_import = 0)
    {
        if ($this->getSystemUtilities()->isRestoringThemeLessPackage($ret) && $blogid_to_import) {
            $this->getPrimeMover()->getSystemChecks()->deactivateThemeOnSpecificSite($blogid_to_import);
        }
    }
    
    /**
     * Check if we are exporting theme-less site (page builders that disables theme)
     * @param array $export_system_footprint
     * @param array $ret
     * @return string
     */
    public function maybeExportingThemeLessSite($export_system_footprint = [], $ret = [])
    {
        if (empty($this->getPrimeMover()->getExporter()->getThemesToExport($export_system_footprint, false))) {
            $export_system_footprint['themeless'] = 'yes';
        }
        
        return $export_system_footprint;
    }
    
    /**
     * Update encoded page builder data
     * @param number $primary_index_ids
     * @param string $value
     * @param string $table
     * @param string $primary_index
     * @param string $user_id_column
     * @param string $leftoff_identifier
     * @param array $ret
     * @return array $ret
     * Hooked to `prime_mover_non_user_adjustment_update_data`
     */
    public function updateEncodedPageBuilderEncodedData($primary_index_id = 0, $value = '', $table = '', $primary_index = '', $user_id_column = '', $leftoff_identifier = '', $ret = [])
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $primary_index_id;
        } 
        
        if ($this->getLeftOffIdentifier() !== $leftoff_identifier) {
            return $primary_index_id;
        }
        
        if (!isset($ret['prime_mover_final_replaceables'])) {
            return $primary_index_id;
        }
        
        $post_id = (int)$primary_index_id;
        $post_content = $value;
        $post_content_orig = $post_content;
        
        $matches = [];
        $encoded = [];
        preg_match_all("/tdc_css=\"(\S+)\"/", $post_content, $matches);
        if (isset($matches[1])) {
            $encoded = $matches[1];
        }
        $replaceables = [];
        $srch_replaceables = $ret['prime_mover_final_replaceables'];
        $srch_element = '';
        $rplc_elements = '';
        
        foreach ($encoded as $data) {
            $decoded = base64_decode($data);  
            $replaced = $decoded;
            foreach ($this->getReplaceables() as $src_key) {
                if (!isset($srch_replaceables[$src_key])) {
                    continue;
                }
                
                if (!isset($srch_replaceables[$src_key]['search']) || !isset($srch_replaceables[$src_key]['replace'])) {
                    continue;
                }
                
                $srch_element = $srch_replaceables[$src_key]['search'];
                $rplc_elements = $srch_replaceables[$src_key]['replace'];
                
                $replaced = str_replace($srch_element, $rplc_elements, $replaced);                
            }
            
            if ($decoded === $replaced) {
                continue;
            }
            
            $encoded_fix = base64_encode($replaced);
            $replaceables[$data] = $encoded_fix;            
        }
        
        foreach ($replaceables as $search => $replace) {
            $post_content = str_replace($search, $replace, $post_content);
        }
        
        if ($post_content_orig === $post_content) {
            return $primary_index_id;
        }
        
        global $wpdb;
        $query = $wpdb->prepare("
            UPDATE $wpdb->posts
            SET post_content = %s
            WHERE ID = %d",
            $post_content, $post_id
        );
       
        return $this->getUserQueries()->updateCustomerUserIdBySQL($post_id, 0, '', '', '', $query);
    }
    
    /**
     * Seek posts to update query
     * @param string $query
     * @param array $ret
     * @param string $leftoff_identifier
     * @param string $table
     * @param string $primary_index
     * @param string $column_strings
     * @return string
     */
    public function seekPostToUpdateQuery($query = '', $ret = [], $leftoff_identifier = '', $table = '', $primary_index = '', $column_strings = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $query;
        } 
        
        if ($this->getLeftOffIdentifier() !== $leftoff_identifier) {
            return $query;
        }
        
        global $wpdb;
        $postcontent_column = $this->getUserQueries()->parsePrimaryIndexUserColumns($column_strings, 'user');
        $where = "WHERE {$postcontent_column} LIKE '%tdc_css=\"%' AND post_status = 'publish'";
        
        $left_off = 0;
        if (isset($ret[$leftoff_identifier])) {
            $left_off = $ret[$leftoff_identifier];
        }
        
        if ($left_off) {
            $where .= $wpdb->prepare(" AND {$primary_index} < %d", $left_off);
        }
        
        $orderby = $wpdb->prepare("ORDER BY {$primary_index} DESC LIMIT %d", PRIME_MOVER_NON_USER_ADJUSTMENT_LOOKUP_LIMIT);        
        $sql = "SELECT {$column_strings} FROM {$wpdb->prefix}{$table} {$where} {$orderby}";
                
        return $sql;
    }
    
    /**
     * Remove processor hooks when plugin not activated
     * @param array $ret
     * @param number $blogid_to_import
     */
    public function removeProcessorHooksWhenDependencyNotMeet($ret = [], $blogid_to_import = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getTagDivPlugin());
        if (is_array($validation_error)) {
            foreach ($this->getCallBacks() as $callback => $priority) {
                remove_filter('prime_mover_do_process_thirdparty_data', [$this, $callback], $priority, 3);
            }
        }
    }
    
    /**
     * Maybe adjust tag div encoded data
     * Hooked to `prime_mover_do_process_thirdparty_data` filter - priority 35
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustTagDivEncodedData($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getTagDivPlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
  
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'posts';
        $leftoff_identifier = $this->getLeftOffIdentifier();
        
        $primary_index = 'ID';
        $column_strings = 'ID, post_content';
        $update_variable = '3rdparty_tagdiv_log_updated';
        
        $progress_identifier = 'tagdiv encoded data';  
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import); 
        $handle_unique_constraint = '';
        $non_user_adjustment = true;
                
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint, $non_user_adjustment);
    }
}