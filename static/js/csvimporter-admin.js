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
 * @package ow_plugins.csv_importer
 * @since 1.0
 */
window.CSVIMPORTERADMIN = (function( $ )
{
    var constant = (function()
    {
        var constants = {}, ownProp = Object.prototype.hasOwnProperty, allowed = {string: 1}, prefix = ( Math.random() + '_' ).slice( 2 );
        
        return {
            set: function( name, value )
            {
                if ( this.isDefined(name) )
                {
                    return false;
                }
                
                if ( !ownProp.call(allowed, typeof value) )
                {
                    return false;
                }
                
                constants[prefix + name] = value;
                
                return  true;
            },
            
            isDefined: function( name )
            {
                return ownProp.call( constants, prefix + name );
            },
            
            get: function( name )
            {
                if ( this.isDefined(name) )
                {
                    return constants[prefix + name];
                }
                
                return null;
            }
        };
    })();
    
    constant.set( 'stepUpload', 'step_upload' );
    constant.set( 'stepPreview', 'step_preview' );
    constant.set( 'stepProcess', 'step_process' );
    constant.set( 'stepReady', 'step_ready' );
    constant.set( 'stepImport', 'step_import' );
    constant.set( 'stepRollback', 'step_rollback' );
    constant.set( 'stepDone', 'step_done' );
    
    var _elemtnts = {}, _methods = {};
    
    _methods.changeStep = function( forward )
    {
        var step;
        
        var forward = forward || false;
        
        switch ( constant.get('currentStep') )
        {
            case 'step_preview':
                step = forward ? constant.get( 'stepProcess' ) : constant.get( 'stepUpload' );
                break;
            case 'step_process':
                step = forward ? constant.get( 'stepReady' ) : constant.get( 'stepUpload' );
                break;
            case 'step_import':
                step = forward ? constant.get( 'stepDone' ) : constant.get( 'stepRollback' );
                break;
            case 'step_ready':
                step = constant.get( 'stepUpload' );
                break;
            case 'step_rollback':
                step = constant.get( 'stepReady' );
                break;
            case 'step_done':
                step = constant.get( 'stepUpload' );
                break;
        }
        
        window.location.search = 'step=' + step;
    };
    
    return {
        setStep: function( step )
        {
            constant.set( 'currentStep', step );
            
            switch ( step )
            {
                case 'step_upload':
                    document.getElementById( 'csv_file' ).addEventListener( 'change', function()
                    {
                        if ( this.value.match(/\.(csv|zip)$/) === null )
                        {
                            OW.error( OW.getLanguageText("csvimporter", "upload_type_error") );
                        }
                    });
                    break;
                case 'step_preview':
                    document.getElementById( 'btn-back' ).addEventListener( 'click', function()
                    {
                        _methods.changeStep();
                    });
                    document.getElementById( 'btn-forward-process' ).addEventListener( 'click', function()
                    {
                        _methods.changeStep( true );
                    });
                    break;
                case 'step_process':
                case 'step_import':
                case 'step_rollback':
                    if ( step !== 'step_rollback' )
                    {
                        document.getElementById( 'btn-back' ).addEventListener( 'click', function()
                        {
                            if ( confirm(OW.getLanguageText("csvimporter", "are_you_sure")) )
                            {
                                _methods.changeStep();
                            }
                        });
                    }
                    
                    _elemtnts.progressBar = document.getElementById( 'csvimporter-progressbar' );
                    _elemtnts.progressBarCaption = $( '.progressbar-caption', _elemtnts.progressBar );
                    _elemtnts.progressBarComplete = $( '.progressbar-complete', _elemtnts.progressBar );
                    _elemtnts.complete;
                    
                    _elemtnts.pingCommand = OW.getPing().addCommand('step_info',
                    {
                        after: function( data )
                        {
                            if ( data && $.isPlainObject(data) )
                            {
                                var complete = Math.round( (data.complete * 100) / data.total );
                                
                                if ( complete === _elemtnts.complete )
                                {
                                    return;
                                }
                                
                                _elemtnts.progressBarCaption.text( complete + '%' );
                                _elemtnts.progressBarComplete.animate({width: complete + '%'}, 
                                {
                                    duration: 'slow',
                                    specialEasing: {width: 'easeOutBounce'},
                                    queue: false
                                });
                                _elemtnts.complete = complete;
                                
                                if ( complete >= 100 )
                                {
                                    OW.info( OW.getLanguageText( "csvimporter", "successfully_message" ) );
                                    _methods.changeStep( true );
                                }
                            }
                        }
                    });
                    
                    _elemtnts.pingCommand.start();
                    _elemtnts.pingCommand.start( 5000 );
                    break;
                case 'step_ready':
                    document.getElementById( 'btn-back' ).addEventListener( 'click', function()
                    {
                        if ( confirm(OW.getLanguageText("csvimporter", "are_you_sure")) )
                        {
                            _methods.changeStep();
                        }
                    });
                    
                    var fields = {};
                    
                    $( '.question' ).bind( 'change', function()
                    {
                        var field = $( this ).attr( 'field' );

                        if ( !fields[field] )
                        {
                            fields[field] = document.getElementById( 'csvimporter-' + field );
                        }

                        fields[field].value = this.value;
                        fields[field].field = $( this ).attr( 'field' );
                        
                        var items = [];
                        
                        for ( var item in fields )
                        {
                            if ( item === field || !this.value )
                            {
                                continue;
                            }
                            
                            if ( fields[item].value === this.value )
                            {
                                items.push( fields[item].field );
                            }
                        }
                        
                        if ( items.length !== 0 )
                        {
                            OW.warning( OW.getLanguageText("csvimporter", "double_select", {questions: items.join(', ')}) );
                        }
                    });
                    break;
                case 'step_done':
                    document.getElementById( 'btn-forward' ).addEventListener( 'click', function()
                    {
                        _methods.changeStep();
                    });
                    break;
            }
        }
    };
})( jQuery );
