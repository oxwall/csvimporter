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
class CSVIMPORTER_BOL_Service
{
    CONST FILE_NAME     = 'dump.csv';
    CONST ZIP_NAME      = 'dump.zip';
    CONST CHECK_LIMIT   = 20;
    CONST PREVIEW_LIMIT = 20;
    CONST PROCESS_LIMIT = 1000;
    CONST IMPORT_LIMIT  = 1000;
    CONST DELETE_LIMIT  = 1000;
    
    private static $classInstance;
    
    public static function getInstance()
    {
        if ( !isset(self::$classInstance) )
        {
            self::$classInstance = new self();
        }

        return self::$classInstance;
    }
    
    private $availableSeparators;
    private $filename;
    private $dumpDao;
    private $fileHandler;
    public $stepManager;

    private function __construct()
    {
        $this->availableSeparators = array( ",", '"', '\\', "'", '/', "\t", ':', ';', '#', '|', '$', '%', '^', '&', '*', '@', ' ' );
        $this->stepManager = new CSVIMPORTER_CLASS_EnumStep( OW::getConfig()->getValue('csvimporter', 'step') );
        $this->filename = OW::getPluginManager()->getPlugin( 'csvimporter' )->getPluginFilesDir() . self::FILE_NAME;
        $this->dumpDao = CSVIMPORTER_BOL_DumpDao::getInstance();
    }
    
    public function getFilename()
    {
        return $this->filename;
    }

    private function getSeparator( $separator )
    {
        $key = array_search( $separator, $this->availableSeparators );
        
        return isset( $this->availableSeparators[$key + 1] ) ? $this->availableSeparators[$key + 1] : FALSE;
    }

    private function getSeparators( $delimiter = NULL, $enclosure = NULL, $escape = NULL )
    {
        $escape = $this->getSeparator( $escape );
        
        if ( $escape === FALSE )
        {
            $escape = $this->availableSeparators[0];
            $enclosure = $this->getSeparator( $enclosure );
            
            if ( $enclosure === FALSE )
            {
                $enclosure = $this->availableSeparators[0];
                $delimiter = $this->getSeparator( $delimiter );
                
                if ( $delimiter === FALSE )
                {
                    return FALSE;
                }
            }
        }
        
        return array(
            $delimiter,
            $enclosure,
            $escape
        );
    }
    
    private function checkEnclosure( array $data )
    {
        $match = 0;
        
        foreach ( $data as $val )
        {
            if ( $val == 'NULL' )
            {
                $match++;
                continue;
            }
            
            $strlen = strlen( $val );

            if ( $strlen > 1 )
            {
                if ( $val{0} == $val{$strlen - 1} && in_array($val{0}, $this->availableSeparators) )
                {
                    $match++;
                }
            }
            else
            {
                continue;
            }
        }

        return count( array_filter(array_map('trim', $data), 'strlen') ) === $match;
    }
    
    private function checkEscape( array $data, $escape )
    {
        $count = 0;
        
        foreach ( $data as $val )
        {
            $split = str_split( $val );
            $intersect = array_intersect( $split, array('\\', "'", '"') );
            
            if ( !empty($intersect) )
            {
                if ( $escape != $split[key($intersect) - 1] )
                {
                    return TRUE;
                }
            }
            else
            {
                continue;
            }
        }
        
        return $count > 0;
    }

    private function parse( $delimiter, $enclosure, $escape )
    {
        $rowCount = 0;
        $csvDataCount = 0;
        
        fseek( $this->fileHandler, 0 );
        
        while ( ($data = fgetcsv($this->fileHandler, 0, $delimiter, $enclosure, $escape)) !== FALSE )
        {
            if ( ($count = count($data)) === 1 || $this->checkEnclosure($data) )
            {
                return FALSE;
            }

            if ( $rowCount === 0 )
            {
                $csvDataCount = $count;
                $rowCount++;
                continue;
            }

            if ( $count === $csvDataCount )
            {
                if ( $rowCount === self::CHECK_LIMIT )
                {
                    break;
                }

                $csvDataCount = $count;
                $rowCount++;
            }
            else
            {
                return FALSE;
            }
        }
        
        return $rowCount === self::CHECK_LIMIT || feof( $this->fileHandler );
    }
    
    public function parseAttempt( $filename )
    {
        if ( $this->stepManager->getCurrentStep() != CSVIMPORTER_CLASS_EnumStep::STEP_UPLOAD or 
            !file_exists($filename) or ($this->fileHandler = fopen($filename, 'r')) === FALSE )
        {
            return FALSE;
        }
        
        $result = FALSE;
        
        $delimiter = ',';
        $enclosure = '"';
        $escape = '\\';
            
        if ( $this->parse($delimiter, $enclosure, $escape) )
        {
            $result = TRUE;
        }
        else
        {
            $delimiter = $this->availableSeparators[0];
            $enclosure = $this->availableSeparators[0];
            $escape = $this->availableSeparators[0];
            
            while ( ($_result = $this->parse($delimiter, $enclosure, $escape)) !== TRUE )
            {
                if ( ($separators = $this->getSeparators($delimiter, $enclosure, $escape)) !== FALSE )
                {
                    list( $delimiter, $enclosure, $escape ) = $separators;
                }
                else
                {
                    $_result = FALSE;
                    break;
                }
            }
            
            $result = $_result;
        }

        if ( $result )
        {
            $config = OW::getConfig();
            
            $config->saveConfig( 'csvimporter', 'define_delimiter', $delimiter );
            $config->saveConfig( 'csvimporter', 'define_enclosure', $enclosure );
            $config->saveConfig( 'csvimporter', 'define_escape', $escape );
        }
        
        return $result;
    }
    
    public function getPreviewData()
    {
        if ( !in_array($this->stepManager->getCurrentStep(), 
            array(CSVIMPORTER_CLASS_EnumStep::STEP_UPLOAD,   CSVIMPORTER_CLASS_EnumStep::STEP_PREVIEW, CSVIMPORTER_CLASS_EnumStep::STEP_READY)) )
        {
            return array();
        }
        
        $config = OW::getConfig();
        
        $delimiter = $config->getValue( 'csvimporter', 'define_delimiter' );
        $enclosure = $config->getValue( 'csvimporter', 'define_enclosure' );
        $escape = $config->getValue( 'csvimporter', 'define_escape' );
        
        $rowCount = 0;
        $result = array();
        
        if ( ($handle = fopen($this->filename, 'r')) !== FALSE )
        {
            while ( ($data = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== FALSE )
            {
                if ( $rowCount === self::PREVIEW_LIMIT )
                {
                    break;
                }
                
                array_push( $result, $data );
                $rowCount++;
            }
            
            fclose( $handle );
        }
        
        return $result;
    }
    
    public function process()
    {
        if ( $this->stepManager->getCurrentStep() != CSVIMPORTER_CLASS_EnumStep::STEP_PROCESS )
        {
            return;
        }
        
        $config = OW::getConfig();
        
        $delimiter = $config->getValue( 'csvimporter', 'define_delimiter' );
        $enclosure = $config->getValue( 'csvimporter', 'define_enclosure' );
        $escape = $config->getValue( 'csvimporter', 'define_escape' );
        $currentPosition = $config->getValue( 'csvimporter', 'current_position' );
        
        $rowCount = 0;
        $insertData = array();
        
        if ( ($handle = fopen($this->filename, 'r')) !== FALSE )
        {
            fseek( $handle, $currentPosition );
            
            while ( ($data = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== FALSE )
            {
                if ( $rowCount === self::PROCESS_LIMIT )
                {
                    break;
                }
                elseif ( $rowCount === (self::PROCESS_LIMIT - 1) )
                {
                    $currentPosition = ftell( $handle );
                }
                
                array_push( $insertData, $data );
                $rowCount++;
            }
            
            $this->dumpDao->insertData( $insertData );
            
            if ( feof($handle) )
            {
                $currentPosition = 0;
                $this->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_READY), FALSE );
            }
            
            fclose( $handle );
            $config->saveConfig( 'csvimporter', 'current_position', $currentPosition );
        }
    }
    
    public function import()
    {
        if ( $this->stepManager->getCurrentStep() != CSVIMPORTER_CLASS_EnumStep::STEP_IMPORT )
        {
            return;
        }
        
        $dumpUsers = $this->getDumpUsers();
        
        if ( !empty($dumpUsers) )
        {
            $userService = BOL_UserService::getInstance();
            $language = OW::getLanguage();

            $importConfigs = get_object_vars( json_decode(OW::getConfig()->getValue('csvimporter', 'import_configs')) );
            $fields = get_object_vars( $importConfigs['fields'] );
            $dateFormat = @get_object_vars( $importConfigs['dateFormat'] );
            $questions = $this->getAssocQuestions( array_keys($fields) );

            $progressionQuestions = array();

            foreach ( $questions as $question )
            {
                if ( in_array($question->type, array(
                    BOL_QuestionService::QUESTION_VALUE_TYPE_MULTISELECT,
                    BOL_QuestionService::QUESTION_VALUE_TYPE_SELECT)) )
                {
                    $progressionQuestions[$question->name] = $this->getValuesForProgressionQuestion( $question->name );
                }
            }

            $username = $fields['username'];
            $password = !empty( $fields['password'] ) ? $fields['password'] : NULL;
            $email = $fields['email'];
            unset( $fields['username'] );
            unset( $fields['password'] );
            unset( $fields['email'] );

            foreach ( $dumpUsers as $user )
            {
                try
                {
                    if ( $importConfigs['options']->generatePassword || empty($user->$password) )
                    {
                        $_password = substr( md5(uniqid()), 0, 6 );
                    }
                    else
                    {
                        $_password = $user->$password;
                    }

                    if ( ($newUser = $userService->createUser(strtolower(preg_replace('/[^\w]/', '_', $user->$username)), $_password, $user->$email)) instanceof BOL_User && $importConfigs['options']->generatePassword )
                    {
                        $this->sendPasswordLetter( $newUser->id, $_password, $newUser->email );
                    }

                    $data = array();

                    foreach ( $fields as $key => $field )
                    {
                        switch ( $questions[$key]->presentation )
                        {
                            case BOL_QuestionService::QUESTION_PRESENTATION_MULTICHECKBOX:
                                $userValue = explode( ',', $user->$field );
                                $valueIntersect = array_intersect( $progressionQuestions[$key], array_map('trim', $userValue) );
                                $data[$key] = array_sum( array_keys($valueIntersect) );
                                break;
                            case BOL_QuestionService::QUESTION_PRESENTATION_RADIO:
                            case BOL_QuestionService::QUESTION_PRESENTATION_SELECT:
                                $data[$key] = ( $_key = array_search($user->$field, $progressionQuestions[$key]) ) !== FALSE ? $_key : NULL;
                                break;
                            case BOL_QuestionService::QUESTION_PRESENTATION_AGE:
                            case BOL_QuestionService::QUESTION_PRESENTATION_DATE:
                            case BOL_QuestionService::QUESTION_PRESENTATION_BIRTHDATE:
                                if ( !empty($dateFormat[$key]) && ($_dateFormat = date_create_from_format($dateFormat[$key], $user->$field)) !== FALSE )
                                {
                                    $data[$key] = date_format( $_dateFormat, 'Y/m/d' );
                                }
                                else
                                {
                                    if ( ($time = strtotime($user->$field)) !== FALSE )
                                    {
                                        $data[$key] = date( 'Y/m/d', $time );
                                    }
                                    else
                                    {
                                        $data[$key] = date( 'Y/m/d', $user->$field );
                                    }
                                }
                                break;
                            default:
                                $data[$key] = $user->$field == 'NULL' ? NULL : $user->$field;
                                break;
                        }
                    }

                    if ( !BOL_QuestionService::getInstance()->saveQuestionsData($data, $newUser->id) )
                    {
                        throw new Exception( $language->text('base', 'join_join_error') );
                    }
                    
                    if ( $importConfigs['options']->sendWelcomeMessage )
                    {
                        $this->sendWelomeLetter( $newUser );
                    }

                    $user->setUserId( $newUser->getId() );
                }
                catch ( Exception $exception )
                {
                    $user->setErrorMessage( $exception->getMessage() );
                }

                $this->dumpDao->save( $user );
            }
        }
        
        if ( (int)$this->countProfilesForImport() === 0 )
        {
            $this->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_DONE), FALSE );
        }
    }
    
    private function sendWelomeLetter( BOL_User $user )
    {
        $language = OW::getLanguage();
        
        $vars = array(
            'username' => BOL_UserService::getInstance()->getDisplayName($user->id),
        );

        $subject = $language->text( 'base', 'welcome_letter_subject', $vars );
        $template_html = $language->text( 'base', 'welcome_letter_template_html', $vars );
        $template_text = $language->text( 'base', 'welcome_letter_template_text', $vars );

        $mail = OW::getMailer()->createMail();
        $mail->addRecipientEmail( $user->email );
        $mail->setSubject( $subject );
        $mail->setHtmlContent( $template_html );
        $mail->setTextContent( $template_text );

        OW::getMailer()->addToQueue( $mail );
    }
    
    private function sendPasswordLetter( $userId, $password, $email )
    {
        $language = OW::getLanguage();
        
        $vars = array(
            'username' => BOL_UserService::getInstance()->getDisplayName($userId),
            'password' => $password
        );

        $subject = $language->text( 'csvimporter', 'generate_password_subject', $vars );
        $template_html = $language->text( 'csvimporter', 'generate_password_template_html', $vars );
        $template_text = $language->text( 'csvimporter', 'generate_password_template_text', $vars );

        $mail = OW::getMailer()->createMail();
        $mail->addRecipientEmail( $email );
        $mail->setSubject( $subject );
        $mail->setHtmlContent( $template_html );
        $mail->setTextContent( $template_text );

        OW::getMailer()->addToQueue( $mail );
    }
    
    private function getValuesForProgressionQuestion( $questionName )
    {
        if ( empty($questionName) )
        {
            return array();
        }
        
        $result = array();
        $values = BOL_QuestionValueDao::getInstance()->findQuestionValues( $questionName );
        $questionService = BOL_QuestionService::getInstance();
        
        foreach ( $values as $value )
        {
            $result[$value->value] = $questionService->getQuestionValueLang( $questionName, $value->value );
        }
        
        return $result;
    }
    
    public function getAssocQuestions( array $questionNameList )
    {
        $result = array();
        $questions = BOL_QuestionService::getInstance()->findQuestionByNameList( $questionNameList );
        
        foreach ( $questions as $question )
        {
            $result[$question->name] = $question;
        }
        
        return $result;
    }

    public function rollback()
    {
        if ( $this->stepManager->getCurrentStep() != CSVIMPORTER_CLASS_EnumStep::STEP_ROLLBACK )
        {
            return;
        }
        
        $importedUserList = $this->dumpDao->findImportedUsers();
        $userService = BOL_UserService::getInstance();
        $dumpList = array();
        
        foreach ( $importedUserList as $user )
        {
            if ( !empty($user->userId) )
            {
                $userService->deleteUser( $user->userId );
            }
            
            $dumpList[] = $user->id;
        }

        if ( $this->dumpDao->markUninstalled($dumpList) == 0 )
        {
            $this->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_READY), FALSE );
        }
    }

    public function getImportedUsers()
    {
        return $this->dumpDao->findImportedUsers();
    }
    
    public function  truncateDumpTable()
    {
        $this->dumpDao->truncate();
    }
    
    public function getDumpUsers()
    {
        return $this->dumpDao->findDumpUsers( 0, self::IMPORT_LIMIT );
    }
    
    public function getLog( $fields, $page, $limit )
    {
        $first = ( $page - 1 ) * $limit;
        
        return $this->dumpDao->getLog( $fields, $first, $limit );
    }
    
    public function getFieldDate( $field )
    {
        return $this->dumpDao->getFieldDate( $field );
    }

    public function getStepInfo( OW_Event $event )
    {
        switch ( $this->stepManager->getCurrentStep() )
        {
            case CSVIMPORTER_CLASS_EnumStep::STEP_PROCESS:
                $event->setData( array(
                    'total' => filesize($this->getFilename()),
                    'complete' => (int)OW::getConfig()->getValue('csvimporter', 'current_position'))
                );
                break;
            case CSVIMPORTER_CLASS_EnumStep::STEP_IMPORT:
                $total = (int)$this->dumpDao->countAll();
                $complete = $total - (int)$this->countProfilesForImport();
                
                $event->setData( array(
                    'total' => $total,
                    'complete' => $complete
                ) );
                break;
            case CSVIMPORTER_CLASS_EnumStep::STEP_ROLLBACK:
                $total = (int)$this->dumpDao->countAll();
                $complete = $total - (int)$this->countProfilesForRollback();
                
                $event->setData( array(
                    'total' => $total,
                    'complete' => $complete)
                );
                break;
            case CSVIMPORTER_CLASS_EnumStep::STEP_READY:
            case CSVIMPORTER_CLASS_EnumStep::STEP_DONE:
                $event->setData( array('total' => 100, 'complete' => 100) );
                break;
        }
    }
    
    public function extractZipFile()
    {
        $plugin = OW::getPluginManager()->getPlugin( 'csvimporter' );
        $fileName = $plugin->getPluginFilesDir() . self::ZIP_NAME;
        
        if ( strcasecmp(pathinfo($fileName, PATHINFO_EXTENSION), 'zip') !== 0 )
        {
            return OW::getLanguage()->text( 'csvimporter', 'zip_error_nozip' );
        }
        
        $zip = new ZipArchive();
        
        if ( ($error = $zip->open($fileName)) === TRUE )
        {
            $zip->extractTo( $plugin->getPluginFilesDir() );
            $files = glob( $plugin->getPluginFilesDir() . '*.csv' );
            
            if ( empty($files) )
            {
                $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_noent' );
            }
            else
            {
                rename( $files[0], $plugin->getPluginFilesDir() . self::FILE_NAME );
                $error = TRUE;
            }
        }
        else
        {
            switch ( $error )
            {
                case ZIPARCHIVE::ER_EXISTS:
                    $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_exists' );
                    break;
                case ZipArchive::ER_INCONS:
                    $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_incons' );
                    break;
                case ZipArchive::ER_INVAL:
                    $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_inval' );
                    break;
                case ZipArchive::ER_MEMORY:
                    $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_memory' );
                    break;
                case ZipArchive::ER_NOENT:
                    $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_noent' );
                    break;
                case ZipArchive::ER_NOZIP:
                    $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_nozip' );
                    break;
                case ZipArchive::ER_OPEN:
                    $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_open' );
                    break;
                case ZipArchive::ER_READ:
                    $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_read' );
                    break;
                case ZipArchive::ER_SEEK:
                    $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_seek' );
                    break;
                default:
                    $error = OW::getLanguage()->text( 'csvimporter', 'zip_error_unknow' );
                    break;
            }
        }
        
        $zip->close();
        
        return $error;
    }
    
    public function getMostUsedValuesByField( $field )
    {
        return $this->dumpDao->getMostUsedValuesByField( $field );
    }
    
    public function genMostUsedValuesForMultiselect()
    {
        return $this->dumpDao->genMostUsedValuesForMultiselect();
    }

    public function countProfilesForImport()
    {
        return $this->dumpDao->countProfilesForImport();
    }
    
    public function countImportedProfiles()
    {
        return $this->dumpDao->countImportedProfiles();
    }
    
    public function countProfilesForRollback()
    {
        return $this->dumpDao->countProfilesForRollback();
    }

    public function getDumpTableFields()
    {
        return $this->dumpDao->getDumpTableFields();
    }
    
    public function countLog()
    {
        return $this->dumpDao->countAll();
    }
    
    public function countFailImported()
    {
        return $this->dumpDao->countFailImported();
    }
}
