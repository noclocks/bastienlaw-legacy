<?php
/**
 *
 * This is the overall requirements check class.
 *
 */
class PrimeMoverRequirementsCheck
{    
    /**
     * 
     * @var PrimeMoverWPCoreDependencies
     */
    private $corewpdependencies;
    
    /**
     * 
     * @var PrimeMoverPHPVersionDependencies
     */
    private $phpversiondependencies;
    
    /**
     * 
     * @var PrimeMoverPHPCoreFunctionDependencies
     */
    private $phpfuncdependency;
    
    /**
     * 
     * @var PrimeMoverFileSystemDependencies
     */
    private $filesystem_dependency;
    
    /**
     *
     * @var PrimeMoverPluginSlugDependencies
     */
    private $foldernamedependency;
  
    /**
     *
     * @var PrimeMoverCoreSaltDependencies
     */
    private $coresaltdependency;
    
    /**
     * 
     * @param PrimeMoverPHPVersionDependencies $phpversiondependencies
     * @param PrimeMoverWPCoreDependencies $corewpdependencies
     * @param PrimeMoverPHPCoreFunctionDependencies $phpfuncdependency
     * @param PrimeMoverFileSystemDependencies $filesystem_dependency
     * @param PrimeMoverPluginSlugDependencies $foldernamedependency
     * @param PrimeMoverCoreSaltDependencies $coresaltdependency
     */
    public function __construct( $phpversiondependencies, $corewpdependencies, $phpfuncdependency, $filesystem_dependency, $foldernamedependency, $coresaltdependency)
    {
        $this->phpversiondependencies = $phpversiondependencies;
        $this->corewpdependencies = $corewpdependencies;
        $this->phpfuncdependency = $phpfuncdependency;
        $this->filesystem_dependency = $filesystem_dependency;
        $this->foldernamedependency = $foldernamedependency;
        $this->coresaltdependency = $coresaltdependency;
    }
    
    /**
     * 
     * @return PrimeMoverCoreSaltDependencies
     */
    public function getCoreSaltDependency()
    {
        return $this->coresaltdependency;
    }

    /**
     * 
     * @return PrimeMoverPluginSlugDependencies
     */
    public function getPluginFolderNameDependency()
    {
        return $this->foldernamedependency;
    }
    
    /**
     * @compatible 5.6
     * @return PrimeMoverPHPVersionDependencies
     */
    public function getPHPVersionDependencies()
    {
        return $this->phpversiondependencies;
    }
    
    /**
     * @compatible 5.6
     * @return PrimeMoverWPCoreDependencies
     */
    public function getCoreWPDependencies() 
    {
        return $this->corewpdependencies;
    }
 
    /**
     * @compatible 5.6
     * @return PrimeMoverPHPCoreFunctionDependencies
     */
    public function getPHPCoreFunctionDependencies()
    {
        return $this->phpfuncdependency;
    }
    
    /**
     * @compatible 5.6
     * @return PrimeMoverFileSystemDependencies
     */
    public function getFileSystemPermissionChecks() {
        return $this->filesystem_dependency;
    }
    
    /**
     * Do an overall sanity checks if all dependencies required are meet
     * @return boolean
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverRequirements::itChecksCorrectPluginRequirementsMultisite()
     */
    public function passes()
    {
        $passes = false;

        $phpversion_check = $this->getPHPVersionDependencies()->phpPasses();
        $wpversion_check = $this->getCoreWPDependencies()->wpPasses();        
        $phpextensions_check = $this->getPHPCoreFunctionDependencies()->extensionsRequisiteCheck();
        $phpfunction_check = $this->getPHPCoreFunctionDependencies()->functionRequisiteCheck();
        $filesystem_check = $this->getFileSystemPermissionChecks()->fileSystemPermissionsRequisiteCheck();
        $pluginfoldername_check = $this->getPluginFolderNameDependency()->slugPasses();
        $coresaltdependency_check = $this->getCoreSaltDependency()->saltPasses();
        
        if ( $phpversion_check && $wpversion_check && $phpextensions_check && $phpfunction_check && $filesystem_check && $pluginfoldername_check && $coresaltdependency_check) {
            $passes = true;
        }
        
        if (! $passes) {
            global $pm_fs;
            if (is_object($pm_fs)) {
                remove_action( 'admin_init', array($pm_fs, '_admin_init_action' ));
            }            
            add_action('admin_init', array( $this, 'deactivate' ));
        }
        
        return $passes;
    }
        
    /**
     * Deactivate plugin
     * @compatible 5.6
     */
    public function deactivate()
    {
        primeMoverAutoDeactivatePlugin();
    }
}