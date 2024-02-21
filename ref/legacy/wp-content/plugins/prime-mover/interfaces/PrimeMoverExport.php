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

interface PrimeMoverExport
{
    public function createTempfolderForThisSiteExport($blogid_to_export);
    public function multisiteCreateFoldername($blogid_to_export);
    public function dumpDbForExport($blogid_to_export, $tmp_folderpath);
    public function copyMediaFiles($blogid_to_export, $tmp_folderpath);
    public function generateExportFootprintConfig($blogid_to_export, $tmp_folderpath);
    public function zippedFolder($tmp_folderpath);
    public function deleteTemporaryFolder($tmp_folderpath);
    public function generateDownloadUrl($results);
}
