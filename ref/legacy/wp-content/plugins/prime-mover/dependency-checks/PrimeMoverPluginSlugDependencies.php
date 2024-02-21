<?php
/**
 *
 * This is the requirement for Prime Mover plugin folder name to exactly use /prime-mover/ or /prime-mover-pro/ slug.
 * Otherwise it cannot be activated.
 *
 */
class PrimeMoverPluginSlugDependencies
{
    /**
     * The only allowed plugin folder name
     * @var array
     */
    private $slugs = array();

    /**
     * Constructor
     * @param array $slugs
     */
    public function __construct($slugs = array())
    {
        $this->slugs = $slugs;        
    }    
    
    /**
     * Get allowed slugs
     * @return array|string[]
     */
    public function getAllowedPluginSlugs()
    {
        return $this->slugs;
    }
    
    /**
     * Checks if minimum PHP version is meet
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsMultisite()
     */
    public function slugPasses()
    {
        //Get plugin foldername currently being used now
        $plugin_basename = plugin_basename(PRIME_MOVER_MAINPLUGIN_FILE);
        
        //Get the only allowed plugin slugs
        $allowed_slugs = $this->getAllowedPluginSlugs();
   
        //Define notice hook
        $notice_hook = 'admin_notices';
        if (is_multisite()) {
            $notice_hook = 'network_admin_notices';
        }
        
        //Check if passes
        if (in_array($plugin_basename, $allowed_slugs, true)) {
            return true;
        } else {
            add_action($notice_hook, array( $this, 'incompatibleSlugNotice'));
            return false;
        }
    }
    
    /**
     * Report non-compliant plugin slug to user.
     */
    public function incompatibleSlugNotice()
    {
        ?>
        <div class="error">
            <p>
            <?php 
            printf( esc_html__( 'The %s plugin cannot be activated if it is using different plugin folder name other than the defaults. Please rename plugin folder name to :', 
                'prime-mover'), '<strong>' . esc_html(PRIME_MOVER_PLUGIN_CODENAME) . '</strong>'); 
            ?>
            </p>
            <ul>
            <?php 
            foreach ($this->getAllowedPluginSlugs() as $slug) {
                $pro = false;
                if (false !== strpos($slug, 'prime-mover-pro/')) {
                    $pro = true;
                } 
                if ($pro) {
            ?>
                <li><strong><?php echo 'prime-mover-pro'; ?></strong> - <?php esc_html_e( 'if you have a pro version copy that is under active subscription, under active trial or expired subscription.', 'prime-mover'); ?></li> 
                <li><strong><?php echo 'prime-mover'; ?></strong> - <?php esc_html_e( 'if you have a free version copy that comes from WordPress.org.', 'prime-mover'); ?></li>      
            <?php 
                }    
            }
            ?>
            </ul>
            <p>
            <?php esc_html_e( 'After renaming the plugin folder name, re-activate again. Thank you', 'prime-mover'); ?> !
            </p>
        </div>
        <?php 
    }
}
