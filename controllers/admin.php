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
 * @package ow_plugins.csv_importer.controllers
 * @since 1.0
 */
class CSVIMPORTER_CTRL_Admin extends ADMIN_CTRL_Abstract
{
    private $service;

    public function __construct()
    {
        parent::__construct();
        
        $this->service = CSVIMPORTER_BOL_Service::getInstance();
        
        OW::getDocument()->addScript( OW::getPluginManager()->getPlugin('csvimporter')->getStaticJsUrl() . 'csvimporter-admin.js' );        
    }
    
    public function init()
    {
        parent::init();
        
        if ( !empty($_GET['step']) )
        {
            switch ( $_GET['step'] )
            {
                case CSVIMPORTER_CLASS_EnumStep::STEP_UPLOAD:
                    switch ( $this->service->stepManager->getCurrentStep() )
                    {
                        case CSVIMPORTER_CLASS_EnumStep::STEP_PREVIEW:
                        case CSVIMPORTER_CLASS_EnumStep::STEP_PROCESS:
                        case CSVIMPORTER_CLASS_EnumStep::STEP_READY:
                        case CSVIMPORTER_CLASS_EnumStep::STEP_DONE:
                            UTIL_File::removeDir( OW::getPluginManager()->getPlugin('csvimporter')->getPluginFilesDir(), TRUE );
                            OW::getDbo()->query( 'DROP TABLE IF EXISTS `' . CSVIMPORTER_BOL_DumpDao::getInstance()->getTableName() . '`' );
                            OW::getConfig()->saveConfig( 'csvimporter', 'current_position', 0 );
                            
                            $this->service->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_UPLOAD), FALSE );
                            break;
                    }
                    break;
                case CSVIMPORTER_CLASS_EnumStep::STEP_PREVIEW:
                    break;
                case CSVIMPORTER_CLASS_EnumStep::STEP_PROCESS:
                    switch ( $this->service->stepManager->getCurrentStep() )
                    {
                        case CSVIMPORTER_CLASS_EnumStep::STEP_PREVIEW:
                            $data = $this->service->getPreviewData();
                            $fields = array();

                            for ( $i = 0; $i < count($data[0]); $i++ )
                            {
                                array_push( $fields, '`field' . $i . '` text' );
                            }

                            OW::getDbo()->query( '
                                DROP TABLE IF EXISTS `' . CSVIMPORTER_BOL_DumpDao::getInstance()->getTableName() . '`;
                                CREATE TABLE `' . CSVIMPORTER_BOL_DumpDao::getInstance()->getTableName() . '` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `userId` int(11) DEFAULT NULL,
                                    `errorMessage` varchar(50) DEFAULT NULL,' . implode( ',', $fields ) . ',
                                    PRIMARY KEY (`id`),
                                    KEY `userId` (`userId`),
                                    KEY `errorMessage` (`errorMessage`))
                                ENGINE=MyISAM DEFAULT CHARSET=utf8;' );

                            $this->service->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_PROCESS), FALSE );
                            break;
                    }
                    break;
                case CSVIMPORTER_CLASS_EnumStep::STEP_READY:
                    switch ( $this->service->stepManager->getCurrentStep() )
                    {
                        case CSVIMPORTER_CLASS_EnumStep::STEP_ROLLBACK:
                            $this->service->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_READY), FALSE );
                            break;
                    }
                    break;
                case CSVIMPORTER_CLASS_EnumStep::STEP_IMPORT:
                    switch ( $this->service->stepManager->getCurrentStep() )
                    {
                        case CSVIMPORTER_CLASS_EnumStep::STEP_READY:
                            $this->service->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_IMPORT), FALSE );
                            break;
                    }
                    break;
                case CSVIMPORTER_CLASS_EnumStep::STEP_ROLLBACK:
                    switch ( $this->service->stepManager->getCurrentStep() )
                    {
                        case CSVIMPORTER_CLASS_EnumStep::STEP_IMPORT:
                            $this->service->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_ROLLBACK), FALSE );
                            break;
                    }
                    break;
                case CSVIMPORTER_CLASS_EnumStep::STEP_DONE:
                    switch ( $this->service->stepManager->getCurrentStep() )
                    {
                        case CSVIMPORTER_CLASS_EnumStep::STEP_IMPORT:
                            $this->service->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_DONE), FALSE );
                            break;
                    }
                    break;
            }
        }
    }

    public function index( $params = NULL )
    {
        switch ( $this->service->stepManager->getCurrentStep() )
        {
            case CSVIMPORTER_CLASS_EnumStep::STEP_PROCESS:
            case CSVIMPORTER_CLASS_EnumStep::STEP_IMPORT:
            case CSVIMPORTER_CLASS_EnumStep::STEP_ROLLBACK:
                $this->runStepProcessing();
                break;
            case CSVIMPORTER_CLASS_EnumStep::STEP_UPLOAD:
                $this->runStepUpload();
                break;
            case CSVIMPORTER_CLASS_EnumStep::STEP_PREVIEW:
                $this->runStepPreview();
                break;
            case CSVIMPORTER_CLASS_EnumStep::STEP_READY:
                $this->runStepReady();
                break;
            case CSVIMPORTER_CLASS_EnumStep::STEP_DONE:
                $this->runStepDone();
                break;
        }
        
        $this->assign( 'step', $this->service->stepManager->getCurrentStep() );
        
        OW::getDocument()->addScriptDeclaration(
            UTIL_JsGenerator::composeJsString(
                ';window.CSVIMPORTERADMIN.setStep({$step});',
                    array('step' => $this->service->stepManager->getCurrentStep()))
        );
    }
    
    private function runStepUpload()
    {
        $plugin = OW::getPluginManager()->getPlugin( 'csvimporter' );
        $language = OW::getLanguage();
        $document = OW::getDocument();
        $csvUploaderForm = new CSVIMPORTER_FORM_CsvUploader();
        
        if ( OW::getRequest()->isPost() )
        {
            if ( $csvUploaderForm->isValid() )
            {
                UTIL_File::removeDir( $plugin->getPluginFilesDir(), TRUE );
                
                switch ( $_FILES['csv_file']['type'] )
                {
                    case 'application/zip':
                    case 'application/octet-stream':
                        $filename = $plugin->getPluginFilesDir() . CSVIMPORTER_BOL_Service::ZIP_NAME;
                        $isZip = TRUE;
                        break;
                    case 'text/csv':
                    default:
                        $filename = $this->service->getFilename();
                        $isZip = FALSE;
                        break;
                }
                
                if ( move_uploaded_file($_FILES['csv_file']['tmp_name'], $filename) && ($isZip ? (($error = $this->service->extractZipFile()) === TRUE) : TRUE) )
                {
                    if ( $this->service->parseAttempt($this->service->getFilename()) )
                    {
                        $data = $this->service->getPreviewData();
                        $fields = array();

                        for ( $i = 0; $i < count($data[0]); $i++ )
                        {
                            array_push( $fields, '`field' . $i . '` text' );
                        }

                        OW::getDbo()->query( '
                            DROP TABLE IF EXISTS `' . CSVIMPORTER_BOL_DumpDao::getInstance()->getTableName() . '`;
                            CREATE TABLE `' . CSVIMPORTER_BOL_DumpDao::getInstance()->getTableName() . '` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `userId` int(11) DEFAULT NULL,
                                `errorMessage` varchar(50) DEFAULT NULL,' . implode( ',', $fields ) . ',
                                PRIMARY KEY (`id`),
                                KEY `userId` (`userId`),
                                KEY `errorMessage` (`errorMessage`))
                            ENGINE=MyISAM DEFAULT CHARSET=utf8;' );
                        
                        OW::getFeedback()->info( $language->text('csvimporter', 'parse_success_message') );
                        $this->service->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_PROCESS) );
                    }
                    else
                    {
                        OW::getFeedback()->error( $language->text('csvimporter', 'parse_error_message') );
                    }
                }
                else
                {
                    if ( $isZip && $error )
                    {
                        OW::getFeedback()->error( $error );
                    }
                    else
                    {
                        OW::getFeedback()->error( $language->text('csvimporter', 'parse_error_message') );
                    }
                }
            }
            else
            {
                $errors = $csvUploaderForm->getErrors();
                OW::getFeedback()->error( $errors['csv_file'][0] );
            }
        }

        $this->addForm( $csvUploaderForm );
        $document->setTitle( $language->text('csvimporter', 'step_upload_page_title') );
        $language->addKeyForJs( 'csvimporter', 'upload_type_error' );
    }

    private function runStepPreview()
    {
        OW::getDocument()->setTitle( OW::getLanguage()->text('csvimporter', 'step_preview_page_title') );
        
        $config = OW::getConfig();
                
        $this->assign( 'defineDelimiter', $config->getValue('csvimporter', 'define_delimiter') );
        $this->assign( 'defineEnclosure', $config->getValue('csvimporter', 'define_enclosure') );
        $this->assign( 'defineEscape', $config->getValue('csvimporter', 'define_escape') );

        $previewData = $this->service->getPreviewData();

        foreach ( $previewData as &$data )
        {
            $data = array_map( 'htmlentities', $data );
        }

        $this->assign( 'previewData', $previewData );
    }
    
    private function runStepProcessing()
    {
        $document = OW::getDocument();
        $language = OW::getLanguage();
        
        $document->addStyleSheet( OW::getPluginManager()->getPlugin('csvimporter')->getStaticCssUrl() . 'csvimporter.css' );
        $document->addScript( OW::getPluginManager()->getPlugin('csvimporter')->getStaticJsUrl() . 'jquery-ui-1.10.2.custom.min.js' );
                
        $language->addKeyForJs( 'csvimporter', 'are_you_sure' );
        $language->addKeyForJs( 'csvimporter', 'successfully_message' );;
        
        switch ( $this->service->stepManager->getCurrentStep() )
        {
            case CSVIMPORTER_CLASS_EnumStep::STEP_PROCESS:
                $document->setTitle( $language->text('csvimporter', 'step_process_page_title') );
                break;
            case CSVIMPORTER_CLASS_EnumStep::STEP_IMPORT:
                $document->setTitle( $language->text('csvimporter', 'step_import_page_title') );
                break;
            case CSVIMPORTER_CLASS_EnumStep::STEP_ROLLBACK:
                $document->setTitle( $language->text('csvimporter', 'step_rollback_page_title') );
                break;
        }
    }

    private function runStepReady()
    {
        $previewData = $this->service->getPreviewData();
                
        foreach ( $previewData as &$data )
        {
            $data = array_map( 'htmlentities', $data );
        }

        $this->assign( 'previewData', $previewData );
        $this->assign( 'totalProfiles', $this->service->countProfilesForImport() );
        $this->assign( 'dumpTableFields', $this->service->getDumpTableFields() );

        $importSettingsForm = new CSVIMPORTER_FORM_ImportSettings();

        if ( OW::getRequest()->isPost() )
        {
            if ( $importSettingsForm->isValid($_POST) )
            {
                $questionsToFields = $this->getQuestionsToFields();
                $questions = BOL_QuestionService::getInstance()->findQuestionByNameList( array_keys($questionsToFields) );                
                $selectQuestions = array();
                $multiselectQuestions = array();
                $datatimeFields = array();
                
                foreach ( $questions as $question )
                {
                    switch ( $question->presentation )
                    {
                        case BOL_QuestionService::QUESTION_PRESENTATION_MULTICHECKBOX:
                            $multiselectQuestions[$question->id] = $questionsToFields[$question->name];;
                            break;
                        case BOL_QuestionService::QUESTION_PRESENTATION_RADIO:
                        case BOL_QuestionService::QUESTION_PRESENTATION_SELECT:
                            $selectQuestions[$question->id] = $questionsToFields[$question->name];
                            break;
                        case BOL_QuestionService::QUESTION_PRESENTATION_AGE:
                        case BOL_QuestionService::QUESTION_PRESENTATION_DATE:
                        case BOL_QuestionService::QUESTION_PRESENTATION_BIRTHDATE:
                            $datatimeFields[$question->name] = $this->getDatetimeFormat( $questionsToFields[$question->name] );
                            break;
                    }
                }
                
                $this->generateValuesForSelectQuestions( $selectQuestions );
                $this->generateValuesForMultiselectQuestions( $multiselectQuestions );
                
                OW::getConfig()->saveConfig( 'csvimporter', 'import_configs', 
                    json_encode(array(
                        'fields' => $questionsToFields,
                        'options' => array(
                            'generatePassword' => !empty( $_POST['generate-password'] ), 
                            'sendWelcomeMessage' => !empty( $_POST['send-welcome-message'] )),
                        'dateFormat' => $datatimeFields
                        )
                    )
                );
                
                $this->service->stepManager->changeStep( new CSVIMPORTER_CLASS_EnumStep(CSVIMPORTER_CLASS_EnumStep::STEP_IMPORT) );
            }
            else
            {
                OW::getFeedback()->error( OW::getLanguage()->text('csvimporter', 'import_settings_error_message') );
            }
        }

        $this->addForm( $importSettingsForm );
        
        OW::getLanguage()->addKeyForJs( 'csvimporter', 'are_you_sure' );
        OW::getLanguage()->addKeyForJs( 'csvimporter', 'double_select' );
        OW::getLanguage()->addKeyForJs( 'csvimporter', 'import_settings_required_error' );
    }
    
    private function runStepDone()
    {
        $this->assign( 'countSuccessImported', $this->service->countImportedProfiles() );
        $this->assign( 'countFailImported', $this->service->countFailImported() );
    }

    private function getQuestionsToFields()
    {
        $result = array();
        
        foreach ( $_POST['import-fields'] as $value )
        {
            $key = $value[key($value)];
            $val = key( $value );
            
            if ( !empty($key) && !empty($val) )
            {
                $result[$key] = $val;
            }
        }
        
        return $result;
    }
    
    private function generateValuesForSelectQuestions( array $questions )
    {
        if ( count($questions) === 0 )
        {
            return FALSE;
        }
        
        $this->deleteQuestionValues( array_keys($questions) ); 
        
        $questionService = BOL_QuestionService::getInstance();
        $lanuageService = BOL_LanguageService::getInstance();
        $currentLanguageId = $lanuageService->getCurrent()->getId();
        
        foreach ( $questions as $key => $question )
        {
            $questionDto = $questionService->findQuestionById( $key );
            
            if ( !in_array($questionDto->presentation, array(
                BOL_QuestionService::QUESTION_PRESENTATION_RADIO,
                BOL_QuestionService::QUESTION_PRESENTATION_SELECT)) )
            {
                continue;
            }
            
            $questionValues = $this->service->getMostUsedValuesByField( $question );
            $_key = 0;
            
            foreach ( $questionValues as $value )
            {
                if ( $_key > 30 )
                {
                    break;
                }

                $value = trim( $value['label'] );
                
                if ( strlen($value) === 0 )
                {
                    continue;
                }

                $valueId = pow( 2, $_key );

                $questionValue = new BOL_QuestionValue();
                $questionValue->questionName = $questionDto->name;
                $questionValue->sortOrder = $_key;
                $questionValue->value = $valueId;

                $questionService->saveOrUpdateQuestionValue( $questionValue );

                $lanuageService->addValue( $currentLanguageId, 'base', 'questions_question_' . ($questionDto->name) . '_value_' . $valueId, $value, $_key === count($questionValues) - 1 );
                $_key++;
            }
        }
    }
    
    private function generateValuesForMultiselectQuestions( array $questions )
    {
        if ( count($questions) === 0 )
        {
            return FALSE;
        }
        
        $this->deleteQuestionValues( array_keys($questions) );
        
        $questionService = BOL_QuestionService::getInstance();
        $lanuageService = BOL_LanguageService::getInstance();
        $currentLanguageId = $lanuageService->getCurrent()->getId();
        
        foreach ( $questions as $key => $question )
        {
            $questionDto = $questionService->findQuestionById( $key );
            
            if ( !in_array($questionDto->presentation, array(BOL_QuestionService::QUESTION_PRESENTATION_MULTICHECKBOX)) )
            {
                continue;
            }
            
            OW::getDbo()->query( 'CALL `' . OW_DB_PREFIX . 'csvimporter_sp_get_values`("' . $question . '")' );
            OW::getDbo()->query( 'CALL `' . OW_DB_PREFIX . 'csvimporter_sp_create_values`()' );
            
            $questionValues = $this->service->genMostUsedValuesForMultiselect();
            $_key = 0;
            
            foreach ( $questionValues as $value )
            {
                if ( $_key > 30 )
                {
                    break;
                }

                $value = trim( $value['label'] );
                
                if ( strlen($value) === 0 )
                {
                    continue;
                }

                $valueId = pow( 2, $_key );

                $questionValue = new BOL_QuestionValue();
                $questionValue->questionName = $questionDto->name;
                $questionValue->sortOrder = $_key;
                $questionValue->value = $valueId;

                $questionService->saveOrUpdateQuestionValue( $questionValue );

                $lanuageService->addValue( $currentLanguageId, 'base', 'questions_question_' . ($questionDto->name) . '_value_' . $valueId, $value, $_key === count($questionValues) - 1 );
                $_key++;
            }
        }
    }

    private function deleteQuestionValues( array $questions )
    {
        if (count($questions) === 0 )
        {
            return FALSE;
        }
        
        $questionArray = BOL_QuestionDao::getInstance()->findByIdList( $questions );

        $questionsNameList = array();
        $questionService = BOL_QuestionService::getInstance();

        foreach ( $questionArray as $question )
        {
            $questionsNameList[] = $question->name;

            $values = $questionService->findRealQuestionValues( $question->name );

            foreach ( $values as $value )
            {
                $questionService->deleteQuestionValue( $question->name, $value->value );
            }
        }

        BOL_QuestionDataDao::getInstance()->deleteByQuestionNamesList( $questionsNameList );
    }
    
    private function getDatetimeFormat( $field )
    {
        if ( empty($field) )
        {
            return;
        }
        
        $values = $this->service->getFieldDate( $field );
        
        foreach ( $values as $value )
        {
            if ( empty($value) || $value == 'NULL' )
            {
                continue;
            }

            preg_match_all( '/(\d{1,4})|(\D)/', trim($value), $date );

            if ( ($count = count($date[0])) >= 5 )
            {
                $year = current( array_filter($date[1], 'self::getYear') );

                if ( !empty($year) )
                {
                    $dayMonth = array_values( array_diff(array_filter($date[1], 'trim' ), (array)$year) );

                    if ( $dayMonth[0] > 12 )
                    {
                        $day = $dayMonth[0];
                        $month = $dayMonth[1];
                    }
                    elseif ( $dayMonth[1] > 12 )
                    {
                        $day = $dayMonth[1];
                        $month = $dayMonth[0];
                    }

                    if ( !empty($day) && !empty($month) )
                    {
                        $result = array(
                            array_search($year, $date[0]) => 'Y',
                            array_search($month, $date[0]) => 'm',
                            array_search($day, $date[0]) => 'd'
                        );

                        ksort( $result );

                        return implode( $date[0][1], $result ) . ( $count > 5 ? ' H:i:s' : '' );
                    }
                }
            }
        }
        
        return FALSE;
    }
    
    private static function getYear( $value )
    {
        return (int)$value > 31;
    }
}
