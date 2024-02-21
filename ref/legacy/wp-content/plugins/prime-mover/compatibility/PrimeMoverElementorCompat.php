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
 * Prime Mover Elementor Compatibility Class
 * Helper class for interacting with Elementor plugin
 *
 */
class PrimeMoverElementorCompat
{     
    private $prime_mover;
    private $elementor_plugin;
    
    /**
     * Construct
     * @param PrimeMover $prime_mover
     * @param array $utilities
     */
    public function __construct(PrimeMover $prime_mover, $utilities = [])
    {
        $this->prime_mover = $prime_mover;
        $this->elementor_plugin = 'elementor/elementor.php';
    }
    
    /**
     * Get elementor plugin
     * @return string
     */
    public function getElementorPlugin()
    {
        if (defined('PRIME_MOVER_CUSTOM_ELEMENTOR_PATH') && PRIME_MOVER_CUSTOM_ELEMENTOR_PATH && 
            $this->getSystemFunctions()->nonCachedFileExists(PRIME_MOVER_PLUGIN_CORE_PATH . PRIME_MOVER_CUSTOM_ELEMENTOR_PATH)) {
                return PRIME_MOVER_CUSTOM_ELEMENTOR_PATH;
        }
        return $this->elementor_plugin;
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
     * Initialize hooks
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverElementorCompat::itAddsInitHooks() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverElementorCompat::itChecksIfHooksAreOutdated()
     */
    public function initHooks()
    {
        add_action('prime_mover_after_actual_import', [$this, 'maybeRefreshElementorCache'], 999, 2);
    } 
    
    /**
     * Maybe refresh Elementor cache after site migration
     * This should work for both subsite and single site migration
     * Only if Elementor is active
     * @param array $ret
     * @param number $blogid_to_import
     */
    public function maybeRefreshElementorCache($ret = [], $blogid_to_import = 0)
    {        
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        if (!$blogid_to_import) {
            return;
        }
        $this->getSystemFunctions()->switchToBlog($blogid_to_import); 
        $elementor_instance = $this->getElementorInstance();
        if (!is_object($elementor_instance)) {
            $this->getSystemFunctions()->restoreCurrentBlog(); 
           return; 
        }
        $elementor_files_manager = $this->getElementorFilesManager($elementor_instance);
        if (!is_object($elementor_instance)) {
            $this->getSystemFunctions()->restoreCurrentBlog(); 
            return;
        }
        if (!method_exists($elementor_files_manager, 'clear_cache')) {
            $this->getSystemFunctions()->restoreCurrentBlog(); 
            return;
        }
                   
        $elementor_files_manager->clear_cache();        
        do_action('prime_mover_log_processed_events', 'Refreshed Elementor Cache since this site uses Elementor plugin.', $blogid_to_import, 'import', __FUNCTION__, $this);
        $this->getSystemFunctions()->restoreCurrentBlog();        
    }
    
    /**
     * Get Elementor instance if activated
     * @return \Elementor\Plugin|NULL
     */
    protected function getElementorInstance()
    {
        if (did_action('elementor/loaded')) {
            return \Elementor\Plugin::instance();
        } 
        
        if (!$this->getSystemFunctions()->isPluginActive($this->getElementorPlugin())) {            
            return null;
        }         
        $elementor = PRIME_MOVER_PLUGIN_CORE_PATH . $this->getElementorPlugin();
        if (!$this->getSystemFunctions()->nonCachedFileExists($elementor, true)) {
            return null;
        }
        if (!class_exists('\Elementor\Plugin')) {
            require_once($elementor);           
        }
        if (!class_exists('\Elementor\Plugin')) {
            return null;
        }
        $instance = \Elementor\Plugin::instance();
        if (is_object($instance)) {
            $instance->init();
            return $instance;
        } else {
            return null;
        }        
    }

    /**
     * Get elementor files manager
     * @param \Elementor\Plugin $elementor
     * @return \Elementor\Core\Files\Manager|NULL
     */
    protected function getElementorFilesManager(\Elementor\Plugin $elementor)
    {
        if (isset($elementor->files_manager)) {
            return $elementor->files_manager;
        }
        return null;
    }    
}