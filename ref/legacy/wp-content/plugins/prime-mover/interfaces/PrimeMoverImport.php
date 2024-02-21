<?php
namespace Codexonics\PrimeMoverFramework\interfaces;

/*
 * This file is part of the Codexonics.PrimeMoverFramework package.
 *
 * (c) Codexonics Ltd
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

if (! defined('ABSPATH')) {
    exit;
}

interface PrimeMoverImport
{
    public function moveImportedFilesToUploads($blogid_to_export, $files_array);
    public function unzipImportedZipPackageMigration($ret);
    public function validateImportedSiteVsPackage($ret = [], $blogid_to_import = 0, $files_array = []);
    public function compareSystemFootprintImport($ret = [], $blogid_to_import = 0, $files_array = []);
    public function updateTargetMediaFilesWithNew($ret = [], $blogid_to_import = 0, $files_array = []);
    public function markTargetSiteUploadsInformation($ret = [], $blogid_to_import = 0, $files_array = []);
    public function multisiteOptionallyImportPluginsThemes($ret = [], $blogid_to_import = 0, $files_array = []);    
    public function importDb($ret = [], $blogid_to_import = 0, $files_array = []);
    public function renameDbPrefix($ret = [], $blogid_to_import = 0, $files_array = []);
    public function searchAndReplace($ret = [], $blogid_to_import = 0, $files_array = []);
    public function activatePluginsIfNotActivated($ret = [], $blogid_to_import = 0, $files_array = []);
    public function restoreCurrentUploadsInformation($ret = [], $blogid_to_import = 0, $files_array = []);
    public function markImportSuccess($ret = [], $blogid_to_import = 0, $files_array = []);
}
