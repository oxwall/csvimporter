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
class CSVIMPORTER_FORM_CsvUploader extends Form
{
    public function __construct()
    {
        parent::__construct( 'csvimport_form' );
        
        $this->setAjax( FALSE );
        $this->setEnctype( Form::ENCTYPE_MULTYPART_FORMDATA );
        $this->setAction( OW::getRouter()->urlForRoute('csvimporter.admin') );
        
        $file = new FileField( 'csv_file' );
        $file->addAttribute( 'id', 'csv_file' );
        $file->addValidator( new CsvFileValidator() );
        $file->setLabel( OW::getLanguage()->text('csvimporter', 'csv_file_label') );
        $this->addElement( $file );
        
        $submit = new Submit( 'send' );
        $submit->setValue( OW::getLanguage()->text('csvimporter', 'csvimporter_form_submit_caption') );
        $this->addElement( $submit );
    }
    
    public function isValid( $data = NULL )
    {
        return $this->getElement( 'csv_file' )->isValid();
    }
}

class CsvFileValidator extends OW_Validator
{
    public function isValid( $value = NULL )
    {
        if ( !empty($_FILES['csv_file']) && in_array($_FILES['csv_file']['type'], array('text/csv', 'application/zip', 'application/octet-stream')) && is_uploaded_file($_FILES['csv_file']['tmp_name']) )
        {
            return TRUE;
        }
        else
        {
            if ( !empty($_FILES['csv_file']['error']) )
            {
                switch ( $_FILES['csv_file']['error'] )
                {
                    case UPLOAD_ERR_INI_SIZE:
                        $this->setErrorMessage( OW::getLanguage()->text('csvimporter', 'upload_error_ini_size') ); 
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $this->setErrorMessage( OW::getLanguage()->text('csvimporter', 'upload_error_form_size') );
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $this->setErrorMessage( OW::getLanguage()->text('csvimporter', 'upload_error_partial') );
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $this->setErrorMessage( OW::getLanguage()->text('csvimporter', 'upload_error_no_file') );
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $this->setErrorMessage( OW::getLanguage()->text('csvimporter', 'upload_error_no_tmp_fir') );
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $this->setErrorMessage( OW::getLanguage()->text('csvimporter', 'upload_error_cant_write') );
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $this->setErrorMessage( OW::getLanguage()->text('csvimporter', 'upload_error_extension') );
                        break;
                }
            }
            else
            {
                $this->setErrorMessage( OW::getLanguage()->text('csvimporter', 'upload_error_unknow') );
            }
        }
    }
    
    public function getJsValidator()
    {
        return '{
            validate : function( value )
            {
                if ( !value.match("\.(csv|zip)$") )
                {
                    throw OW.getLanguageText( "csvimporter", "upload_type_error" );
                }
            }
        }';
    }
}
