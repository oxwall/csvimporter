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
 * @package ow_plugins.csv_importer.forms
 * @since 1.0
 */
class CSVIMPORTER_FORM_ImportSettings extends Form
{
    public function __construct()
    {
        parent::__construct( 'import-settings' );
        
        $this->setAjax( FALSE );
        $this->setAction( OW::getRouter()->urlForRoute('csvimporter.admin') );

        $language = OW::getLanguage();
        
        $generatePassword = new CheckboxField( 'generate-password' );
        $generatePassword->setLabel( $language->text('csvimporter', 'generate_password_label') );
        $generatePassword->setDescription( $language->text('csvimporter', 'generate_password_desc') );
        $this->addElement( $generatePassword );
        
        $sendWelcomeMessage = new CheckboxField( 'send-welcome-message' );
        $sendWelcomeMessage->setLabel( $language->text('csvimporter', 'send_welcome_message_label') );
        $sendWelcomeMessage->setDescription( $language->text('csvimporter', 'send_welcome_message_desc') );
        $this->addElement( $sendWelcomeMessage );
        
        $questions = new Selectbox( 'questions[]' );
        
        $questionService = BOL_QuestionService::getInstance();
        $_questions = $questionService->findAllQuestions();

        $options = array();
        
        foreach ( $_questions as $question )
        {
            if ( in_array($question->name, array('joinStamp', 'googlemap_location')) )
            {
                continue;
            }
            
            $options[$question->name] = $questionService->getQuestionLang( $question->name );
            
            if ( in_array($question->name, array('username', 'email')) )
            {
                $options[$question->name] .= ' *';
            }
        }
        
        asort( $options );
        
        $questions->setOptions( $options );
        $questions->addValidator( new CSVIMPORTER_QuestionsValidator() );
        $this->addElement( $questions );
        
        foreach ( CSVIMPORTER_BOL_Service::getInstance()->getDumpTableFields() as $val )
        {
            $importFields = new TextField( 'import-fields[][' . $val['Field'] . ']' );
            $importFields->addAttribute( 'type', 'hidden' );
            $importFields->addAttribute( 'id', 'csvimporter-' . $val['Field'] );
            $this->addElement( $importFields );
        }
        
        $submit = new Submit( 'start-import' );
        $submit->setValue( $language->text('csvimporter', 'import_settings_submit_caption') );
        $this->addElement( $submit );
    }

    public function isValid( $data )
    {
        return !empty( $data['import-fields'] );
    }
}

class CSVIMPORTER_QuestionsValidator extends OW_Validator
{
    public function isValid( $value ) 
    {
        return parent::isValid( $value );
    }
    
    public function getJsValidator()
    {
        return '{
            validate : function( value )
            {
                var required = [];
                
                $(".question").each(function(i)
                {
                    if ( this.value == "username" || this.value == "email" )
                    {
                        required.push( this.value );
                    }
                });
                
                if ( required.length !== 2 )
                {
                    OW.error( OW.getLanguageText("csvimporter", "import_settings_required_error") );
                    
                    throw "Required";
                }
            }
        }';
    }
}
