<?php
/**
 *
 * This is the PHP core function dependency class, purpose is to manage required PHP core function checks.
 *
 */
class PrimeMoverPHPCoreFunctionDependencies
{
    /**
     * Required core functions
     * @var array
     */
    private $required_core_functions = array('readfile', 'extension_loaded', 'file_get_contents', 'file_put_contents', 'fopen', 'mysqli_connect', 
        'stream_copy_to_stream', 'openssl_encrypt', 'openssl_cipher_iv_length', 'openssl_random_pseudo_bytes', 
        'hash_hmac', 'openssl_decrypt', 'base64_encode', 'stream_bucket_make_writeable', 'stream_bucket_append', 'stream_filter_register'        
    );
    
    /**
     * Required core extensions
     * @var array
     */
    private $required_extensions = array('mbstring', 'pdo_mysql', 'ctype');
    
    /**
     * Missing functions
     * @var array
     */
    private $missing_functions = array();
    
    /**
     * Missing extensions
     * @var array
     */
    private $missing_extensions = array();
    
    /**
     * Gets required core functions
     * @return array
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverPHPCoreFunctionDependencies::itGetsAllRequiredCoreFunctions()
     */
    public function getRequiredCoreFunctions()
    {
        return $this->required_core_functions;
    }
    
    /**
     * Gets required core extensions
     * @return array|string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverPHPCoreFunctionDependencies::itGetsAllRequiredCoreExtensions()
     */
    public function getRequiredCoreExtensions()
    {
        return $this->required_extensions;
    }
    
    /**
     * Gets missing functions
     * @return array
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite() 
     */
    public function getMissingFunctions()
    {
        return $this->missing_functions;
    }

    /**
     * get missing extensions
     * @return array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite()
     */
    public function getMissingExtensions()
    {
        return $this->missing_extensions;
    }
    
    /**
     * Checks if function exist
     * @param string $function
     * @compatible 5.6
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverPHPCoreFunctionDependencies::itChecksIfFunctionsExist()
     */
    protected function isFunctionExist($function = '')
    {
        if ( function_exists($function) ) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Check if extension exist
     * @param string $extension
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverPHPCoreFunctionDependencies::itChecksIfExtensionsLoaded() 
     */
    protected function doExtensionExist($extension = '')
    {        
        if (extension_loaded($extension)) {
            return true;
        } else {
            return false;
        }        
    }
    
    /**
     * Sets missing functions
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite()
     */
    private function checkMissingFunctions()
    {
        foreach ( $this->getRequiredCoreFunctions() as $function ) {
            if ( ! $this->isFunctionExist($function) ) {
                $this->missing_functions[] = $function;
            }
        }
    }
    
    /**
     * Check missing extensions
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite()
     */
    private function checkMissingExtensions()
    {
        foreach ( $this->getRequiredCoreExtensions() as $extension ) {
            if ( ! $this->doExtensionExist($extension) ) {
                $this->missing_extensions[] = $extension;
            }
        }
    }
    
    /**
     * Checks all internal PHP core functions and if enabled
     * @return boolean
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsMultisite()
     */
    public function functionRequisiteCheck()
    {
        $notice_hook = 'admin_notices';
        if (is_multisite()) {
            $notice_hook = 'network_admin_notices';
        }
        $this->checkMissingFunctions();
        $missing_functions = $this->getMissingFunctions();
        if ( ! empty( $missing_functions ) ) {
            add_action($notice_hook, array( $this, 'missingCoreFunctions'));
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Check required extensions
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite()
     */
    public function extensionsRequisiteCheck()
    {
        $notice_hook = 'admin_notices';
        if (is_multisite()) {
            $notice_hook = 'network_admin_notices';
        }
        $this->checkMissingExtensions();
        $missing_extensions = $this->getMissingExtensions();
        if ( ! empty($missing_extensions) ) {
            add_action($notice_hook, array( $this, 'missingCoreExtensions'));
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Displays network admin notice for missing core functions
     * @compatible 5.6
     */
    public function missingCoreFunctions()
    {
        $missing_functions = $this->getMissingFunctions();
        if ( empty( $missing_functions ) ) {
            return;
        }
        ?>
        <div class="error">        
         <p><?php printf( esc_html__( 'The %s plugin cannot be activated if these following PHP core functions are missing', 'prime-mover' ), 
             '<strong>' . esc_html(PRIME_MOVER_PLUGIN_CODENAME) . '</strong>' )?>:</p>
            <ul>
                <?php 
                foreach ( $this->missing_functions as $function ) {
                ?>
                    <li><strong><?php echo $function;?>()</strong></li>
                <?php    
                }
                ?>
            </ul>
         <p><?php esc_html_e('Please contact your web hosting provider to enable these required PHP functions for you.', 'prime-mover' ); ?></p>
        </div>
    <?php
    }
    
    /**
     * Displays network admin notice for missing core extensions
     * @compatible 5.6
     */
    public function missingCoreExtensions()
    {
        $missing_extensions = $this->getMissingExtensions();
        if ( empty($missing_extensions) ) {
            return;
        }
        ?>
        <div class="error">        
         <p><?php printf( esc_html__( 'The %s plugin cannot be activated if these following PHP core extensions are missing', 'prime-mover' ), 
             '<strong>' . esc_html(PRIME_MOVER_PLUGIN_CODENAME) . '</strong>' )?>:</p>
            <ul>
                <?php 
                foreach ( $this->missing_extensions as $extension ) {
                ?>
                    <li><strong><?php echo $extension;?></strong></li>
                <?php    
                }
                ?>
            </ul>
         <p><?php esc_html_e('Please contact your web hosting provider to enable these required PHP extensions for you.', 'prime-mover' ); ?></p>
        </div>
    <?php
    }
}
