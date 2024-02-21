<?php
namespace Codexonics\PrimeMoverFramework\app;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover display excluded uploads
 *
 */
class PrimeMoverDisplayExcludedUploadSettings
{
    private $prime_mover_settings;
    
    const EXCLUDED_UPLOADS = 'excluded_uploads';
    
    /**
     * Constructor
     * @param PrimeMoverSettings $prime_mover_settings
     */
    public function __construct(PrimeMoverSettings $prime_mover_settings) 
    {
        $this->prime_mover_settings = $prime_mover_settings;
    }
    
    /**
     * Settings instance
     * @return \Codexonics\PrimeMoverFramework\app\PrimeMoverSettings
     */
    public function getPrimeMoverSettings()
    {
        return $this->prime_mover_settings;
    }
        
    /**
     * Get Prime Mover instance
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMover
     */
    public function getPrimeMover()
    {
        return $this->getPrimeMoverSettings()->getPrimeMover();
    }
    
    /**
     * Get settings markup
     * @return \Codexonics\PrimeMoverFramework\utilities\PrimeMoverSettingsMarkups
     */
    public function getSettingsMarkup()
    {
        return $this->getPrimeMoverSettings()->getSettingsMarkup();
    }

    /**
     * Get placeholder
     * @return string
     */
    protected function getPlaceHolder()
    {
        return "folders-1: 2019/03";
    }
    
    /**
     * Show exclude uploads filters setting
     */
    public function showExcludedUploadsSetting()
    {
        ?>
        <table class="form-table">
        <tbody>
        <tr>
            <th scope="row">
                <label id="prime-mover-exportfilters-settings-label"><?php esc_html_e('Export filters', 'prime-mover')?></label>
                <?php do_action('prime_mover_last_table_heading_settings', 'https://codexonics.com/prime_mover/prime-mover/how-to-exclude-upload-media-files-in-prime-mover-pro/'); ?>
            </th>
            <td>
            <?php 
            $setting = $this->getPrimeMoverSettings()->convertMediaSettingsToTextAreaOutput(self::EXCLUDED_UPLOADS);
            ?>
            <textarea class="large-text" placeholder="<?php echo esc_attr($this->getPlaceHolder())?>" name="prime-mover-excluded-uploads" 
            id="js-prime-mover-excluded-uploads" rows="5" cols="45"><?php echo esc_textarea($setting);?></textarea>
                <div class="prime-mover-setting-description">
                    <p class="description prime-mover-settings-paragraph">
                    <?php esc_html_e('By default, all files/folders in uploads directory will be included in the export package. This is when exporting a package that includes media files.',
                        'prime-mover'); ?>
                    </p>
                    <p class="description">
                    <?php esc_html_e('However you can exclude files/folders in your uploads directory during export by adding exclusion rules above. 
                    You can use this reduce size of the export package or remove unwanted files/directories in the export.',
                        'prime-mover');
                    ?>                        
                    </p>
                    <p class="description prime-mover-settings-paragraph">
                    <?php printf( esc_html__('Please add ONE LINE per excluded file/folder using %s format where %s is the blog id if using multisite. It should be 1 in single-site.
                     Please see examples below on how to add your own exclusion rules :',
                        'prime-mover'), '<strong>IDENTIFIER-{blogid} : RESOURCES</strong>', '<strong>{blogid}</strong>');
                    ?>                        
                    </p>
                    <div class="description">
                    <ul class="ul-disc prime-mover-exclusion-uploads-example">
                        <li><span><?php esc_html_e('Excluding examplebigfile.mp4 and anotherbigfile.wav in 2019/04 uploads folder in a single-site', 'prime-mover');?></span> 
                        <code>files-1 : "2019/04/bigfile.mp4", "2019/04/anotherbigfile.wav"</code></li>
                        <li><span>
                        <?php esc_html_e('Excluding 2019/03 and 2017/03/very-big-folder of an uploads directory for multisite blog id 999', 'prime-mover');?></span> 
                        <code>folders-999 : "2019/03", "2017/03/very-big-folder"</code></li>
                        <li><span><?php esc_html_e('Excluding avi, zip, mp4 file extensions inside uploads directory of a single-site configuration', 'prime-mover');?></span> 
                        <code>extensions-1 : "avi", "zip", "mp4"</code></li>
                        <li><span><?php esc_html_e('Excluding huge_surprise.avi in 2018\10 uploads folder in a single-site Windows Server (use backlash)', 'prime-mover');?></span> 
                        <code>files-1 : "2018\10\huge_surprise.avi"</code></li>
                    </ul>                       
                    </div>
                     <p class="description prime-mover-settings-paragraph">
                         <?php printf( esc_html__('Replace %s with the multisite blog id where this exclusion applies if you are using multisite. %s.',
                             'prime-mover'), '<strong>{blogid}</strong>', 
                             '<strong>' . esc_html__('Set 1 as the blogid if you are in single-site configuration', 'prime-mover') . '</strong>');
                         ?> <?php esc_html_e('Always wrap each resource in double quotes and separate each of them with a comma.', 'prime-mover'); ?>
                    </p> 
                    <?php 
                    if (is_multisite()) {
                    ?>  
                    <p class="description">
                            <strong><em>
                            <span><?php echo esc_html__('IMPORTANT: ', 'prime-mover'); ?>
                                <?php echo esc_html__('This feature only works for subsites with active PRO licenses.', 'prime-mover'); ?></span>
                            </em></strong>
                     </p>                
                    <?php 
                    }
                    ?>                                                                          
                    <?php $this->getSettingsMarkup()->renderSubmitButton('prime_mover_excluded_uploads_nonce', 'js-save-prime-mover-excluded-uploads', 
                        'js-save-prime-mover-excluded-uploads-spinner'); ?> 
                </div>                      
            </td>
        </tr>
        </tbody>
        </table>
    <?php     
    }    
}