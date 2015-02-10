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
 * @package ow_plugins.csv_importer.classes
 * @since 1.0
 */
class CSVIMPORTER_CLASS_EnumStep extends CSVIMPORTER_CLASS_Enum
{
    CONST STEP_UPLOAD   = 'step_upload';   // Upload ".csv" file
    CONST STEP_PREVIEW  = 'step_preview';  // Preview data for import
    CONST STEP_PROCESS  = 'step_process';  // Import into dump table
    CONST STEP_READY    = 'step_ready';    // Ready to import. Set some options
    CONST STEP_IMPORT   = 'step_import';   // Import from dump table into user table
    CONST STEP_ROLLBACK = 'step_rollback'; // Cancel imports. Removing all imported users
    CONST STEP_DONE     = 'step_done';     // Import successfully completed. Showing logs
    
    public function changeStep( self $step, $redirect = true )
    {
        $this->currentStep = (string)$step;
        
        OW::getConfig()->saveConfig( 'csvimporter', 'step', (string)$step );
        
        if ( $redirect )
        {
            OW::getApplication()->redirect();
        }
    }
}
