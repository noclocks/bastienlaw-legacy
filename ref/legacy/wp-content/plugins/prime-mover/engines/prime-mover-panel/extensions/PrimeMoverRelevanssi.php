<?php
namespace Codexonics\PrimeMoverFramework\extensions;

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
 * Prime Mover Relevanssi Compatibility Class
 * Helper class for interacting with Relevanssi plugin
 *
 */
class PrimeMoverRelevanssi
{     
    private $prime_mover;
    private $relevanssi_plugin;
    private $callbacks;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->relevanssi_plugin = 'relevanssi/relevanssi.php';
        
        $this->callbacks = [
            'maybeAdjustUserIdsLogTable' => 504
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
     * Get Relevanssi plugin
     * @return string
     */
    public function getRelevanssiPlugin()
    {
        return $this->relevanssi_plugin;
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
    }   
    
    /**
     * Remove processor hooks when not activated
     * @param array $ret
     * @param number $blogid_to_import
     */
    public function removeProcessorHooksWhenDependencyNotMeet($ret = [], $blogid_to_import = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getRelevanssiPlugin());
        if (is_array($validation_error)) {
            foreach ($this->getCallBacks() as $callback => $priority) {
                remove_filter('prime_mover_do_process_thirdparty_data', [$this, $callback], $priority, 3);
            }
        }
    }

    /**
     * Adjust user IDs in log table
     * Hooked to `prime_mover_do_process_thirdparty_data` filter, priority 504
     * @param array $ret
     * @param number $blogid_to_import
     * @param number $start_time
     * @return array
     */
    public function maybeAdjustUserIdsLogTable($ret = [], $blogid_to_import = 0, $start_time = 0)
    {
        $validation_error = apply_filters('prime_mover_validate_thirdpartyuser_processing', $ret, $blogid_to_import, $this->getRelevanssiPlugin());
        if (is_array($validation_error)) {
            return $validation_error;
        }
        
        if (!empty($ret['3rdparty_current_function']) && __FUNCTION__ !== $ret['3rdparty_current_function']) {
            return $ret;
        }
        
        $ret['3rdparty_current_function'] = __FUNCTION__;
        $table = 'relevanssi_log';
        $leftoff_identifier = "3rdparty_{$table}_leftoff";
        
        $primary_index = 'id';
        $column_strings = 'id, user_id';
        $update_variable = "3rdparty_{$table}_updated";
        
        $progress_identifier = 'Relevanssi log table';
        $last_processor = apply_filters('prime_mover_is_thirdparty_lastprocessor', false, $this, __FUNCTION__, $ret, $blogid_to_import);
        $handle_unique_constraint = '';
        
        return apply_filters('prime_mover_process_userid_adjustment_db', $ret, $table, $blogid_to_import, $leftoff_identifier, $primary_index, $column_strings,
            $update_variable, $progress_identifier, $start_time, $last_processor, $handle_unique_constraint);
    }   
}