<?php
/**
 *
 * This is the PHP core version dependency class, purpose is to manage required PHP version checks.
 *
 */
class PrimeMoverPHPVersionDependencies
{
    /**
     * PHP Version
     * @var string
     */
    private $php = '5.6';

    /**
     * Constructor
     * @param string $minimum_version
     */
    public function __construct($minimum_version = '')
    {
        $this->php = $minimum_version;        
    }    
    
    /**
     * Checks if minimum PHP version is meet
     * @return boolean
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsMultisite()
     */
    public function phpPasses()
    {
        $notice_hook = 'admin_notices';
        if (is_multisite()) {
            $notice_hook = 'network_admin_notices';
        }
        if ($this->phpAtLeast($this->php)) {
            return true;
        } else {
            add_action($notice_hook, array( $this, 'phpVersionNotice'));
            return false;
        }
    }
    
    /**
     * Gets PHP version
     * @return string
     * @compatible 5.6
     */
    protected function getphpversion()
    {
        return phpversion();
    }
    
    /**
     * Compare PHP versions
     * @param string $min_version
     * @return mixed
     * @compatible 5.6
     */
    private function phpAtLeast($min_version = '')
    {
        $phpversion = $this->getphpversion();
        return version_compare($phpversion, $min_version, '>=');
    }
    
    /**
     * Report non-compliant PHP version to user
     * @compatible 5.6
     */
    public function phpVersionNotice()
    {
        ?>
        <div class="error">
            <p>
            <?php 
            printf( esc_html__( 'The %s plugin cannot run on PHP versions older than %s. Please contact your host and ask them to upgrade.', 'prime-mover'),
                '<strong>' . esc_html(PRIME_MOVER_PLUGIN_CODENAME) . '</strong>', $this->php ); 
            ?>
            </p>
        </div>
        <?php 
    }
}
