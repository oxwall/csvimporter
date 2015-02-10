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
 *  - Neither the name of the Oxwall Foundation nor the names of its contributors may be used to endorse or promote products
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
 * @package ow_plugins.csv_importer.bol
 * @since 1.0
 */
class CSVIMPORTER_BOL_DumpDao extends OW_BaseDao
{
    CONST ID = 'id';
    CONST USER_ID = 'userId';
    CONST ERROR_MESSAGE = 'errorMessage';
    
    private static $classInstance;
    
    public static function getInstance()
    {
        if ( !isset(self::$classInstance) )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }
    
    public function getTableName()
    {
        return OW_DB_PREFIX . 'csvimporter_dump';
    }
    
    public function getDtoClassName()
    {
        return 'CSVIMPORTER_BOL_Dump';
    }
    
    public function insertData( array $data )
    {
        if ( empty($data) )
        {
            return FALSE;
        }
        
        $sql = array();
        
        function csvimporterCleareData( $value )
        {
            return OW::getDbo()->escapeString( $value );
        }
        
        foreach ( $data as &$val )
        {
            array_push( $sql, '("' . implode('","', array_map('csvimporterCleareData', $val)) . '")' );
        }
        
        $fields = array();

        for ( $i = 0; $i < count($data[0]); $i++ )
        {
            array_push( $fields, '`field' . $i . '`' );
        }
        
        $_fields = implode( ',', $fields );
        
        foreach ( $sql as $value )
        {
            try
            {
                $this->dbo->query( '
                    INSERT INTO `' . $this->getTableName() . '`(' . $_fields . ')
                    VALUES ' . $value );
            }
            catch ( Exception $e )
            {
                # Nothing
            }
        }
        
        return TRUE;
    }
    
    public function getDumpTableFields()
    {
        return $this->dbo->queryForList( 'SHOW COLUMNS FROM `' . $this->getTableName() . '` WHERE  `Field` NOT IN ("id","errorMessage", "userId")' );
    }
    
    public function findDumpUsers( $first, $limit )
    {
        $example = new OW_Example();
        $example->andFieldIsNull( self::USER_ID );
        $example->andFieldIsNull( self::ERROR_MESSAGE );
        $example->setLimitClause( $first, $limit );
        
        return $this->findListByExample( $example );
    }
    
    public function truncate()
    {
        $this->dbo->query( 'TRUNCATE TABLE  `' . $this->getTableName() . '`' );
    }
    
    public function findImportedUsers()
    {
        $sql = 'SELECT * 
            FROM `' . $this->getTableName() . '`
            WHERE `' . self::USER_ID . '` IS NOT NULL OR `' . self::ERROR_MESSAGE . '` IS NOT NULL
            LIMIT 0, ' . CSVIMPORTER_BOL_Service::DELETE_LIMIT;
        
        return $this->dbo->queryForObjectList( $sql, $this->getDtoClassName() );
    }
    
    public function markUninstalled( array $dumpList )
    {
        if ( empty($dumpList) )
        {
            return 0;
        }
        
        $dumpList = array_map( 'intval', $dumpList );
        
        $sql = 'UPDATE `' . $this->getTableName() . '`
            SET `' . self::USER_ID . '` = NULL, `' . self::ERROR_MESSAGE .'` = NULL 
            WHERE `' . self::ID . '` IN(' . implode(',', $dumpList) . ')';
        
        return $this->dbo->query( $sql );
    }
    
    public function countProfilesForImport()
    {
        $example = new OW_Example();
        $example->andFieldIsNull( self::USER_ID );
        $example->andFieldIsNull( self::ERROR_MESSAGE );
        
        return $this->countByExample( $example );
    }
    
    public function countImportedProfiles()
    {
        $example = new OW_Example();
        $example->andFieldIsNotNull( self::USER_ID );
        
        return $this->countByExample( $example );
    }
    
    public function countProfilesForRollback()
    {
        $example = new OW_Example();
        $example->andFieldIsNotNull( self::USER_ID );
        
        return $this->countByExample( $example );
    }
    
    public function getLog( $fields, $first, $limit )
    {
        $_fields = implode( '`,`', $fields );
        
        $sql = 'SELECT `' . self::ERROR_MESSAGE . '`, `' . $_fields . '` FROM `' . $this->getTableName() . '` 
            ORDER BY `' . self::ERROR_MESSAGE . '` DESC
            LIMIT ' . (int)$first . ', ' . (int)$limit;
        
        return $this->dbo->queryForList( $sql );
    }
    
    public function countFailImported()
    {
        $example = new OW_Example();
        $example->andFieldIsNotNull( self::ERROR_MESSAGE );
        $example->andFieldIsNull( self::USER_ID );
        
        return $this->countByExample( $example );
    }
    
    public function getMostUsedValuesByField( $field )
    {
        if ( empty($field) )
        {
            return array();
        }

        $sql = 'SELECT `' . $field . '` AS `label`, COUNT(*) AS `count`
            FROM `' . $this->getTableName() . '`
            WHERE `' . $field . '` IS NOT NULL AND LENGTH(`' . $field . '`) > 0 AND `' . $field . '` != "NULL"
            GROUP BY 1 
            ORDER BY 2 DESC, 1
            LIMIT 31';
        
        return $this->dbo->queryForList( $sql );
    }
    
    public function genMostUsedValuesForMultiselect()
    {
        $sql = 'SELECT `value` AS `label`, COUNT(*) AS `count`
            FROM `' . OW_DB_PREFIX . 'csvimporter_multiselect_values`
            WHERE `value` IS NOT NULL AND LENGTH(`value`) > 0 AND `value` != "NULL"
            GROUP BY 1 
            ORDER BY 2 DESC, 1
            LIMIT 31';
        
        return $this->dbo->queryForList( $sql );
    }
    
    public function getFieldDate( $field )
    {
        if ( empty($field) )
        {
            return NULL;
        }
        
        $sql = 'SELECT `' . $field . '`
            FROM `' . $this->getTableName() . '`
            LIMIT 0, 20';
        
        return $this->dbo->queryForColumnList( $sql );
    }
}
