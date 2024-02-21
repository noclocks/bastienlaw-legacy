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
 * Prime Mover WooCommerce Compatibility Class
 * Helper class for interacting with WooCommerce plugin
 *
 */
class PrimeMoverWooCommerceCompat
{     
    private $prime_mover;
    private $woocommerce_plugin;
    private $wc_order_posttype;
    private $callbacks;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->woocommerce_plugin = 'woocommerce/woocommerce.php';
        $this->wc_order_posttype = 'shop_order';
        
        $this->callbacks = [
            'maybeAdjustUserIdsCustomerLookup' => 10,
            'maybeAdjustUserIdsDownloadPermissions' => 11,
            'maybeAdjustUserIdsDownloadLog' => 12,
            'maybeAdjustUserIdsHposOrders' => 13,
            'maybeAdjustUserIdsApiKeys' => 14,
            'maybeAdjustUserIdsWebHooks' => 15,
            'maybeAdjustUserIdsPaymentTokens' => 16
        ];
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
     * Get order post type
     * @return string
     */
    public function getOrderPostType()
    {
        return $this->wc_order_posttype;
    }
    
    /**
     * Get WooCommerce plugin
     * @return string
     */
    public function getWooCommercePlugin()
    {
        return $this->woocommerce_plugin;
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
        add_action('prime_mover_before_post_author_update', [$this, 'maybeReconnectWcOrdersWithMigratedUsers'], 10, 6);        
        foreach ($this->getCallBacks() as $callback => $priority) {
            add_filter('prime_mover_do_process_thirdparty_data', [$this, $callback], $priority, 3);
        }
        
        add_action('prime_mover_before_thirdparty_data_processing', [$this, 'removeProcessorHooksWhenDependencyNotMeet'], 10, 2); 
        add_action('wp_redirect', [$this, 'forceRedirectToPermalinkPage'], 9999999999, 1);
    } 

    /**
     * Force redirect to permalinks page after restore
     * To make sure - user will be able to re-saved permalinks.
     * @param string $location
     * @return string
     */
    public function forceRedirectToPermalinkPage($location = '')
    {
        if (!is_admin() || !$location) {
            return $location;
        }
       
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return $location;
        }        
        
        if (wp_doing_ajax() ) {
            return $location;
        }
        
        if (false !== strpos($location, 'options-permalink.php')) {
            return $location;
        }
        
        $args = [
            'prime_mover_force_redirect_to_permalinks' => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(), 
            'prime_mover_force_redirect_nonce' => $this->getPrimeMover()->getSystemInitialization()->getPrimeMoverSanitizeStringFilter(), 
            'prime_mover_target_blogid' => FILTER_SANITIZE_NUMBER_INT            
        ];
        
        $params = $this->getPrimeMover()->getSystemInitialization()->getUserInput('get', $args, 'force_permalink_redirect', 'import', 0, true);
        if (!is_array($params) || empty($params)) {
            return $location;
        }
        
        $set = false;
        if (isset($params['prime_mover_force_redirect_to_permalinks'], $params['prime_mover_force_redirect_nonce'], $params['prime_mover_target_blogid'])) {
            $set = true;
        }
        
        if (!$set) {
            return $location;
        }
        $blog_id = (int)$params['prime_mover_target_blogid'];
        $nonce = $params['prime_mover_force_redirect_nonce'];
        $force_redirect = $params['prime_mover_force_redirect_to_permalinks'];
        
        if ('yes' === $force_redirect && $this->getSystemFunctions()->primeMoverVerifyNonce($nonce, 'prime_mover_force_redirect_to_permalinks') && $blog_id) {           
            $location = $this->getPrimeMover()->getSystemChecks()->getSystemCheckUtilities()->getSystemUtilities()->generateUrlToPermalinksPage($blog_id, false);
        }
           
        return $location;
    }
    
    /**
     * Remove processor hooks when plugin not activated
     * @param array $ret
     * @param number $blogid_to_import
     */
    public function removeProcessorHooksWhenDependencyNotMeet($ret = [], $blogid_to_import = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getWooCommercePlugin());
        if (is_array($validation_error)) {
            foreach ($this->getCallBacks() as $callback => $priority) {
                remove_filter('prime_mover_do_process_thirdparty_data', [$this, $callback], $priority, 3);
            }
        }
    }
    
    /**
     * Adjust customer Ids in payment tokens table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsPaymentTokens($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getWooCommercePlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
  
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'woocommerce_payment_tokens';
        $leftoff_identifier = '3rdparty_paymenttokens_leftoff';
        
        $primary_index = 'token_id';
        $column_strings = 'token_id, user_id';
        $update_variable = '3rdparty_paymenttokens_log_updated';
        
        $progress_identifier = 'payment tokens table';  
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import); 
        $handle_unique_constraint = '';
                
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }
    
    /**
     * Adjust customer Ids in WC webhooks table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsWebHooks($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getWooCommercePlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'wc_webhooks';
        $leftoff_identifier = '3rdparty_webhooks_leftoff';
        
        $primary_index = 'webhook_id';
        $column_strings = 'webhook_id, user_id';
        $update_variable = '3rdparty_webhooks_log_updated';
        
        $progress_identifier = 'web hooks table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = '';
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }
 
    /**
     * Adjust customer Ids in WC new HPOS Order table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsHposOrders($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getWooCommercePlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
       
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'wc_orders';
        $leftoff_identifier = '3rdparty_hpos_orders_leftoff';
       
        $primary_index = 'id';
        $column_strings = 'id, customer_id';
        $update_variable = '3rdparty_hpos_orders_log_updated';
        
        $progress_identifier = 'HPOS orders table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = '';
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }
    
    /**
     * Adjust customer Ids in WC API keys table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsApiKeys($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getWooCommercePlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'woocommerce_api_keys';
        $leftoff_identifier = '3rdparty_apikeys_leftoff';
        
        $primary_index = 'key_id';
        $column_strings = 'key_id, user_id';
        $update_variable = '3rdparty_apikeys_log_updated';
        
        $progress_identifier = 'api keys table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = '';
                
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }
    
    /**
     * Adjust customer Ids in WC download log table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsDownloadLog($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getWooCommercePlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'wc_download_log';
        $leftoff_identifier = '3rdparty_dl_log_leftoff';
        
        $primary_index = 'download_log_id';
        $column_strings = 'download_log_id, user_id';
        $update_variable = '3rdparty_dl_log_updated';
        
        $progress_identifier = 'download log table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = '';       
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }
    
    /**
     * Adjust customer Ids in WC download permissions table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsDownloadPermissions($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getWooCommercePlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'woocommerce_downloadable_product_permissions';
        $leftoff_identifier = '3rdparty_cust_dl_leftoff';
        
        $primary_index = 'permission_id';
        $column_strings = 'permission_id, user_id';
        $update_variable = '3rdparty_cust_dl_updated';
        
        $progress_identifier = 'download permissions table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = ''; 
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }
    
    /**
     * Adjust customer Ids in WC customer lookup table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsCustomerLookup($ret = [], $blogid_to_import = 0, $start_time = 0)
    {        
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getWooCommercePlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__; 
        $table = 'wc_customer_lookup';
        $leftoff_identifier = '3rdparty_customers_leftoff';
        
        $primary_index = 'customer_id';
        $column_strings = 'customer_id, user_id';
        $update_variable = '3rdparty_customers_updated';
        
        $progress_identifier = 'customers lookup table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = 'user_id';
         
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }
            
    /**
     * Maybe reconnect WC Orders with migrated users
     * Hooked to `prime_mover_before_post_author_update` action.
     * @param number $post_id
     * @param number $post_author
     * @param number $new_author
     * @param array $ret
     * @param SplFixedArray $user_equivalence
     * @param string $post_type
     */
    public function maybeReconnectWcOrdersWithMigratedUsers($post_id = 0, $post_author = 0, $new_author = 0, $ret = [], SplFixedArray $user_equivalence = null, $post_type = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        if (!isset($ret['imported_package_footprint']['plugins'][$this->getWooCommercePlugin()])) {
            return;
        }
           
        if (!$post_type || $this->getOrderPostType() !== $post_type) {
            return;
        }
        
        $unadjusted_customerid = get_post_meta($post_id, '_customer_user', true);
        $unadjusted_customerid = (int)$unadjusted_customerid;
        if (!$unadjusted_customerid) {
            return;
        }
        
        if (!isset($user_equivalence[$unadjusted_customerid])) {
       
            return;
        }
        
        $migrated_customer_id = (int)$user_equivalence[$unadjusted_customerid];
        if (!$migrated_customer_id) {
            return;
        }
        
        if ($unadjusted_customerid === $migrated_customer_id) {
            return;
        }
                      
        update_post_meta($post_id, '_customer_user', $migrated_customer_id);        
    }
}