<?php
namespace Codexonics\PrimeMoverFramework\classes;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Codexonics\PrimeMoverFramework\interfaces\PrimeMoverSystemInitialize;
use ZipArchive;
use DirectoryIterator;
use WP_Error;
use Codexonics\PrimeMoverFramework\general\PrimeMoverMustUsePluginManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prime Mover System Initialization Class
 *
 * The Prime Mover System Initialization Class handles the initialization of properties used in this plugin.
 *
 */
class PrimeMoverSystemInitialization implements PrimeMoverSystemInitialize
{
    
    /** @var string */
    private $backupsites_optionname;
    
    /** @var string */
    private $prime_mover_user_understand_mainsite_only;
    
    /** @var boolean export folder created */
    private $multisite_export_folder_created;
    
    /** @var boolean lock files folder */
    private $lock_folder_created;
    
    /** @var string export folder path */
    private $multisite_export_folder_path;
        
    /** @var string export folder name */
    private $multisite_export_folder;
    
    /** @var boolean WP_FileSystem initialized */
    private $multisite_wp_filesystem_initialized;
    
    /** @var array ZIP mime types */
    private $zip_mime_types;
    
    /** @var string Plugin URI */
    private $plugin_uri;
    
    /** @var string Plugin file */
    private $plugin_file;
    
    /** @var string Import ID */
    private $import_id;
    
    /** @var string Export ID */
    private $export_id;
    
    /** PrimeMoverSystemAuthorization */
    private $system_authorization;
 
    /** @var string current import package path */
    private $currentimportpackagepath;
 
    /** @var string current export package path */
    private $currentexportpackagepath;
    
    /** @var integer blog ID */
    private $import_blog_id;
    
    /** @var integer blog ID */
    private $export_blog_id;
    
    /** @var string current import zip path */
    private $current_import_zip_path;
    
    /** @var string export zip path */
    private $export_zip_path;
    
    /** @var boolean is_legacy */
    private $is_legacy;
    
    /** @var array coresystem_functions */
    private $coresystem_functions;
    
    /** @var string base backup directory */
    private $basebackupdir;
    
    /** @var string download URL */
    private $download_url;
    
    /** @var string js-body-class */
    private $js_body_class;
    
    /** @var string css_body_class */
    private $css_body_class;
    
    /** @var boolean */
    private $is_network_sites;
    
    /** @var boolean */
    private $using_dev_args;
    
    /** @var array */
    private $dev_args;
    
    /** @var boolean */
    private $encrypted_db;
    
    /** @var string */
    private $basedirectorypath;
    
    /** @var string */
    private $controlpanelsettings;
    
    /** @var string */
    private $error_log_file;

    /** @var string */
    private $uploaderidentity;
    
    /** @var string */
    private $upload_tmp_path_slug;
    
    /** @var boolean */
    private $encrypt_export_data;
    
    /** @var boolean */
    private $randomize_db_prefix;
    
    /** @var string */
    private $random_db_prefix;
    
    /** @var string */
    private $current_gearbox_packages;
    
    /** @var string */
    private $prime_mover_current_settings;
    
    /** @var array */
    private $prime_mover_ajax_actions;
    
    /** @var array */
    private $prime_mover_export_methods;
    
    /** @var array */
    private $prime_mover_import_methods;
    
    /** @var string */
    private $signature_file;
    
    /** @var string */
    private $media_encrypted_signature;
    
    /** @var string */
    private $tmp_downloads_folder;
    
    /** @var string */
    private $prime_mover_validated_backups_option;
    
    /** @var string */
    private $prime_mover_backups_menu;
    
    /** @var string */
    private $prime_mover_gearbox_backups;
    
    /** @var array */
    private $cli_progress_keys;
    
    /** @var string */
    private $prime_mover_backup_markup_version;
    
    /** @var string */
    private $prime_mover_cli_master_tmp_files;
    
    /** @var string */
    private $prime_mover_user_enc_key_settings;
    
    /** @var string */
    private $prime_mover_user_read_important_message;
    
    /** @var string */
    private $prime_mover_default_encryption_setup_done;
    
    /** @var object */
    private $gdrive_client;
    
    /** @var string */
    private $gdrive_authurl;
    
    /** @var object */
    private $gdrive_service;
    
    /** @var string */
    private $gDriveFileId;
    
    /** @var boolean */
    private $prime_mover_is_subsite;
    
    /** @var string */
    private $prime_mover_action_link;
    
    /** @var string */
    private $lock_files_folder;
    
    /** @var string */
    private $prime_mover_default_user_role;
    
    /** @var string */
    private $srch_replace_batch_size;
    
    /** @var string */
    private $site_specific_export_folder_created;
 
    /** @var string */
    private $db_dump_batch_size;
    
    /** @var boolean */
    private $force_adjust_site_url;
    
    /** @var string */
    private $maybe_refresh_package_enc_changed;
    
    /** @var array */
    private $canonical_uploads_info;
    
    /** @var array */
    private $root_uploads_info;

    /** @var array */
    private $utf;
    
    /** @var array */
    private $deprecated_utf8;
    
    /** @var array */
    private $fast_hash_algos;
    
    /**
     * Constructor
     * @param PrimeMoverSystemAuthorization $system_authorization
     */
    public function __construct(PrimeMoverSystemAuthorization $system_authorization)
    {
        $this->system_authorization = $system_authorization;
        $this->multisite_export_folder = PRIME_MOVER_EXPORT_DIR_SLUG;
        $this->tmp_downloads_folder = PRIME_MOVER_TMP_DIR_SLUG;
        $this->lock_files_folder = PRIME_MOVER_LOCK_DIR_SLUG;
        $this->upload_tmp_path_slug = PRIME_MOVER_IMPORT_DIR_SLUG;
        
        $this->multisite_export_folder_path = '';
        $this->plugin_uri = PRIME_MOVER_PLUGIN_URI;
        $this->plugin_file = PRIME_MOVER_PLUGIN_FILE;
        $this->prime_mover_action_link = 'prime_mover_backup_sites_action_link';
        
        $this->multisite_export_folder_created = false;
        $this->lock_folder_created = false;
        $this->multisite_wp_filesystem_initialized = false;
        $this->encrypt_export_data = false;
        $this->zip_mime_types = ['application/x-zip', 'application/zip', 'application/x-zip-compressed', 'application/octet-stream'];
        $this->randomize_db_prefix = false;
        $this->random_db_prefix = '';
        $this->prime_mover_cli_master_tmp_files = '_prime_mover_cli_master_option_files';
        
        $this->import_id = '';
        $this->export_id = '';
        $this->currentimportpackagepath = '';
        $this->currentexportpackagepath = '';
        $this->import_blog_id = 0;
        $this->export_blog_id = 0;
        $this->current_import_zip_path =  '';
        $this->basebackupdir = '';
        $this->export_zip_path =  '';
        $this->site_specific_export_folder_created = '';
        $this->is_legacy =  false;
        $this->coresystem_functions = ['escapeshellarg', 'escapeshellcmd', 'shell_exec', 'extension_loaded', 'popen', 'exec'];
        $this->download_url = '';
        
        $this->js_body_class = 'js-prime-mover-sites-page';
        $this->css_body_class = 'prime-mover-sites-page';
        $this->is_network_sites = false;
        $this->using_dev_args = false;
        $this->uploaderidentity = '';        
        $this->prime_mover_user_understand_mainsite_only = 'prime_mover_user_understand_mainsite_only';
        
        $this->dev_args = [];
        $this->dev_args['reject_unsafe_urls'] = false;
        $this->dev_args['sslverify'] = false;
        $this->dev_args['redirection'] = apply_filters('prime_mover_filter_redirection_times', 10);
        $this->dev_args['timeout'] = apply_filters('prime_mover_filter_request_timeout', 18000);
        
        $this->backupsites_optionname = 'prime_mover_backup_sites';
        $this->encrypted_db = false;
        $this->basedirectorypath = 'basedir_backup_path';
        $this->controlpanelsettings = 'prime_mover_control_panel_settings';
        $this->error_log_file = 'prime_mover_error.log';  
        $this->current_gearbox_packages = 'prime_mover_current_gearbox_packages';
        
        $this->prime_mover_current_settings = 'prime_mover_current_settings';
        $this->signature_file = 'signature.enc';
        $this->media_encrypted_signature = 'media.enc';
        $this->prime_mover_validated_backups_option = 'prime_mover_validated_backups_option';
        $this->prime_mover_backups_menu = 'prime_mover_backups_menu';
        $this->prime_mover_gearbox_backups = 'prime_mover_gearbox_backups';
        $this->prime_mover_backup_markup_version = 'prime_mover_backup_markup_version';
        $this->prime_mover_default_user_role = 'prime_mover_default_user_role';
        
        $this->prime_mover_user_enc_key_settings = 'prime_mover_user_enc_key_settings';
        $this->prime_mover_user_read_important_message = 'prime_mover_user_read_important_message';
        $this->prime_mover_default_encryption_setup_done = 'prime_mover_default_encryption_setup_done';
        $this->gdrive_client = null;
        $this->gdrive_authurl = '';
        $this->gdrive_service = null;
        $this->gDriveFileId = '';
        $this->prime_mover_is_subsite = false;
        
        $this->prime_mover_ajax_actions = [
            'prime_mover_process_export' => 'primeMoverExportProcessor',
            'prime_mover_process_uploads' => 'primeMoverUploadsProcessor',
            'prime_mover_process_import' => 'primeMoverImportProcessor',
            'prime_mover_monitor_import_progress' => 'primeMoverImportProgressProcessor',
            'prime_mover_monitor_export_progress' => 'primeMoverExportProgressProcessor',
            'multisite_tempfile_cancel' => 'primeMoverTempfileDeleteProcessor',
            'prime_mover_shutdown_export_process' => 'primeMoverShutdownExportProcessor',
            'prime_mover_shutdown_import_process' => 'primeMoverShutdownImportProcessor',
            'prime_mover_verify_encrypted_package' => 'primeMoverVerifyEncryptedPackage'       
            
            ];
        
        $this->prime_mover_export_methods = [
            'createTempfolderForThisSiteExport',
            'maybeGetThirdPartyCallBacks',
            'dumpDbForExport',            
            'zipDbDump',
            'generateMediaFilesList',
            'copyMediaFiles',
            'generateLanguageFilesList',
            'copyLanguageFiles',
            'generateExportFootprintConfig',            
            'generateUserMetaKeysToAdjust',
            'exportUsers',
            'maybeAddUsersExportFileToArchive',
            'generatePluginFilesList',
            'optionallyExportPluginsThemes',
            'generateThemesFilesList',
            'maybeExportThemes',
            'finalizingMediaArchive',
            'zippedFolder',
            'deleteTemporaryFolder',
            'generateDownloadUrl',
            'doPostExportProcessing'
        ];
        
        $this->prime_mover_import_methods = [
            'moveImportedFilesToUploads',
            'unzipImportedZipPackageMigration',
            'validateImportedSiteVsPackage',
            'compareSystemFootprintImport',
            'extractZipPackage',
            'updateTargetMediaFilesWithNew',
            'maybeCopyLanguageFolderToTarget',
            'markTargetSiteUploadsInformation',
            'multisiteOptionallyImportPluginsThemes',
            'maybeRestoreTheme',
            'dropCustomTables',
            'importDb',
            'renameDbPrefix',
            'generateUserMetaKeysToAdjust',
            'importUsers',
            'generateUserEquivalence',
            'updatePostAuthors',
            'maybeUpdateUserTaxonomy',
            'countTableRows',
            'searchAndReplace',
            'activatePluginsIfNotActivated',            
            'restoreCurrentUploadsInformation',
            'maybeProcessThirdPartyData',
            'markImportSuccess' 
        ];  
        
        $this->cli_progress_keys = [
            'archiving_plugins_shell_started',
            'archiving_themes_shell_started',
            'copying_media_shell_started',
            'zipping_main_directory_started',
            'deleting_tmp_dir_started',
            'importing_plugins_started',
            'unzipping_main_directory_started'           
        ];
        
        $this->srch_replace_batch_size = "prime_mover_searchreplace_batchsize";
        $this->db_dump_batch_size = "prime_mover_dbdump_batchsize";
        $this->force_adjust_site_url = false;
        $this->maybe_refresh_package_enc_changed = 'prime_mover_package_refresh_needed';
        $this->canonical_uploads_info = [];
        $this->root_uploads_info = [];
        $this->utf = [PRIME_MOVER_UNICODE_CHARSET, PRIME_MOVER_DEPRECATED_UNICODE_CHARSET, PRIME_MOVER_MODERN_UNICODE_CHARSET];
        $this->deprecated_utf8 = [PRIME_MOVER_UNICODE_CHARSET, PRIME_MOVER_DEPRECATED_UNICODE_CHARSET];  
        $this->fast_hash_algos = ['adler32', 'fnv1a32'];
    }

    /**
     * Get fast hash algos
     * @return array|string[]
     */
    public function getFastHashAlgos()
    {
        return $this->fast_hash_algos;
    }
    
    /**
     * Get deprecated UTF8 charsets
     * @return array|string[]
     */
    public function getDeprecatedUtf8()
    {
        return $this->deprecated_utf8;
    }
    
    /**
     * Get UTF encoding
     * @return string[]
     */
    public function getUtfEncoding()
    {
        return $this->utf;
    }
    
    /**
     * Get root uploads information
     * @return array
     */
    public function getRootUploadsInfo()
    {
        return $this->root_uploads_info;
    }
    
    /**
     * Set root uploads info
     * @param array $info
     */
    public function setRootUploadsInfo($info = [])
    {
        $this->root_uploads_info = $info;
    }
    
    /**
     * Get canonical uploads information
     * @return array
     */
    public function getCanonicalUploadsInfo()
    {
        return $this->canonical_uploads_info;
    }
    
    /**
     * Set canonical uploads info
     * @param array $info
     */
    public function setCanonicalUploadsInfo($info = [])
    {        
        $this->canonical_uploads_info = $info;
    }
    
    /**
     * Get package refresh option
     * @return string
     */
    public function getPackageRefreshOption()
    {
        return $this->maybe_refresh_package_enc_changed;
    }
    
    /**
     * Set force adjust site URL
     * @param boolean $force_adjust
     */
    public function setForceAdjustSiteUrl($force_adjust = false)
    {
        $this->force_adjust_site_url = $force_adjust;
    }
    
    /**
     * Get force adjust site URL
     * @return boolean
     */
    public function getForceAdjustSiteUrl()
    {
        return $this->force_adjust_site_url;
    }
    
    /**
     * Get db dump batch size setting
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsDbDumpBatchSizeSetting()
     */
    public function getDbDumpBatchSizeSetting()
    {
        return $this->db_dump_batch_size;
    }
    
    /**
     * Get search replace batch size setting
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsSearchReplaceBatchSizeSetting()
     */
    public function getSearchReplaceBatchSizeSetting()
    {
        return $this->srch_replace_batch_size;
    }
    
    /**
     * Get site specific export folder path if already created
     * @return string
     */
    public function getSiteSpecificFolderCreated()
    {
        return $this->site_specific_export_folder_created;
    }
    
    /**
     * Set site specific export folder path if already created
     * @param string $path
     */
    public function setSiteSpecificFolderCreated($path = '')
    {
        $this->site_specific_export_folder_created = $path;
    }
    
    /**
     * Default user role meta key
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsDefaultUserRole() 
     */
    public function getDefaultUserRole()
    {
        return $this->prime_mover_default_user_role;
    }
    
    /**
     * Get Prime Mover action link
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsPrimeMoverActionLink() 
     */
    public function getPrimeMoverActionLink()
    {
        return $this->prime_mover_action_link;
    }
    
    /**
     * Get is subsite
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsIfSubsite()
     */
    public function getIsSubsite()
    {
        return $this->prime_mover_is_subsite;
    }
    
    /**
     * Sets Gdrive File ID
     * @param string $gdrive_fileid
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsGDriveFileId() 
     */
    public function setsGdriveFileId($gdrive_fileid = '')
    {
        $this->gDriveFileId = $gdrive_fileid;
    }
    
    /**
     * Gets Gdrive FileId
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsGDriveFileId() 
     */
    public function gDriveFileId()
    {
        return $this->gDriveFileId;
    }
    
    /**
     * Gets Gdrive service
     * @return object|NULL|string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsGDriveService()
     */
    public function getGDriveService()
    {
        return $this->gdrive_service;
    }
    
    /**
     * Sets Gdrive service
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsGDriveService()
     */
    public function setGDriveService($service = null)
    {
        $this->gdrive_service = $service;
    }
    
    /**
     * Sets Gdrive Auth URL
     * @param string $authurl
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsGDriveAuthUrl() 
     */
    public function setGdriveAuthUrl($authurl = '')
    {
        $this->gdrive_authurl = $authurl;
    }
    
    /**
     * Gets Gdrive auth URL
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsGDriveAuthUrl() 
     */
    public function getGdriveAuthUrl()
    {
        return $this->gdrive_authurl;
    }
    
    /**
     * Sets Google Drive client
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsGdriveClient() 
     */
    public function setGdriveClient($client = null)
    {
        $this->gdrive_client = $client;
    }
    
    /**
     * Gets Google Drive Client
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsGdriveClient() 
     */
    public function getGDriveClient()
    {
        return $this->gdrive_client;
    }
    
    /**
     * Get enc key setting
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsEncSetting()
     */
    public function getEncKeySetting()
    {
        return $this->prime_mover_user_enc_key_settings;
    }
    
    /**
     * Get important read msg setting
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsImportantReadMsgSetting()
     */
    public function getImportantReadMsgSetting()
    {
        return $this->prime_mover_user_read_important_message;
    }
    
    /**
     * Get enc config done setting
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetEncConfigDoneSetting()
     */
    public function getEncConfigDoneSetting()
    {
        return $this->prime_mover_default_encryption_setup_done;
    }
    
    /**
     * get CLi master tmp files option
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsCliMasterTmpFilesOptions()
     */
    public function getCliMasterTmpFilesOptions()
    {
        return $this->prime_mover_cli_master_tmp_files;
    }
    
    /**
     * Get process methods
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsProcessMethods()
     * @param string $current_function
     * @param string $mode
     * @return array
     */
    public function getProcessMethods($current_function = '', $mode = 'export')
    {
        $export_methods = $this->getPrimeMoverExportMethods();
        $import_methods = $this->getPrimeMoverImportMethods();
        $process_methods = $export_methods;        
        if ('import' === $mode) {
            $process_methods = $import_methods;
        }        
        $position = array_search($current_function, $process_methods);
        $previous_pos = $position - 1;
        $next_pos = $position + 1;
        if ($previous_pos < 0) {
            return [$current_function, '', $process_methods[$next_pos]]; 
        } elseif ( ! isset($process_methods[$next_pos]) ) {
            return [$current_function, $process_methods[$previous_pos], ''];  
        } else {
            return [$current_function, $process_methods[$previous_pos], $process_methods[$next_pos]];  
        }             
    }
    
    /**
     * Get prime mover backup version option name
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsPrimeMoverBackupMarkupVersion()
     */
    public function getPrimeMoverBackupMarkupVersion()
    {
        return $this->prime_mover_backup_markup_version;
    }
    
    /**
     * Get Cli progress keys
     * @return array|string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsCliProgressKeys()
     */
    public function getCliProgressKeys()
    {
        return $this->cli_progress_keys;
    }
    
    /**
     * Get Prime Mover gearbox backup option
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsPrimeMoverGearBoxBackupOption() 
     */
    public function getPrimeMoverGearBoxBackupOption()
    {
        return $this->prime_mover_gearbox_backups;
    }
    
    /**
     * Get option name for Prime Mover menu backups
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsPrimeMoverMenuBackupOption()
     */
    public function getPrimeMoverMenuBackupsOption()
    {
        return $this->prime_mover_backups_menu;
    }
    
    /**
     * Get option name for Prime Mover validated backups
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsPrimeMoverValidatedBackupsOption() 
     */
    public function getPrimeMoverValidatedBackupsOption()
    {
        return $this->prime_mover_validated_backups_option;
    }
    
    /**
     * Get media encrypted signature
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsEncryptedMediaSignature() 
     */
    public function getMediaEncryptedSignature()
    {
        return $this->media_encrypted_signature;
    }
    
    /**
     * Get signature file
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsSignatureFile()
     */
    public function getSignatureFile()
    {
        return $this->signature_file;
    }
    
    /**
     * Get Prime Mover import methods
     * @return array|string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsImportMethods() 
     */
    public function getPrimeMoverImportMethods()
    {
        return $this->prime_mover_import_methods;
    }
    
    /**
     * Get Prime Mover export methods
     * @return array|string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsExportMethods()
     */
    public function getPrimeMoverExportMethods()
    {
        return $this->prime_mover_export_methods;
    }
    
    /**
     * Get Prime Mover ajax actions
     * @return array|string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsAjaxActions()
     */
    public function getPrimeMoverAjaxActions()
    {
        return $this->prime_mover_ajax_actions;
    }
    
    /**
     * Get migration current settings
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsMigrationCurrentSettings()
     */
    public function getMigrationCurrentSettings()
    {
        return $this->prime_mover_current_settings;
    }
    
    /**
     * 
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsCurrentGearBoxPackagesMetaKey()
     */
    public function getCurrentGearBoxPackagesMetaKey()
    {
        return $this->current_gearbox_packages;
    }
    
    /**
     * Get upload tmp path slug
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsUploadTmpPathSlug()
     */
    public function getUploadTmpPathSlug()
    {
        return $this->upload_tmp_path_slug;
    }
    
    /**
     * Get error log file
     * @param number $blog_id
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGeneratesErrorLogFile()
     */
    public function getErrorLogFile($blog_id = 0)
    {
        if ( ! $blog_id ) {
            return $this->error_log_file;
        }        
        $user_hash = $this->generateHashByUser();
        if ( ! $user_hash ) {
            return $this->error_log_file;
        }
        return $user_hash . '_blog_id_' . $blog_id . '_' . $this->error_log_file;
    }
    
    /**
     * Set slow process for testing purposes
     * @codeCoverageIgnore
     */
    public function setSlowProcess()
    {
        $time = 5;
        if (defined('PRIME_MOVER_SLOW_SECONDS')) {
            $time = (int)PRIME_MOVER_SLOW_SECONDS;
        }
        if (defined('PRIME_MOVER_TEST_SLOW_PROCESSES') && PRIME_MOVER_TEST_SLOW_PROCESSES && $time) {
            $this->setProcessingDelay($time);
        }
    }
  
    /**
     * Set PHP-fpm request_terminate_timeout to 30 seconds
     * In export process, this function is called in db export, copying media and copying plugins
     * In import process, this function is called in restoring media, plugins and database
     * Set the terminate timeout in such a way it exceeds request_terminate_timeout to reproduce issue
     * @codeCoverageIgnore
     */
    public function testRequestTerminateTimeout()
    {
        if (defined('PRIME_MOVER_TEST_REQUEST_TERMINATE_TIMEOUT') && PRIME_MOVER_TEST_REQUEST_TERMINATE_TIMEOUT) {
            $seconds = (int)PRIME_MOVER_TEST_REQUEST_TERMINATE_TIMEOUT;            
            $this->setProcessingDelay($seconds);
        }
    }
 
    /**
     * Set processing delay
     * @param number $sleep
     * @param boolean $microsleep
     * @codeCoverageIgnore
     */
    public function setProcessingDelay($sleep = 0, $microsleep = false)
    {
        if ("cli" === php_sapi_name()) {
            return;
        }        
        $sleep = (int)$sleep;
        if ($microsleep) {
            usleep($sleep);
        } else {
            sleep($sleep);
        }        
    }
    
    /**
     * 
     * @return string
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsControlPanelSettingsName()
     */
    public function getControlPanelSettingsName()
    {
        return $this->controlpanelsettings;
    }
    
    /**
     * Get base directory path setting name
     * @return string
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsBaseDirectoryPathCustomName()
     */
    public function getBaseDirectoryPathSettingName()
    {
        return $this->basedirectorypath;
    }

    /**
     * Sets if we are importing encrypted dB
     * @param boolean $ret
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEncryptedDbBoolean() 
     */
    public function setEncryptedDb($ret = false)
    {
        $this->encrypted_db = $ret;
    }
    
    /**
     * Gets the encrypted dB import status
     * @return boolean
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEncryptedDbBoolean() 
     */
    public function getEncryptedDb()
    {
        return $this->encrypted_db;
    }
    
    /**
     * Get dB encryption key from wp-config.php
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsDbEncryptionKey()
     */
    public function getDbEncryptionKey()
    {        
        $ret = '';
        if ( ! defined('PRIME_MOVER_DB_ENCRYPTION_KEY') ) {
            return $ret;
        }
        $key = trim(PRIME_MOVER_DB_ENCRYPTION_KEY);        
        if (empty($key)) {            
            return '';
        }
        return $key;
    }
    
    /**
     * Generate site with backups option name
     * @return string
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsBackupSitesOptionName()
     */
    public function getBackupSitesOptionName()
    {
        return $this->backupsites_optionname;
    }
    
    /**
     * 
     * @return mixed|NULL|array
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsDevArgs() 
     */
    public function getDevArgs()
    {
        return apply_filters( 'prime_mover_filter_request_dev_args', $this->dev_args);
    }
    
    /**
     * Sets if an import process is using dev args
     * @param boolean $ret
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsIsUsingDevArgs() 
     */
    public function setIsUsingDevArgs($ret = false)
    {
        $this->using_dev_args = $ret;
    }
    
    /**
     * Gets is using dev args
     * @return boolean
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsIsUsingDevArgs() 
     */
    public function getIsUsingDevArgs()
    {
        return $this->using_dev_args;
    }
    
    /**
     * 
     * @param boolean $ret
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itAddsJsBodyClassOnNetworkSitesPage()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itDoesNotAddBodyClassNotOnNetworkSites()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itAddsJsBodyClassOnExistingBodyClass() 
     */
    public function setIsNetworkSites($ret = false)
    {
        $this->is_network_sites = $ret;
    }
    
    /**
     * Returns true if on network sites
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itAddsJsBodyClassOnNetworkSitesPage()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itDoesNotAddBodyClassNotOnNetworkSites()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverHookedMethods::itAddsJsBodyClassOnExistingBodyClass() 
     * @return boolean
     */
    public function isNetworkSites()
    {
        return $this->is_network_sites;        
    }
    
    /**
     * Get js body class
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsJsBodyClass()
     */
    public function getJsBodyClass()
    {
        return $this->js_body_class;
    }
    
    /**
     * Get css body class
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsCssBodyClass()
     */
    public function getCssBodyClass()
    {
        return $this->css_body_class;
    }

    /**
     * Get main site blog ID of the multisite network
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itStreamsErrorLog() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsOneAsMainSiteIdIfSingleSite()
     * @mainsitesupport_affected
     * In multisite, the main site might not be using 1 as the blog ID
     * Rely on the function to get the correct value.
     * @param boolean $force_domain_current_site
     * @return number
     */
    public function getMainSiteBlogId($force_domain_current_site = false)
    {
        if ($force_domain_current_site && is_multisite() && defined('DOMAIN_CURRENT_SITE') && DOMAIN_CURRENT_SITE) {
            $domain_current_site_id = (int)get_blog_id_from_url(DOMAIN_CURRENT_SITE);
            return $domain_current_site_id;
        }
        
        if (is_multisite()) {
            return get_network()->site_id;
        } else {
            return 1;
        }        
    }

    /**
     * Get user input data and apply basic sanitization => validation
     * @param string $method
     * @param string|array $args
     * @param string $validation_id
     * @param string $mode
     * @param number $blog_id
     * @param boolean $return_data
     * @param boolean $return_error
     * @return mixed|NULL|array|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsUserInput
     */
    public function getUserInput($method = 'post', $args = null, $validation_id = '', $mode = '', $blog_id = 0, $return_data = false, $return_error = false)
    {
        if (in_array($method, ['post', 'get', 'server'])) {
            return apply_filters('prime_mover_validate_user_input_data', $this->filterInputArray($method, $args), $validation_id, $mode, $blog_id, $return_data, $return_error);
        }
        return [];
    }
    
    /**
     * Filter input array helper
     * @param string $mode
     * @param mixed $args
     * @return mixed
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsUserInput
     */
    protected function filterInputArray($mode = 'post', $args = null)
    {
        if ('post' === $mode) {
            return filter_input_array(INPUT_POST, $args);
        }
        if ('get' === $mode) {
            return filter_input_array(INPUT_GET, $args);
        }
        if ('server' === $mode) {            
            $output = [];
            foreach ($args as $arg => $filter) {
                if (isset($_SERVER[$arg])) {
                    $output[$arg] = filter_var($_SERVER[$arg], $filter);
                } else {
                    $output[$arg] = null;
                }
            }            
            return $output;
        }
    }
    
    /**
     * Generate download option name
     * @param string $sanitized_name
     * @param number $blog_id
     * @return string
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itCleansBackupDirectoryUponRequests()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itCleansAllBackupDirectoryFilesUponRequests()
     */
    public function generateZipDownloadOptionName($sanitized_name = '', $blog_id = 0)
    {
        return $sanitized_name . "_" . $blog_id;
    }
    
    /**
     * Get export directory path using $blog_id
     * @param number $blog_id
     * @param boolean $exist_check
     * @param string $custom_path
     * @return NULL|string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsExportDirectoryPath()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsNullIfBlogIdIsNotSetOnExportDir()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsNullIfExportDirDoesNotExists()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsExportDirectoryPathFromInputCustomPath()
     */
    public function getExportDirectoryPath($blog_id = 0, $exist_check = true, $custom_path = '')
    {
        if ( ! $blog_id ) {
            return null;
        }
        $path = $this->getMultisiteExportFolderPath();
        if ($custom_path) {
            $path = $this->getMultisiteExportFolder($custom_path);
        }
        $dir = $path . $blog_id . DIRECTORY_SEPARATOR;
        global $wp_filesystem;
        if ( $exist_check && ! $wp_filesystem->exists($dir) ) {
            return null;
        }
        return $dir;
    }
    
    /**
     * Set download zip URL
     * @param string $url
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsDownloadZipUrl()
     */
    public function setDownloadZipURL($url = '') 
    {
        $this->download_url = $url;
    }
    
    /**
     * Get download zip URL
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsDownloadZipUrl()
     */
    public function getDownloadZipURL()
    {
        return $this->download_url;
    }
    
    /**
     * Gets core system functions required
     * @return array
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsCoreSystemFunctions() 
     */
    public function getCoreSystemFunctions() 
    {
        return $this->coresystem_functions;
    }
    
    /**
     * Get System authorization
     * @return \Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemAuthorization
     * @compatible 5.6
     */
    public function getSystemAuthorization()
    {
        return $this->system_authorization;
    }
    
    /**
     * Get import ID
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsImportId()
     */
    public function getImportId()
    {
        return $this->import_id;
    }
    
    /**
     * @compatible 5.6
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsExportId()
     */
    public function getExportId()
    {
        return $this->export_id;
    }
    
    /**
     * Set import ID
     * @param string $uniqid
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsImportId()
     */
    public function setImportId($uniqid = '')
    {
        $this->import_id = $uniqid;
    }
 
    /**
     * Set export ID
     * @param string $uniqid
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsExportId()
     */
    public function setExporttId($uniqid = '')
    {
        $this->export_id = $uniqid;
    }
    
    /**
     * Initialize file system API helper
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itInitializesWpFilesystem()
     */
    protected function initializeFileSystemApi()
    {
        if (function_exists('WP_Filesystem')) {
            WP_Filesystem();
        }
        //Test if WP_FileSystem is ready to use
        global $wp_filesystem;
        if ( ! is_object($wp_filesystem) ) {
            return;
        }
        if ( ! isset($wp_filesystem->errors) || ! isset($wp_filesystem->method ) ) {
            return;
        }
        if ( is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_message()) {
            return;
        }
        if ( 'direct' !== $wp_filesystem->method) {
            return;
        }
        $this->multisite_wp_filesystem_initialized	= true;
    }
    
    /**
     * Initialize WP Filesystem
     * Hooked to `admin_init`
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itInitializesWpFilesystem()
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotInitializeFileSystemOnErrors()
     * @tested PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotInitializeWhenNotUsingDirectMethod()
     */
    public function multisiteInitializeWpFilesystemApi()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $this->initializeFileSystemApi();
    }
    
    /**
     * Initialize WP Filesystem on init CLI
     * Hooked to `admin_init` available only in CLI
     * @param boolean $require_authorization
     * @codeCoverageIgnore
     */
    public function multisiteInitializeWpFilesystemApiCli($require_authorization = true)
    {
        if ($require_authorization && ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $initialize = false;
        if ("cli" == php_sapi_name() && 'init' === current_filter()) {
            $initialize = true;
        }
        if (false === $require_authorization) {
            $initialize = true;
        }
        if ($initialize && file_exists(ABSPATH . 'wp-admin/includes/file.php')) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            $this->initializeFileSystemApi();
        }
    }
    
    /**
     * Get default base backup directory
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsDefaultBackupDir() 
     */
    public function getDefaultBaseBackupDir() 
    {
        $upload_dir = $this->getInitializedWpRootUploads();
        return $this->realPath($upload_dir['basedir']);
    }
    
    /**
     * Set if subsite
     * @param boolean $subsite
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsIfSubsite()
     */
    public function setIsSubsite($subsite = false)
    {
        $this->prime_mover_is_subsite = $subsite;
    }
    
    /**
     * Creates folder for storing exported sites
     * {@inheritDoc}
     * @see PrimeMoverSystemInitialization::primeMoverCreateFolder()
     * Hooked to `init`
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itCreatesPrimeMoverFolder()
     */
    public function primeMoverCreateFolder()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        
        $export_folder = $this->getMultisiteExportFolder();        
        if (false === $this->getIsSubsite() && $export_folder && wp_mkdir_p($export_folder)) {
            $this->multisite_export_folder_created	= true;                
            $this->multisite_export_folder_path = $export_folder;                
        }
    }

    /**
     * Creates folder for storing publicly available symlinks.
     * Easy storage for easy maintenance and deletion after downloads
     * Hooked to `init`
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotCreateTmpDownloadsDirIfNotAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotCreateTmpDownloadsDirIfNotSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itCreatesTmpDownloadsFolder()
     */
    public function primeMoverCreateTmpDownloadsFolder()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $downloads_folder = $this->getTmpDownloadsFolder();
        if (false === $this->getIsSubsite() && $downloads_folder) {
            wp_mkdir_p($downloads_folder);
        }
    }

    /**
     * Get lock folder created
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itCreatesLockFilesFolder()
     */
    public function getLockFolderCreated()
    {
        return $this->lock_folder_created;
    }
    
    /**
     * Create lock files directory
     * Hooked on `init` - priority 1
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itCreatesLockFilesFolder() 
     */
    public function primeMoverCreateLockFilesFolder()
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
                
        $lock_folder = $this->getLockFilesFolder();
        if (false === $this->getIsSubsite() && $lock_folder && wp_mkdir_p($lock_folder)) {
            $this->lock_folder_created = true;
        }
    }
 
    /**
     * Get lock files folder slug
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsLockFilesFolderSlug()
     */
    public function getLockFilesFolderSlug()
    {
        return $this->lock_files_folder;
    }
    
    /**
     * Get lock files folder path
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsLockFilesFolder()
     */
    public function getLockFilesFolder()
    {
        $basedir = $this->getBackupBaseDirectory(false);
        if ($basedir) {
            $lockfiledir = $basedir . DIRECTORY_SEPARATOR . $this->lock_files_folder;
            return  $lockfiledir . DIRECTORY_SEPARATOR;
        }
        return '';
    }
    
    /**
     * Get folder path
     * @compatible 5.6
     * @return string
     * @tested TestMigrationSystemInitialization::itCreatesPrimeMoverFolder() 
     */
    public function getMultisiteExportFolderPath()
    {
        return $this->multisite_export_folder_path;
    }

    /**
     * Get export folder
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsMultisiteExportFolderFromBaseDirectory()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsMultisiteExportFolderFromInput() 
     * @param string $basedir
     * @param boolean $allow_filters
     * @param boolean $force_subsite
     * @return string
     */
    public function getMultisiteExportFolder($basedir = '', $allow_filters = true, $force_subsite = false) 
    {
        if (empty($basedir)) {
            $basedir = $this->getBackupBaseDirectory($allow_filters, $force_subsite);
        }        
        if ($basedir) {
            $exportbasedir = $basedir . DIRECTORY_SEPARATOR . $this->multisite_export_folder;            
            return  $exportbasedir . DIRECTORY_SEPARATOR;
        }
        return '';        
    }
    
    /**
     * Get tmp downloads folder slug
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTmpDownloadsSlug() 
     */
    public function getTmpDownloadsFolderSlug()
    {
        return $this->tmp_downloads_folder;
    }
    
    /**
     * Get tmp downloads folder
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTmpDownloadsFolder()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsEmptyWhenTmpBaseIsNotSet() 
     */
    public function getTmpDownloadsFolder()
    {
        $basedir = $this->getBackupBaseDirectory(false);         
        if ($basedir) {
            $exportbasedir = $basedir . DIRECTORY_SEPARATOR . $this->tmp_downloads_folder;
            return  $exportbasedir . DIRECTORY_SEPARATOR;
        }
        return '';       
    }
 
    /**
     * Get default import folder
     * @return string
     */
    public function getDefaultImportFolder()
    {
        $basedir = $this->getBackupBaseDirectory(false);
        if ($basedir) {
            $exportbasedir = $basedir . DIRECTORY_SEPARATOR . $this->getUploadTmpPathSlug();
            return  $exportbasedir . DIRECTORY_SEPARATOR;
        }
        return '';
    }
    
    /**
     * Get tmp downloads URL
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTmpDownloadsUrl()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsEmptyWhenDownloadUrlNotSet() 
     */
    public function getTmpDownloadsUrl()
    {        
        $upload_dir = $this->getInitializedWpRootUploads();
        if (empty($upload_dir['baseurl'])) {
            return '';
        } else {
            return trailingslashit(set_url_scheme($upload_dir['baseurl'])) . $this->tmp_downloads_folder;
        }
    }
    
    /**
     * Get base backup directory
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsBaseBackupDirectoryWithAllowFiltersFalse() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsBaseBackupDirectoryFromWpUploadsDir() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsEmptyBaseBackupDirectoryIfNotSet()
     * 
     * @param boolean $allow_filters
     * @param boolean $force_subsite_load
     * @return string
     */
    public function getBackupBaseDirectory($allow_filters = true, $force_subsite_load = false) 
    {
        $ret = '';
        if ($force_subsite_load && is_multisite()) {            
            $upload_dir = $this->getWpUploadsDir(false, false);
        } else {
            $upload_dir = $this->getInitializedWpRootUploads();
        }
        
        if (empty($upload_dir['basedir'])) {
            return $ret;
        }
        if ($allow_filters) {
            $basedir = apply_filters( 'prime_mover_filter_basebackup_dir', $upload_dir['basedir']);
        } else {
            $basedir = $upload_dir['basedir'];
        }        
        $this->basebackupdir = $basedir;
        $realpath = $this->realPath($basedir);
        
        if ($realpath) {
            return $realpath;
        } else {
            return $upload_dir['basedir'];
        }
    }
    
    /**
     * Returns the TRUE WordPress root wp_uploads_dir information that is compatible to both single-site/multisite 
     * and even non-standard/customized multisite configurations
     * @return array|mixed|string|NULL
     * @tested Codexonics\PrimeMoverFramework\classes\PrimeMoverSystemInitialization::getBackupBaseDirectory()
     */
    public function getInitializedWpRootUploads($clear_cache = false)
    {
        $main_site_blog_id = 0;
        if (is_multisite()) {
            $main_site_blog_id = $this->getMainSiteBlogId();            
        }
        
        $this->switchToBlog($main_site_blog_id);        
        $upload_dir = $this->getWpUploadsDir($clear_cache, false, true);        
        $this->restoreCurrentBlog(); 
        
        return $upload_dir;
    }
    
    /**
     * Get realpath
     * @param string $path
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsBaseBackupDirectoryWithAllowFiltersFalse()
     */
    public function realPath($path = '')
    {
        $realpath = realpath($path);
        if (false === $realpath) {
            return $realpath;
        }
        
        if (wp_normalize_path($realpath) === wp_normalize_path($path)) {
            return $realpath;
        } 
        
        if (path_is_absolute($path)) {
            return $path;
        }
        
        return $realpath;        
    }
    
    /**
     * Initialize export directory protection
     * Hooked to `admin_init` right after WP_FileSystem Initialization
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itInitializesExportDirectoryProtection()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotInitializeDirectoryProtectionNotAuthorized()
     */
    public function initializeExportDirectoryProtection()
    {
        global $wp_filesystem;
        $export_folder = $this->getMultisiteExportFolderPath();
        if (! $this->getSystemAuthorization()->isUserAuthorized() || ! $wp_filesystem->exists($export_folder)) {
            return;
        }
        $this->initializeIndexHTML($export_folder);
        $this->initializeHtAccess($export_folder);
        $downloads_tmp_folder = $this->getTmpDownloadsFolder();
        
        if ($downloads_tmp_folder) {
            $downloads_tmp_folder = wp_normalize_path($downloads_tmp_folder);
            $this->initializeIndexHTML($downloads_tmp_folder);
            $this->initializeHtAccess($downloads_tmp_folder, true);            
        }
        
        $site_specific_export_folder = $this->getSiteSpecificFolderCreated();
        if ($site_specific_export_folder) {
            $this->camouflageFolders($site_specific_export_folder);
        }
        
        $lock_folder = $this->getLockFilesFolder();
        if ($lock_folder) {
            $lock_folder = wp_normalize_path($lock_folder);
            $this->initializeIndexHTML($lock_folder);
        }
        
        $import_folder = $this->getDefaultImportFolder();
        if ($wp_filesystem->exists($import_folder)) {
            $this->camouflageFolders($import_folder);
        }
    }
    
    /**
     * Camouflage folders
     * @param string $folder
     */
    public function camouflageFolders($folder = '')
    {
        if (!$this->getSystemAuthorization()->isUserAuthorized()) {
            return;
        }
        $this->initializeIndexHTML($folder);
        $this->initializeHtAccess($folder, false, $this->getSpecificDirHtAccessDirective());
        $this->initializeFileCover($folder, 'web.config', false, $this->getWebConfigDirective());
    }
    
    /**
     * Initialize files cover
     * @param string $export_folder
     * @param string $filename
     * @param boolean $add_wprime_mime_type
     * @param string $directive
     */
    protected function initializeFileCover($export_folder = '', $filename = '.htaccess', $add_wprime_mime_type = false, $directive = '')
    {
        if (!$export_folder) {
            return;
        }
        global $wp_filesystem;
        $file = $export_folder . $filename;
        
        if ($add_wprime_mime_type) {
            $directive = $this->returnAddTypeWprimeMime();
        }
        if ($wp_filesystem->exists($file)) {
            return;
        }
        $wp_filesystem->put_contents($file, $directive, FS_CHMOD_FILE);
    }
    
    /**
     * Get specific export dir htaccess directive
     * @return string
     */
    protected function getSpecificDirHtAccessDirective()
    {
        
        return '<IfModule mod_mime.c>
AddType application/x-tar .wprime
</IfModule>
<IfModule mod_dir.c>
DirectoryIndex index.php
</IfModule>
<IfModule mod_autoindex.c>
Options -Indexes
</IfModule>';   
        
    }
    
    /**
     * Get web.config directive (IIS servers)
     * @return string
     */
    protected function getWebConfigDirective()
    {
        
        return '<configuration>
<system.webServer>
<staticContent>
<remove fileExtension=".wprime" />
<mimeMap fileExtension=".wprime" mimeType="application/x-tar" />
</staticContent>
<defaultDocument>
<files>
<remove value="index.php" />
<add value="index.php" />
</files>
</defaultDocument>
<directoryBrowse enabled="false" />
</system.webServer>
</configuration>';
        
    }
    
    /**
     * Return addType mime directive for WPRIME package
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itInitializesExportDirectoryProtection()
     */
    protected function returnAddTypeWprimeMime()
    {
        $directive = '';
        $directive .= '<IfModule mod_mime.c>' . PHP_EOL;
        $directive .= 'AddType application/x-tar .wprime' . PHP_EOL;
        $directive .= '</IfModule>';        
        
        return $directive;
    }
    
    /**
     * Initialize htaccess
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itInitializesExportDirectoryProtection()
     * @param string $export_folder
     * @param boolean $add_wprime_mime_type
     * @param string $directive
     */
    protected function initializeHtAccess($export_folder = '', $add_wprime_mime_type = false, $directive = 'deny from all')
    {        
        $this->initializeFileCover($export_folder, '.htaccess', $add_wprime_mime_type, $directive);
    }
    
    /**
     * Initialize basic index HTML
     * @param string $path
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itInitializesExportDirectoryProtection()
     */
    protected function initializeIndexHTML($path = '')
    {
        if ( ! $path ) {
            return;
        }
        global $wp_filesystem;
        $file = $path . 'index.html';
        $this->putContents($wp_filesystem, $file, '<!DOCTYPE html><html lang="en"><title>Silence is gold</title>');
        
        $file = $path . 'index.php';
        $this->putContents($wp_filesystem, $file, '<?php //Silence is gold');
    }
    
    /**
     * Put contents
     * @param $wp_filesystem
     * @param string $file
     * @param string $data
     */
    protected function putContents($wp_filesystem = null, $file = '', $data = '')
    {
        if ($wp_filesystem->exists($file)) {
            return;
        }
        
        $wp_filesystem->put_contents($file, $data, FS_CHMOD_FILE);
    }
    
    /**
     * Get multisite export folder slug
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsMultisiteExportFolderSlug()
     */
    public function getMultisiteExportFolderSlug()
    {
        return $this->multisite_export_folder;
    }
    
    /**
     * Initialize export dir identity
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itInitializesExportDirIdentity() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotInitializeExportDirIdentityNotAuthorized()
     */
    public function initializeExportDirIdentity()
    {
        global $wp_filesystem;
        $export_folder = $this->getMultisiteExportFolderPath();
        if (! $this->getSystemAuthorization()->isUserAuthorized() || ! $wp_filesystem->exists($export_folder)) {
            return;
        }
        
        $current_domain = $this->getDomain();
        $domain_hash = hash('sha256', $current_domain);
        $file = $export_folder . '.export_identity';
        $directive = $domain_hash;
        if ($wp_filesystem->exists($file)) {
            return;
        }
        $wp_filesystem->put_contents($file, $directive, FS_CHMOD_FILE);
    }
    
    /**
     * Initialize troubleshooting log
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itInitializesTroubleshootingLog()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotInitializeTroubleShootingLogNotAuthorized()
     */
    public function initializeTroubleShootingLog()
    {
        $this->initializeLogsHelper('migration');
    }
    
    /**
     * Initialize site info log
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itInitializesSiteInfoLog() 
     */
    public function initializeSiteInfoLog()
    {
        $this->initializeLogsHelper('siteinformation');
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itInitializesTroubleshootingLog()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotInitializeTroubleShootingLogNotAuthorized()
     * @param string $logtype
     */
    protected function initializeLogsHelper($logtype = 'migration')
    {
        global $wp_filesystem;
        $export_folder = $this->getMultisiteExportFolderPath();
        if (! $this->getSystemAuthorization()->isUserAuthorized() || ! $wp_filesystem->exists($export_folder)) {
            return;
        }
        $log_file = $this->generateTroubleShootingLogFileName($logtype);
        $file = $export_folder . $log_file;
        $content = '';
        if ($wp_filesystem->exists($file)) {
            return;
        }
        $wp_filesystem->put_contents($file, $content, FS_CHMOD_FILE); 
        
    }
        
    /**
     * Get Multisite_export_folder_created
     * @compatible 5.6
     * @tested TestMigrationSystemInitialization::itCreatesPrimeMoverFolder()
     * @param boolean $doing_import
     * @return boolean
     */
    public function getMultisiteExportFolderCreated($doing_import = false)
    {
        if ($doing_import) {
            return true;
        }
        return $this->multisite_export_folder_created;
    }
    
    /**
     * Get multisite_wp_filesystem_initialized
     * @compatible 5.6
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotInitializeWhenNotUsingDirectMethod()
     */
    public function getMultisiteWpFilesystemInitialized()
    {
        return $this->multisite_wp_filesystem_initialized;
    }
    
    /**
     * Get zip mime types
     * @compatible 5.6
     * @return array
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverImportUtilities::itShowsCoreImportButton() 
     */
    public function getZipMimeTypes()
    {
        return apply_filters('prime_mover_download_zip_other_mimes', $this->zip_mime_types);
    }
    
    /**
     * Get plugin URI
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsPluginUri()
     */
    public function getPluginUri()
    {
        return $this->plugin_uri;
    }
    /**
     * Not publicly overridable
     * Get plugin file
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsPluginFile()
     */
    public function getPluginFile()
    {
        return $this->plugin_file;
    }

    /**
     * Get WordPress dB connection instance
     * @return NULL|string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsConnectionInstance()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsNullConnectionInstanceIfNotMySQLi() 
     */
    public function getConnectionInstance()
    {
        $connection = null;
        global $wpdb;
        
        if (property_exists($wpdb, 'use_mysqli')) {
            $use_mysqli_defined	= $wpdb->__get('use_mysqli');
            if ($use_mysqli_defined) {
                $connection	=$wpdb->dbh;
            }
        } else {
            $connection	=$wpdb->dbh;
        }
        
        return $connection;
    }
    
    /**
     * Sets temporary import package path
     * @param string $unzipped_directory
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTemporaryImportPackagePathFromGetter()
     * @compatible 5.6
     */
    public function setTemporaryImportPackagePath($unzipped_directory ='') 
    {
        $this->currentimportpackagepath = $unzipped_directory;        
    }

    /**
     * Sets temporary export package path
     * @param string $temp_folder_path
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTemporaryExportPackagePathFromGetter() 
     * @compatible 5.6
     */
    public function setTemporaryExportPackagePath($temp_folder_path = '') 
    {
        $this->currentexportpackagepath = $temp_folder_path;
    }
    
    /**
     * Gets temporary import package path
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTemporaryImportPackagePathFromGetter()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTemporaryImportPackagePathFromFilter() 
     */
    public function getTemporaryImportPackagePath($import_blogid = 0) 
    {        
        if ( ! $import_blogid ) {
            $import_blogid = $this->getImportBlogID();
        }        
        if ( ! $this->currentimportpackagepath && $import_blogid) {
            $ret = apply_filters('prime_mover_get_import_progress', [], $import_blogid);
            if ( ! empty($ret['unzipped_directory']) ) {
                return $ret['unzipped_directory'];
            }
        }
        return $this->currentimportpackagepath;       
    }
    
    /**
     * Gets temporary export package path
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTemporaryExportPackagePathFromGetter() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTemporaryExportPackagePathFromFilter() 
     * @compatible 5.6
     */
    public function getTemporaryExportPackagePath($export_blogid = 0) 
    {
        if ( ! $export_blogid ) {
            $export_blogid = $this->getExportBlogID(); 
        }               
        if ( ! $this->currentexportpackagepath && $export_blogid) {
            $ret = apply_filters('prime_mover_get_export_progress', [], $export_blogid);
            if ( ! empty($ret['temp_folder_path']) ) {
                return $ret['temp_folder_path'];
            }
        }
        return $this->currentexportpackagepath;
    }
    
    /**
     * Sets blog ID under import
     * @param int $blog_id
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDoesNotDeletePackageUserCreatedPackage() 
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDeletesPackageOnFatalError() 
     */
    public function setImportBlogID($blog_id = 0) 
    {
        if ($blog_id) {
            $this->import_blog_id = $blog_id; 
        }               
    }
    
    /**
     * Sets blog ID under export
     * @param int $blog_id
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsExportBlogId()
     */
    public function setExportBlogID($blog_id = 0) 
    {
        $this->export_blog_id = $blog_id;
    }
    
    /**
     * Get import blog ID under import
     * @compatible 5.6
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDoesNotDeletePackageUserCreatedPackage() 
     */
    public function getImportBlogID() 
    {
        return $this->import_blog_id;        
    }
    
    /**
     * Get export blog ID
     * @compatible 5.6
     * @return number
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsExportBlogId()
     */
    public function getExportBlogID() 
    {
        return $this->export_blog_id;        
    }
    
    /**
     * Set import zip path
     * @compatible 5.6
     * @param string $import_zip
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDeletesPackageOnFatalError() 
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDoesNotDeletePackageUserCreatedPackage() 
     */
    public function setImportZipPath($import_zip = '') 
    {
        $this->current_import_zip_path = $import_zip;        
    }
    
    /**
     * Get import zip path
     * @compatible 5.6
     * @return string
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverErrorHandlers::itDeletesPackageOnFatalError()
     */
    public function getImportZipPath() 
    {
        return $this->current_import_zip_path;         
    }
    
    /**
     * Set export zip path
     * @compatible 5.6
     * @param string $import_zip
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsExportZipPath() 
     */
    public function setExportZipPath($export_zip = '')
    {
        $this->export_zip_path = $export_zip;
    }
    
    /**
     * Get export path
     * @return string
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsExportZipPath() 
     */
    public function getExportZipPath($export_blogid = 0) 
    {
        if (!$export_blogid ) {
            $export_blogid = $this->getExportBlogID();
        }
        if (!$this->export_zip_path && $export_blogid) {
            $ret = apply_filters('prime_mover_get_export_progress', [], $export_blogid);
            if ( ! empty($ret['target_zip_path']) ) {
                return $ret['target_zip_path'];
            }
        }
 
        return $this->export_zip_path;        
    }
    
    /**
     * Sets legacy multisite status
     * @param bool $legacy
     * @compatible 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsTrueIfLegacyMultisite() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsFalseIfNotLegacyMultisite()
     */
    public function setLegacyMultisite($legacy = false ) 
    {
        $this->is_legacy = $legacy;
    }
    
    /**
     * Checks if site is legacy multisite
     * @compatible 5.6
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsTrueIfLegacyMultisite() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsFalseIfNotLegacyMultisite()
     */
    public function getLegacyMultisite() 
    {
        return $this->is_legacy;
    }
    
    /**
     * Get Processing plugin path
     * @compatible 5.6
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsProcessingPluginPath()
     */
    public function getProcessingPluginPath() 
    {
        $lock_files_directory = trailingslashit(wp_normalize_path($this->getLockFilesFolder()));
        return $lock_files_directory . '.prime_mover_processing_plugin';
    }
    
    /**
     * Get download URL given parameters
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverExporter::itGeneratesDownloadURLWhenAllSet()
     * @param array $args
     * @param boolean $admin_mode
     * @return string
     */
    public function getDownloadURLGivenParameters($args = [], $admin_mode = false) 
    {
        $url = network_site_url();
        if ($admin_mode) {
            if (is_multisite()) {
                $url = network_admin_url();
            } else {
                $url = admin_url();
            }
        }
        return add_query_arg($args, $url); 
    }
    
    /**
     * Get error log option of blog
     * @param number $blog_id
     * @return string
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itCleansBackupDirectoryUponRequests()
     * @tested PrimeMoverFramework\Tests\TestPrimeMoverSystemUtilities::itCleansAllBackupDirectoryFilesUponRequests() 
     */
    public function getErrorLogOptionOfBlog($blog_id = 0)
    {
        return 'prime_mover_error_hash_' .  $blog_id;
    }
    
    /**
     * Get IP address of user
     * @return string
     * @compatibility 5.6
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsUserIpOnRemoteAddr() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsUserIpOnHttpClientIp()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsUserIpOnHttpForwardIp() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsUserIpOnShell() 
     */
    public function getUserIp()
    {
        $ip = '';
        if (defined('PRIME_MOVER_DONT_TRACK_USERIP') && PRIME_MOVER_DONT_TRACK_USERIP) {
            return 'prime_mover_user_anonymous';
        }
        if (defined('PRIME_MOVER_DOING_SHELL_ARCHIVE') &&
            PRIME_MOVER_DOING_SHELL_ARCHIVE &&
            "cli" === php_sapi_name() &&
            defined('PRIME_MOVER_COPY_MEDIA_SHELL_USER_IP') &&
            PRIME_MOVER_COPY_MEDIA_SHELL_USER_IP) {
                return PRIME_MOVER_COPY_MEDIA_SHELL_USER_IP;
        }
        if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        if (!$ip || !is_string($ip)) {
            return $ip;
        }
        $exploded = explode(",", $ip);
        if (is_array($exploded) && !empty($exploded[0])) {
            $ip = trim($exploded[0]);
        }
        
        return $ip;
    }
    
    /**
     * Get user agent
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsUserAgent() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsUserAgentFromShell() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsEmptyIfUserAgentIsNotSet() 
     */
    public function getUserAgent()
    {
        if (defined('PRIME_MOVER_DOING_SHELL_ARCHIVE') &&
            PRIME_MOVER_DOING_SHELL_ARCHIVE &&
            "cli" === php_sapi_name() &&
            defined('PRIME_MOVER_COPY_MEDIA_SHELL_USER_AGENT') &&
            PRIME_MOVER_COPY_MEDIA_SHELL_USER_AGENT) {
                return PRIME_MOVER_COPY_MEDIA_SHELL_USER_AGENT;
            }
            
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            return $_SERVER['HTTP_USER_AGENT'];
        } else {
            return '';
        }
    }
    
    /**
     * Generate hash by user
     * @return boolean|string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGeneratesHashByUser() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsFalseToGenerateHashIfUnauthorized()
     */
    public function generateHashByUser()
    {
        if (! $this->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }
        $user_ip = $this->getUserIp();
        if (! $user_ip) {
            return false;
        }
        $user_id = get_current_user_id();
        $browser = $this->getUserAgent();
        
        if (defined('SECURE_AUTH_SALT') && SECURE_AUTH_SALT) {
            $string = $browser . $user_ip . $user_id . SECURE_AUTH_SALT;
        } else {
            $string = $browser . $user_ip . $user_id;
        }
        
        return hash('sha256', $string);
    }
    
    /**
     * Generate troubleshooting log name
     * @param string $logtype
     * @return boolean|string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGeneratesMigrationTroubleshootingFilename()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGeneratesSiteInformationLogFileName()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotGenerateLogFileIfNotAuthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotGenerateLogFileIfHashIsFalse()
     */
    public function generateTroubleShootingLogFileName($logtype = 'migration')
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return false;
        }
        $user_hash = $this->generateHashByUser();    
        if ( ! $user_hash ) {
            return false;
        }
        $logfilename = '_migration.log';
        if ('siteinformation' === $logtype) {
            $logfilename = '_siteinformation.log';
        }        
        return $user_hash . $logfilename;
    }
    
    /**
     * Check if we are using core logging mode
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsCoreMigrationTroubleshootingLogPath()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsCustomerMigrationTroubleshootingLogPath()
     */
    private function coreLoggingMode()
    {
        $customer = apply_filters('prime_mover_is_loggedin_customer', false);
        if ($customer) {
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Get troubleshooting log path
     * @param string $logtype
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsCoreMigrationTroubleshootingLogPath() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsCustomerMigrationTroubleshootingLogPath()
     */
    public function getTroubleShootingLogPath($logtype = 'migration')
    {        
        $corelogmode = false;
        if ('migration' === $logtype && $this->coreLoggingMode()) {
            $corelogmode = true;
        }
        if ($corelogmode && defined('PRIME_MOVER_LOG_PATH') && PRIME_MOVER_LOG_PATH) {         
            return PRIME_MOVER_LOG_PATH;             
        }
        $export_path = $this->getMultisiteExportFolderPath();
        $log_file = $this->generateTroubleShootingLogFileName($logtype);

        return $export_path . $log_file;
    }

    /**
     * Get domain | host
     * @param string $url
     * @return array|false
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsDomainOfCurrentSite() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsEmptyStringOnParseURLError()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsDomainOfPassedURL() 
     */
    public function getDomain($url = '')
    {
        if (empty($url)) {
            $parsed = wp_parse_url(network_site_url());
        } else {
            $parsed = wp_parse_url($url);
        }
        if (empty($parsed['host'])) {
            return '';
        } else {
            return $parsed['host'];
        }
    }
    
    /**
     * Get error hash option
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsErrorHashOption()
     */
    public function getErrorHashOption()
    {
        return 'prime_mover_error_hash_';
    }
    
    /**
     * Checks if uploading chunk
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsTrueIfUploadingChunk() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsFalseIfNotUploadingChunk()
     */
    public function isUploadingChunk()
    {
        $uploader_identity = $this->getUploaderIdentity();
        $user_hash = $this->generateHashByUser();
        
        return ($uploader_identity === $user_hash);      
    }
    
    /**
     * Return uploader identity
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsUploaderIdentityIfUploadingInAdmin() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEmptyIdentityIfNotUploadingInAdmin()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEmptyIdentityIfUnauthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEmptyIdentityIfUploadingIsFalse()
     */
    public function getUploaderIdentity()
    {
        return $this->uploaderidentity;
    }
    
    /**
     * Set user uploader identity
     * @param boolean $uploading
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsUploaderIdentityIfUploadingInAdmin() 
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEmptyIdentityIfNotUploadingInAdmin()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEmptyIdentityIfUnauthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEmptyIdentityIfUploadingIsFalse()
     */
    public function setUploaderIdentity($uploading = false)
    {        
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            $this->uploaderidentity = '';
            return;
        } 
        $user_hash = $this->generateHashByUser();
        if ( false === $uploading) {
            $this->uploaderidentity = '';
        } elseif ($this->getImportBlogID() && is_admin() && true === $uploading) {
            $this->uploaderidentity = $user_hash;
        } else {
            $this->uploaderidentity = '';
        }
    }
    
    /**
     * Get core components
     * @return array|mixed|NULL|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsCoreComponents()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotGetCoreComponentsIfUnAuthorized()
     */
    public function getCoreComponents()
    {
        $corecomponents = [];
        if ( ! $this->getSystemAuthorization()->isUserAuthorized()) {
            return $corecomponents;
        }         
        $corecomponents[] = plugin_basename(PRIME_MOVER_MAINPLUGIN_FILE);
        return apply_filters('prime_mover_get_core_components', $corecomponents);
    }
    
    /**
     * Set encrypt export data
     * @param boolean $encrypt
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsFalseEncryptionExportDataUnauthorized()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEncryptionExportDataIfAuthorized() 
     */
    public function setEncryptExportData($encrypt = false)
    {
        if ( $this->getSystemAuthorization()->isUserAuthorized()) {
            $this->encrypt_export_data = $encrypt;     
        } 
           
    }
    
    /**
     * Checks if we need to encrypt export data
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsEncryptedExportDataFromProgress()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEncryptionExportDataIfAuthorized()
     * @param array $ret
     * @return boolean
     */
    public function getMaybeEncryptExportData($ret = [])
    {
        $blog_id = $this->getExportBlogID();
        if ($blog_id && !$this->encrypt_export_data) {
            if (empty($ret)) {
                $ret = apply_filters('prime_mover_get_export_progress', [], $blog_id);
            }            
            if (isset($ret['enable_db_encryption'])) {
                return $ret['enable_db_encryption'];
            }
        }
        return $this->encrypt_export_data;        
    }

    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itRandomizesDbPrefix()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotGetRandomizesDbPrefixIfUnAuthorized()
     * Set randomize dB prefix
     * @param boolean $randomize
     */
    public function setRandomizeDbPrefix($randomize = false)
    {
        if ( $this->getSystemAuthorization()->isUserAuthorized() && $randomize) {
            $this->randomize_db_prefix = $randomize;
        }        
    }
    
    /**
     * Checks if we need to randomize dB prefix
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itRandomizesDbPrefix()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsRandomPrefixFromSavedProgress() 
     * @param array $ret
     * @return boolean
     */
    public function getMaybeRandomizeDbPrefix($ret = [])
    {
        $blog_id = $this->getExportBlogID();
        if ( ! $this->randomize_db_prefix && $blog_id) {
            if (empty($ret)) {
                $ret = apply_filters('prime_mover_get_export_progress', [], $blog_id);
            }            
            if (isset($ret['mayberandomizedbprefix'])) {
                return $ret['mayberandomizedbprefix'];
            }
        }       
        return $this->randomize_db_prefix;
    }
    
    /**
     * Sets random prefix
     * @param string $random_prefix
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsRandomDbPrefix()
     */
    public function setRandomDbPrefix($random_prefix = '')
    {
        $this->random_db_prefix = strtolower($random_prefix);
    }
    
    /**
     * Gets random prefix
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsRandomDbPrefix()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsRandomPrefixFromActiveFilter()
     * @mainsitesupport_affected
     * @param array $ret
     * @return string
     */
    public function getRandomPrefix($ret = [])
    {
        $blog_id = $this->getExportBlogID();
        if ( ! $this->random_db_prefix && $blog_id) {
            if (empty($ret)) {
                $ret = apply_filters('prime_mover_get_export_progress', [], $blog_id);
            }            
            if (isset($ret['randomizedbprefixstring'])) {
                return $ret['randomizedbprefixstring'];
            }
        }  
        return $this->random_db_prefix;
    }
    
    /**
     * Set encrypted password for zip
     * @param \ZipArchive $zip
     * @return \\ZipArchive
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itSetsEncryptionPassword()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itDoesNotSetEncryptionPasswordKeyNotSet()
     */
    public function setEncryptionPassword(\ZipArchive $zip)
    {
        $key = $this->getDbEncryptionKey();
        if ($key) {
            $zip->setPassword($key);
        }
        return $zip;
    }
    
    /**
     * Common wrong target site error
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsWrongSiteImportError()
     */
    public function returnCommonWrongTargetSiteError()
    {
        return esc_html__('Wrong import site package! Please check that the import zip package is correct for this site.', 'prime-mover');
    }
    
    /**
     * Get common media decryption error
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsCommonMediaDecryptionError()
     */
    public function returnCommonMediaDecryptionError()
    {
        return esc_html__('This package cannot be restored because of media decryption error. To fix this, please upgrade Libzip version to 1.2.0 or higher.', 'prime-mover');
    }
    
    /**
     * Get prime mover large filesize
     * @return mixed|NULL|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsPrimeMoverLargeFileSize() 
     */
    public function getPrimeMoverLargeFileSize()
    {
        return apply_filters('prime_mover_large_filesize', PRIME_MOVER_LARGE_FILESIZE);
    }
    
    /**
     * Get prime mover large filesize
     * @return mixed|NULL|array
     */
    public function getPrimeMoverLargeFileSizeStream()
    {
        return apply_filters('prime_mover_large_filesize_stream', PRIME_MOVER_LARGE_FILESIZE_STREAM);
    }
    
    /**
     * Get auth key
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsAuthKeyIfSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsEmptyIfAuthKeyIsNotSet()
     */
    public function getAuthKey()
    {
        $auth_key = '';
        if (defined('AUTH_KEY') && AUTH_KEY) {
            $auth_key = AUTH_KEY;
        }
        return $auth_key;
    }
    
    /**
     * Get http host if defined
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsHttpHostIfSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsEmptyHttpHostIfNotSet() 
     */
    public function getHttpHost()
    {
        $ret = '';
        if ( ! empty($_SERVER['HTTP_HOST']) ) {
            $ret = $_SERVER['HTTP_HOST'];
        }
        return $ret;
    }
    
    /**
     * Get http host if defined
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsRequestMethodIfSet()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsEmptyIfRequestMethodIsNotSet()
     */
    public function getRequestMethod()
    {
        $ret = '';
        if ( ! empty($_SERVER['REQUEST_METHOD']) ) {
            $ret = $_SERVER['REQUEST_METHOD'];
        }
        return $ret;
    }
    
    /**
     * Is CLI environment
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itReturnsTrueIfCli() 
     */
    public function isCliEnvironment()
    {
        return ('cli' === php_sapi_name());
    }
    
    /**
     * Get start time
     * @return mixed
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsValidStartTime()
     */
    public function getStartTime()
    {
        return microtime(true);
    }
    
    /**
     * Get cli must use plugin path
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsCliPluginPath() 
     */
    public function getCliMustUsePluginPath()
    {
        return trailingslashit(WPMU_PLUGIN_DIR) . PRIME_MOVER_MUST_USE_PLUGIN_FILENAME;
    }
    
    /**
     * Get must use plugin constant script path
     * @return string
     */
    public function getCliMustUseConstantScriptPath()
    {
        return trailingslashit(WPMU_PLUGIN_DIR) . PRIME_MOVER_MUST_USE_PLUGIN_CONSTANT_SCRIPT;
    }
    
    /**
     * Get migration tools URL
     * @param boolean $relative
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsMigrationToolsUrlInSingleSite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsMigrationToolsUrlInMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsRelativeMigrationToolsUrl()
     */
    public function getMigrationToolsUrl($relative = false)
    {
        $migration_tools = admin_url('tools.php?page=migration-tools');
        if (is_multisite() && is_network_admin()) {
            $migration_tools = network_admin_url( 'sites.php');
        }
        
        if ($relative) {
            return wp_make_link_relative($migration_tools);
        }
        
        return $migration_tools;
    }
    
    /**
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsZipArchiveInstance()
     * Get zip archive instance
     * @param boolean $return_wp_error
     * @return \WP_Error|\ZipArchive
     */
    public function getZipArchiveInstance($return_wp_error = false)
    {
        if ($return_wp_error && !extension_loaded('zip')) {
            return new WP_Error( 'prime_mover_no_server_zip_extension', __('The requested process requires PHP Zip extension enabled.', 'prime-mover'));
        }
        
        return new ZipArchive();
    }
    
    /**
     * Get directory iterator instance
     * @param string $dir
     * @return \DirectoryIterator
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsDirectoryIteratorInstance()
     */
    public function getDirectoryIteratorInstance($dir = '')
    {
        return new DirectoryIterator($dir);
    }
    
    /**
     * Generate Cli re-processing tmp name
     * @param array $ret
     * @param string $process_id
     * @param string $shell_progress_key
     * @param boolean $forcecreate
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGeneratesCliReprocessingTmpName()
     */
    public function generateCliReprocessingTmpName($ret = [], $process_id = '', $shell_progress_key = '', $forcecreate = false)
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() ) {
            return '';
        }
        
        $tmpname = ''; 
        if (empty($ret['media_tmp_file'])) {
            $forcecreate = true;
        }       
        
        if ( ! empty( $ret['shell_progress_key'] ) ) {
            $shell_progress_key = $ret['shell_progress_key'];
        }
        
        if ( ! empty( $ret['process_id'] )) {
            $process_id = $ret['process_id'];
        }
        
        if ($shell_progress_key && $process_id) {
            $string = $shell_progress_key . $process_id;
            $tmpname = hash('sha256', $string);  
        }
        
        if ( ! $tmpname ) {
            return '';
        }       
        
        $tmpdir = $this->getTmpDirFromTmpFile($forcecreate, $ret);   
        if ( ! $tmpdir ) {
            return '';
        }
        return $tmpdir . $tmpname;
    }
    
    /**
     * Get tmpdir from tmp file
     * @param boolean $forcecreate
     * @param array $ret
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsTmpDirFromTmpFile() 
     */
    protected function getTmpDirFromTmpFile($forcecreate = false, $ret = [])
    {
        global $wp_filesystem;
        $media_tmp_file = '';
        if ( ! empty($ret['media_tmp_file'])) {     
            $media_tmp_file = $ret['media_tmp_file'];
        } 
        
        if ($forcecreate) {            
            $media_tmp_file = $this->wpTempNam();
        }        
        if ($media_tmp_file) {
            $tmpdir = dirname($media_tmp_file);
        }        
        if ( ! wp_is_writable($tmpdir)) {
            return '';
        }        
        $tmpdir = trailingslashit($tmpdir); 
        if ($forcecreate) {            
            $wp_filesystem->delete($media_tmp_file);
        }
        return $tmpdir;
    }
    
    /**
     * Get process id based on given action
     * @param string $action
     * @return void|string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsProcessIdBasedOnAction() 
     */
    public function getProcessIdBasedOnGivenAction($action = '')
    {
        if ( ! $action ) {
            return;
        }
        if ('import' === $action) {
            return $this->getImportId();
        }
        if ('export' === $action) {
            return $this->getExportId();
        }       
    }
    
    /**
     * Get MySQL PHP dump batch size
     * @return number
     * @tested Codexonics\PrimeMoverFramework\Tests\TestMigrationSystemInitialization::itGetsMySQLDumpBatchSize()
     */
    public function getMySqlDumpPHPBatchSize()
    {
        return apply_filters('prime_mover_get_runtime_setting', $this->getDbDumpBatchSizeSetting(), PRIME_MOVER_PHPDUMP_BATCHSIZE);
    }
    
    /**
     * Get default refresh upload interval
     * Outputs default 20-seconds interval for remote servers
     * And 5 seconds interval for local servers
     * @return number|string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsDefaultUploadRefreshInterval()
     */
    public function getDefaultUploadRefreshInterval()
    {
        $default = PRIME_MOVER_UPLOAD_REFRESH_INTERVAL;
        if ($this->isLocalServer()) {
            $default = PRIME_MOVER_UPLOAD_REFRESH_INTERVAL_LOCAL;
        }
        return $default;
    }
    
    /**
     * Checks if local server
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itChecksIfLocalServer()
     */
    public function isLocalServer()
    {
        if ( ! $this->getSystemAuthorization()->isUserAuthorized() ) {
            return false;
        }
        
        $input_server = $this->getUserInput('server', ['SERVER_ADDR' => $this->getPrimeMoverSanitizeStringFilter()], 'islocalserver', '', 0, true, true);
        if (empty($input_server['SERVER_ADDR'])) {
            return false;
        }
               
        $server_ip = $input_server['SERVER_ADDR'];
        if ( ! filter_var($server_ip , FILTER_VALIDATE_IP)) {
           return false;
        } 
       
        if( strpos($server_ip, '127.0.') === 0 ) {
            return true;
        }
       
        return false;
    }
    
    /**
     * Get contact us page
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsContactUsPage() 
     */
    public function getContactUsPage()
    {
        $contact_us = admin_url( 'admin.php?page=migration-panel-settings-contact');        
        if (is_multisite() && is_network_admin()) {
            $contact_us = network_admin_url( 'admin.php?page=migration-panel-settings-contact');
        }
        return $contact_us;
    }
    
    /**
     * Get upgrade URL
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsUpgradeUrlInMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itGetsUpgradeUrlInSingleSite()
     */
    public function getUpgradeUrl()
    {
        if (is_multisite()) {
            return network_admin_url( 'admin.php?page=migration-panel-settings-pricing');
            
        } else {
            return admin_url( 'admin.php?page=migration-panel-settings-pricing');
        }
    }
    
    /**
     * Get Core Wordpress Tables only
     * @param number $blog_id
     * @return array|string|string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsWpCoreTables()
     */
    public function getCoreWpTables($blog_id = 0)
    {
        $schema = '';
        if (!function_exists('wp_get_db_schema')) {
            require_once ABSPATH . 'wp-admin/includes/schema.php';
        }
        
        if (is_multisite() && $blog_id) {
            $schema = wp_get_db_schema('blog', $blog_id);
        } else {
            $schema = wp_get_db_schema('all');
        }
        
        return array_map(function($item) {
            return trim(strstr(trim($item, 'CREATE TABLE'), '(', true));
        },  array_filter(preg_split('/\r\n|\r|\n/', $schema), function($el) {
            return false !== stripos($el, 'CREATE TABLE');
        }));
    }
    
    /**
     * Default export and restore button class
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsDefaultButtonClasses() 
     */
    public function defaultButtonClasses()
    {
        $defaults = 'button button-hero button-primary';
        if (is_multisite()) {
            $defaults = 'button';
        }
        
        return $defaults;
    }
    
    /**
     * 
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsUserUnderstandMainSiteOnly()
     */
    public function getUserUnderstandMainSiteOnly()
    {
        return $this->prime_mover_user_understand_mainsite_only;
    }
    
    /**
     * Single-site compatible switch to blog
     * @param number $blog_id
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itSwitchesToBlogWhenMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itDoesNotSwitchBlogWhenSingleSite()
     */
    public function switchToBlog($blog_id = 0)
    {
        if (is_multisite() && $blog_id) {
            switch_to_blog($blog_id);
        } else {
            return;
        }
    }
    
    /**
     * Single-site compatible restoreCurrentBlog
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itRestoresCurrentBlogOnMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverSystemFunctions::itSkipsCurrentRestoreBlogOnSingleSite()
     */
    public function restoreCurrentBlog()
    {
        if (is_multisite()) {
            restore_current_blog();
        } else {
            return;
        }
    }
    
    /**
     * Prime Mover wp_tempnam() version
     * @param string $filename
     * @param string $dir
     * @return string
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itCallsWpTempNam()
     */
    public function wpTempNam($filename = '', $dir = '')
    {        
        if (!$dir) {
            $dir = trailingslashit(wp_normalize_path($this->getLockFilesFolder()));
        }
      
        return wp_tempnam($filename, $dir);
    }

    /**
     * Checks if we are on backup menu page by using page hook
     * @param string $hook
     * @return boolean
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itReturnsTrueIfBackupMenuPageInMultisite()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itReturnsFalseIfNotBackupMenu()
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverBackupMenus::itReturnsTrueIfBackupMenuPageInSingleSite()
     */
    public function isBackupsMenuPage($hook = '')
    {
        $ret = false;
        $prime_mover_slug = $this->getPrimeMoverMenuSlug();
        if ("{$prime_mover_slug}_page_migration-panel-backup-menu" === $hook || "{$prime_mover_slug}_page_migration-panel-backup-menu-network" === $hook) {
            $ret = true;
        }
        return $ret;
    }
    
    /**
     * Get instance of Prime Mover Plugin Manager
     * @return \Codexonics\PrimeMoverFramework\general\PrimeMoverMustUsePluginManager|NULL
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsPluginManagerInstance($instance=null)
     */
    public function getPrimeMoverPluginManagerInstance()
    {
        global $prime_mover_plugin_manager;
        if ($prime_mover_plugin_manager instanceof PrimeMoverMustUsePluginManager) {
            return $prime_mover_plugin_manager;
        }
        return null;
    }
    
    /**
     * Get browser file upload size limit
     * Defaults to 2GB for best user experience and cross browser compatibility
     * @return mixed|NULL|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsBrowserFileUploadsLimit()
     */
    public function getBrowserFileUploadSizeLimit()
    {
        return apply_filters('prime_mover_browser_fileupload_size_limit', PRIME_MOVER_MAX_BROWSER_UPLOAD_FILESIZE);
    }
    
    /**
     * Get Prime Mover menu slug
     * @return mixed|NULL|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsPrimeMoverSlug()
     */
    public function getPrimeMoverMenuSlug()
    {
        return apply_filters('prime_mover_menu_slug', PRIME_MOVER_DEFAULT_FREE_SLUG);
    }
    
    /**
     * Get Prime Mover plugin title
     * @return mixed|NULL|array
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsPrimeMoverPluginTitle()
     */
    public function getPrimeMoverPluginTitle()
    {
        return apply_filters('prime_mover_plugin_title', PRIME_MOVER_PLUGIN_CODENAME);
    }
    
    /**
     * Get all Prime Mover core directories
     * @param boolean $include_export_defaults
     * @return string[]
     * @tested Codexonics\PrimeMoverFramework\Tests\TestPrimeMoverInitialization::itGetsPrimeMoverCoreDirectories()
     */
    public function getPrimeMoverCoreDirectories($include_export_defaults = true)
    {
        $core_directories = [
            $this->getMultisiteExportFolder(),
            $this->getLockFilesFolder(),
            $this->getTmpDownloadsFolder()           
        ];
        
        $export_dir_default = $this->getMultisiteExportFolder('', false, false);
        if ($include_export_defaults && !in_array($export_dir_default, $core_directories)) {
            $core_directories[] = $export_dir_default;            
        }
        
        return $core_directories;        
    }

    /**
     * Get dB charset used by site
     * @return boolean|string
     */
    public function getDbCharSetUsedBySite()
    {
        global $wpdb;
        if (!is_object($wpdb)) {
            return false;
        }  
        if (!isset($wpdb->charset)) {
            return false;
        }        
        
        $charset = $wpdb->charset;       
        if (!is_string($charset)) {
            return false;
        }
       
        if (!$charset) {
            return false;
        }
       
        if (in_array($charset, $this->getDeprecatedUtf8())) {
            $charset = $wpdb->get_col_charset(_get_meta_table('post'), 'meta_key');
        }
        
        if (is_wp_error($charset) || !is_string($charset) || !$charset) {
            return false;
        }
        
        return $charset;
    }
 
    /**
     * Get dB collate used by site
     * @return boolean|string
     */
    public function getDbCollateUsedBySite()
    {
        global $wpdb;
        if (!is_object($wpdb)) {
            return false;
        }
        
        if (!isset($wpdb->collate)) {
            return false;
        }
        
        $collate = $wpdb->collate;
        if (!is_string($collate)) {
            return false;
        }
        
        return $collate;
    }
    
    /**
     * Configurable way of getting wp uploads directory.
     * 
     * If $use_default is TRUE, it will force to return wp_upload_dir().
     * If $use_default is FALSE, it will return $canonical_uploads IF SET.
     * 
     * Otherwise fallback to wp_upload_dir().
     * If $low_level is TRUE returns uncached uploads, otherwise cached values.
     * 
     * If $force_root_uploads - will return $root_uploads if set.
     * 
     * Bear in mind when using $use_default = false
     * It is because $canonical_uploads is a switched blog value if we are on a multisite.
     * 
     * @param boolean $low_level
     * @param boolean $use_default
     * @param boolean $force_root_uploads
     * @return array|mixed|string|NULL|array
     */
    public function getWpUploadsDir($low_level = false, $use_default = false, $force_root_uploads = false)
    {        
        $canonical_uploads = $this->getCanonicalUploadsInfo();
        $root_uploads = $this->getRootUploadsInfo();
        
        if (empty($canonical_uploads) || empty($root_uploads)) {
            $use_default = true;
        }
        
        if ($use_default && $low_level) {
            return wp_upload_dir(null, true, true);
        }
        
        if ($use_default) {
            return wp_upload_dir();
        }
        
        if ($force_root_uploads) {
            return $root_uploads;
        } else {
            return $canonical_uploads;
        }        
    }
    
    /**
     * Get default charset of MySQLdump based on availability and support
     * By default this should be 'utf8mb4' for modern database.
     * This default is overriden by the current charset used by the site
     * This might not be 'utf8mb4', e.g. for international languages.
     * @return string
     */
    public function getDefaultDbCharSet()
    {        
        $charset = PRIME_MOVER_UNICODE_CHARSET;
        global $wpdb;
        
        if (PRIME_MOVER_UNICODE_CHARSET === $charset && $wpdb->has_cap(PRIME_MOVER_MODERN_UNICODE_CHARSET)) {
            $charset = PRIME_MOVER_MODERN_UNICODE_CHARSET;
        }
        
        return $charset;        
    }
    
    /**
     * Target charset defaults to the current source site charset for full backup compatibility.
     * This can be forcely changed to 'utf8mb4' in PRO version that is used to migrate to utf8mb4 dB. 
     * @param array $ret
     * @return string
     */
    public function getTargetCharset($ret = [])
    {
        $target_charset_dump = '';
        $source_charset = $this->getDbCharSetUsedBySite();
        
        $target_charset_dump = $source_charset;        
        return apply_filters('prime_mover_get_target_charset',  $target_charset_dump, $ret);
    }
    
    /**
     * Get locale of exported site
     * @return string|string[]
     */
    public function getLocaleOfExportedSite()
    {
        $plugin_manager = $this->getPrimeMoverPluginManagerInstance();
        if (!is_object($plugin_manager)) {
            return get_locale();
        }
        
        if (!method_exists($plugin_manager, 'getLocale')) {
            return get_locale();
        }
        
        $locale = $plugin_manager->getLocale();
        
        return [$locale];
    }
    
    /**
     * Get fast file hashing algo
     * @return string|array|string[]
     */
    public function getFastHashingAlgo()
    {
        $fast_algo = PRIME_MOVER_DEFAULT_FAST_HASH_ALGO;        
        if (!function_exists('hash_algos')) {
            return $fast_algo;
        }
        
        $fast_algos = $this->getFastHashAlgos();
        $hash_algos = hash_algos();
        foreach ($fast_algos as $algo) {
            if (in_array($algo, $hash_algos)) {
                return $algo;
            }
        }
        
        return $fast_algo;
    }
    
    /**
     * Get Prime Mover sanitize string filter
     * @return string
     */
    public function getPrimeMoverSanitizeStringFilter()
    {
        $deprecated = false;
        if (version_compare(PHP_VERSION, '8.1.0') >= 0) {
            $deprecated = true;
        }
        
        if ($deprecated) {
            return FILTER_SANITIZE_FULL_SPECIAL_CHARS;
        }
        
        return FILTER_SANITIZE_STRING;
    }
    
    /**
     * Checks if any user has administrative privileges
     * By default checks for super admin access only
     * Otherwise if passing FALSE to this method -
     * It will check for administrator capabilities on multisite subsites too.
     * 
     * @param boolean $super_admin_check_only
     * @return boolean
     */
    public function isAdministrator($super_admin_check_only = true)
    {
        $is_administrator = false;
        if ($this->getSystemAuthorization()->isUserAuthorized()) {
            $is_administrator = true;
        }
        
        if ($super_admin_check_only) {
            return $is_administrator;
        }
        
        if (is_multisite() && current_user_can('manage_options')) {
            $is_administrator = true;
        }
       
        return $is_administrator;
    }
}