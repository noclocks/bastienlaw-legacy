<?php
/**
 * 
 * This is the final system dependency class, purpose is to manage file-system permission checks.
 *
 */
class PrimeMoverFileSystemDependencies
{
    /**
     * Paths that dont have permission to write
     * @var array
     */
    private $problematic_paths = array();
    
    /**
     * Paths that require permission to write
     * @var array
     */
    private $required_allowed_permission_paths = array();
 
    /**
     * Constructor
     */
    public function __construct( $required_paths = array() ) 
    {
        $this->required_allowed_permission_paths = $required_paths;
    }
    
    /**
     * Gets required permission paths
     * @return array
     * @compatible 5.6
     */
    public function getRequiredPermissionPaths()
    {
        return $this->required_allowed_permission_paths;
    }
    
    /**
     * Checks file system permission issues
     * @compatible 5.6
     */
    private function checkFileSystemPermissionIssues()
    {
        foreach ($this->getRequiredPermissionPaths() as $path) {
            if ($path === WPMU_PLUGIN_DIR && true !== wp_mkdir_p($path)) {
                $this->problematic_paths[] = $path;
            } elseif ($this->isValidPath($path) && $this->wpIsNotWritable($path)) {
                $this->problematic_paths[] = $path;
            }
        }
    }
    
    protected function wpIsNotWritable($path)
    {
       return !wp_is_writable($path);        
    }
    
    /**
     * Checks if directory
     * @param string $path
     * @return boolean
     */
    protected function isValidPath($path = '')
    {
        return (is_dir($path) || is_file($path) );
    }
    
    /**
     * Get paths having permission issues
     * @return array
     * @compatible 5.6
     */
    public function getProblematicPaths()
    {
        return $this->problematic_paths;
    }
    
    /**
     * File system permission checks
     * @return boolean
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsMultisite()
     */
    public function fileSystemPermissionsRequisiteCheck()
    {
        $notice_hook = 'admin_notices';
        if (is_multisite()) {
            $notice_hook = 'network_admin_notices';
        }
        
        $this->checkFileSystemPermissionIssues();        
        $problematic_paths = $this->getProblematicPaths();
        
        if ( ! empty( $problematic_paths ) ) {
            add_action($notice_hook, array( $this, 'warnIncorrectPermissions'));
            return false;
        } else {
            return true;
        }
    }
     
    /**
     * Warn user of incorrect permissions
     * @compatible 5.6
     */
    public function warnIncorrectPermissions()
    {
        $problematic_paths = $this->getProblematicPaths();
        if ( empty($problematic_paths) || ! is_array($problematic_paths)) {
            return;
        }
        ?>
        <div class="error">        
         <p><?php printf( esc_html__( 'The %s plugin cannot be activated if the following paths were not writable by WordPress', 'prime-mover' ), 
             '<strong>' . esc_html(PRIME_MOVER_PLUGIN_CODENAME) . '</strong>' )?>:</p>
            <ul>
                <?php 
                foreach ( $this->getProblematicPaths() as $path ) {
                ?>
                    <li><strong><?php echo $path;?></strong></li>
                <?php    
                }
                ?>
            </ul>
             <?php 
             $text = esc_html__('', 'prime-mover');            
             echo sprintf(esc_html__('%s Please contact your web hosting provider or request to make these paths writable.', 'prime-mover' ), $text); ?></p>
            </div>
    <?php
    }
}