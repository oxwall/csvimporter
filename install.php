<?php

/**
 * This software is intended for use with Oxwall Free Community Software http://www.oxwall.org/ and is
 * licensed under The BSD license.

 * ---
 * Copyright (c) 2011, Oxwall Foundation
 * All rights reserved.

 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the
 * following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice, this list of conditions and
 *  the following disclaimer.
 *
 *  - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and
 *  the following disclaimer in the documentation and/or other materials provided with the distribution.
 *
 *  - Neither the name of the Oxwa  ll Foundation nor the names of its contributors may be used to endorse or promote products
 *  derived from this software without specific prior written permission.

 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED
 * AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * @author Kairat Bakytow <kainisoft@gmail.com>
 * @package ow_plugins.csv_importer
 * @since 1.0
 */

$config = OW::getConfig();

if ( !$config->configExists('csvimporter', 'step') )
{
    $config->addConfig( 'csvimporter', 'step', 'step_upload' );
}

if ( !$config->configExists('csvimporter', 'define_delimiter') )
{
    $config->addConfig( 'csvimporter', 'define_delimiter', FALSE );
}

if ( !$config->configExists('csvimporter', 'define_enclosure') )
{
    $config->addConfig( 'csvimporter', 'define_enclosure', FALSE );
}

if ( !$config->configExists('csvimporter', 'define_escape') )
{
    $config->addConfig( 'csvimporter', 'define_escape', FALSE );
}

if ( !$config->configExists('csvimporter', 'current_position') )
{
    $config->addConfig( 'csvimporter', 'current_position', 0 );
}

if ( !$config->configExists('csvimporter', 'current_first') )
{
    $config->addConfig( 'csvimporter', 'current_first', 0 );
}

if ( !$config->configExists('csvimporter', 'import_configs') )
{
    $config->addConfig( 'csvimporter', 'import_configs', array() );
}

$dbo = OW::getDbo();

$dbo->query( 'DROP PROCEDURE IF EXISTS `' . OW_DB_PREFIX . 'csvimporter_sp_get_values`;' );
$dbo->query( 'CREATE PROCEDURE `' . OW_DB_PREFIX . 'csvimporter_sp_get_values`(IN `field` VARCHAR(50))
BEGIN
    DROP TABLE IF EXISTS `' . OW_DB_PREFIX . 'csvimporter_tmp_multiselect_values`;
    CREATE TABLE `' . OW_DB_PREFIX . 'csvimporter_tmp_multiselect_values` (
        `value` varchar(255) NOT NULL,
        KEY (`value`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;

    SET @sql = CONCAT("INSERT IGNORE INTO `' . OW_DB_PREFIX . 'csvimporter_tmp_multiselect_values` SELECT ", field, " FROM `ow_csvimporter_dump`" );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END;' );

$dbo->query( 'DROP PROCEDURE IF EXISTS `' . OW_DB_PREFIX . 'csvimporter_sp_create_values`;' );
$dbo->query( 'CREATE PROCEDURE `' . OW_DB_PREFIX . 'csvimporter_sp_create_values`()
BEGIN
    DECLARE curPosition INT DEFAULT 1;
    DECLARE value VARCHAR( 255 );
    DECLARE remainder VARCHAR( 255 );
    DECLARE curString VARCHAR( 255 );
    DECLARE done INTEGER DEFAULT 0;
    DECLARE multiValues CURSOR FOR  SELECT * FROM `' . OW_DB_PREFIX . 'csvimporter_tmp_multiselect_values`;
    DECLARE CONTINUE Handler FOR SQLSTATE "02000" SET done = 1;
    
    DROP TABLE IF EXISTS `' . OW_DB_PREFIX . 'csvimporter_multiselect_values`;
    CREATE TABLE IF NOT EXISTS `' . OW_DB_PREFIX . 'csvimporter_multiselect_values` (
        `value` varchar( 255 ) NOT NULL,
        KEY ( `value` )
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;

    OPEN multiValues;
    WHILE done = 0 DO
    	FETCH multiValues INTO value;
        SET remainder = value;
        SET curPosition = 1;
        WHILE CHAR_LENGTH( remainder ) > 0 AND curPosition > 0 DO
            SET curPosition = INSTR( remainder, "," );
            
            IF curPosition = 0 THEN
            	SET curString = remainder;
            ELSE
            	SET curString = LEFT( remainder, curPosition - 1 );
            END IF;
            
            SET curString = TRIM( curString );
            
            IF curString != "" THEN
            	INSERT IGNORE INTO `' . OW_DB_PREFIX . 'csvimporter_multiselect_values` VALUES( curString );
            END IF;
            
            SET remainder = SUBSTRING( remainder, curPosition + 1 );
    	END WHILE;
    END WHILE;
    
    CLOSE multiValues;
END;' );

OW::getPluginManager()->addPluginSettingsRouteName( 'csvimporter', 'csvimporter.admin' );

OW::getLanguage()->importPluginLangs( OW::getPluginManager()->getPlugin('csvimporter')->getRootDir() . 'langs.zip', 'csvimporter' );
