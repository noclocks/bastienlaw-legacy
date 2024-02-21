(function ($) {
	$(function(){	                
            PrimeMoverControlPanel.deleteAllBackups();
            PrimeMoverControlPanel.computeBackupDirSize(); 
            PrimeMoverControlPanel.showHideAuthorizationKeys();  
            PrimeMoverControlPanel.showHideGDriveCredentials(); 
            PrimeMoverControlPanel.toggleDropBoxToken();
            PrimeMoverControlPanel.toggleEncryptionKey();
            PrimeMoverControlPanel.toggleMySQLDumpConfigPath();
            PrimeMoverControlPanel.clearLogs();     
            PrimeMoverControlPanel.initializeAjaxHandlers();
            PrimeMoverControlPanel.resetToDefaults(); 
            PrimeMoverControlPanel.generateAuthorizationKey();   
            PrimeMoverControlPanel.copyKeyToClipBoard();
            PrimeMoverControlPanel.copyEncryptionKeyToClipBoard();
            PrimeMoverControlPanel.slideTogglerActivatedPlugins();
    });
    var PrimeMoverControlPanel = {
     	/**
    	 * Initialize data related to panel settings
    	 */        
         initializeData: [
        	     {
	               "button_selector" : "#js-save-prime_mover_dbdump_batchsize",
	               "spinner_selector" : ".js-save-prime_mover_dbdump_batchsize-spinner",
	               "data_selector" : "#js-prime_mover_dbdump_batchsize",
	               "ajax_action" : "prime_mover_dbdump_batchsize",
	               "ajax_key" : "prime_mover_dbdump_batchsize_key",
	               "datatype" : "text",
	               "dialog" : false         	    	 
        	     },
        	     {                    
                   "button_selector" : "#js-save-prime_mover_searchreplace_batchsize",
                   "spinner_selector" : ".js-save-prime_mover_searchreplace_batchsize-spinner",
                   "data_selector" : "#js-prime_mover_searchreplace_batchsize",
                   "ajax_action" : "prime_mover_searchreplace_batchsize",
                   "ajax_key" : "prime_mover_searchreplace_batchsize_key",
                   "datatype" : "text",
                   "dialog" : false                      
                 },                 
                 { 
                   "button_selector" : "#js-save-prime-mover-upload-chunk-size", 
                   "spinner_selector" : ".js-save-prime-mover-upload-chunk-size-spinner",
                   "data_selector" : "#js-prime_mover_upload_chunksize",
                   "ajax_action" : "prime_mover_save_uploadchunksize_settings",
                   "ajax_key" : "uploadchunksize",
                   "datatype" : "text",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-enable-js-uploadlog', 
                   "spinner_selector" : '.js-save-prime-mover-enable-js-uploadlog-spinner',
                   "data_selector" : '#js-prime_mover_enable_js_uploadlog_checkbox',
                   "ajax_action" : 'prime_mover_save_uploadjs_troubleshooting_setting',
                   "ajax_key" : prime_mover_control_panel_renderer.enable_uploadjs_troubleshooting,
                   "datatype" : "checkbox", 
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-enable-turbomode', 
                   "spinner_selector" : '.js-save-prime-mover-enable-turbomode-spinner',
                   "data_selector" : '#js-prime_mover_enable_js_turbomode_checkbox',
                   "ajax_action" : 'prime_mover_save_turbomode_setting',
                   "ajax_key" : prime_mover_control_panel_renderer.enable_turbo_mode,
                   "datatype" : "checkbox", 
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-enable-js-log', 
                   "spinner_selector" : '.js-save-prime-mover-enable-js-log-spinner',
                   "data_selector" : '#js-prime_mover_enable_js_log_checkbox',
                   "ajax_action" : 'prime_mover_save_js_troubleshooting_settings',
                   "ajax_key" : prime_mover_control_panel_renderer.enable_js_troubleshooting,
                   "datatype" : "checkbox",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-persist-troubleshooting', 
                   "spinner_selector" : '.js-save-prime-mover-persist-troubleshooting-spinner',
                   "data_selector" : '#js-prime_mover_persist_log_checkbox',
                   "ajax_action" : 'prime_mover_save_persist_troubleshooting_settings',
                   "ajax_key" : prime_mover_control_panel_renderer.enable_persist_troubleshooting,
                   "datatype" : "checkbox",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-troubleshooting', 
                   "spinner_selector" : '.js-save-prime-mover-troubleshooting-spinner',
                   "data_selector" : '#js-prime_mover_enable_log_checkbox',
                   "ajax_action" : 'prime_mover_save_troubleshooting_settings',
                   "ajax_key" : prime_mover_control_panel_renderer.enable_troubleshooting,
                   "datatype" : "checkbox",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-dropbox-access-token', 
                   "spinner_selector" : '.js-save-prime-mover-dropbox-access-token-spinner',
                   "data_selector" : '#js-prime_mover_dropbox_access_key',
                   "ajax_action" : 'prime_mover_save_dropbox_settings',
                   "ajax_key" : 'dropbox_access_token',
                   "datatype" : "text",
                   "dialog" : false
                 },                 
                 { 
                    "button_selector" : '#js-save-prime-mover-encryption-key', 
                    "spinner_selector" : '.js-save-prime-mover-encryption-key-spinner',
                    "data_selector" : '#js-prime_mover_encryption_key_panel',
                    "ajax_action" : 'prime_mover_write_key_to_config',
                    "ajax_key" : 'prime_mover_encryption_ajax_key',
                    "datatype" : "text",
                    "dialog" : true,
                    "dialog_selector" : "#js-prime-mover-panel-enc-warn-dialog",
                    "dialog_button_text" : prime_mover_control_panel_renderer.prime_mover_update_enc_key_button
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-excluded-plugins', 
                   "spinner_selector" : '.js-save-prime-mover-excluded-plugins-spinner',
                   "data_selector" : '#js-prime-mover-excluded-plugins',
                   "ajax_action" : 'prime_mover_excluded_plugins',
                   "ajax_key" : 'text_area_data',
                   "datatype" : "text",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-excluded-uploads', 
                   "spinner_selector" : '.js-save-prime-mover-excluded-uploads-spinner',
                   "data_selector" : '#js-prime-mover-excluded-uploads',
                   "ajax_action" : 'prime_mover_excluded_uploads',
                   "ajax_key" : 'text_area_data',
                   "datatype" : "text",
                   "dialog" : false
                   },                 
                 { 
                   "button_selector" : '#js-save-prime-mover-custom-basebackup-dir', 
                   "spinner_selector" : '.js-prime_mover_basedirsettings_spinner' ,
                   "data_selector" : '#js-prime_mover_user_backup_dir_setting',
                   "ajax_action" : 'prime_mover_save_custom_baseabackup_dir' ,
                   "ajax_key" : 'custom_path',
                   "datatype" : "text",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-maintenance-mode', 
                   "spinner_selector" : '.js-prime_mover_maintenance-mode-spinner',
                   "data_selector" : '#js-prime_mover_enable_maintenance_mode',
                   "ajax_action" : 'prime_mover_save_maintenance_mode_setting',
                   "ajax_key" : 'turn_off_maintenance',
                   "datatype" : "checkbox",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-download-authentication', 
                   "spinner_selector" : '.js-prime_mover_download_authentication-spinner',
                   "data_selector" : '#js-prime-mover-authorized-domains',
                   "ajax_action" : 'prime_mover_save_download_authentication',
                   "ajax_key" : 'text_area_data',
                   "datatype" : "text",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-gdrive-setting', 
                   "spinner_selector" : '.js-prime_mover_gdrive_setting-spinner',
                   "data_selector" : '#js-prime-mover-gdrive-settings',
                   "ajax_action" : 'prime_mover_save_gdrive_setting',
                   "ajax_key" : 'text_area_data',
                   "datatype" : "text",
                   "dialog" : false
                 },                 
                 { 
                   "button_selector" : "#js-save-prime-mover-refreshinterval-size", 
                   "spinner_selector" : ".js-save-prime-mover-upload-refreshinterval-spinner",
                   "data_selector" : "#js-prime_mover_upload_refreshinterval",
                   "ajax_action" : "prime_mover_save_upload_refresh_interval",
                   "ajax_key" : "refresh_interval_setting",
                   "datatype" : "text",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : '#js-save-prime-mover-upload-retrylimit', 
                   "spinner_selector" : '.js-save-prime-mover-upload-retrylimit-spinner',
                   "data_selector" : '#js-prime_mover_upload_retrylimit',
                   "ajax_action" : 'prime_mover_save_upload_retrylimit',
                   "ajax_key" : 'upload_retry_limit',
                   "datatype" : "text",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : "#js-save-prime-mover-dropbox-chunk-size", 
                   "spinner_selector" : ".js-save-prime-mover-dropbox-chunk-size-spinner",
                   "data_selector" : "#js-prime_mover_dropbox_chunksize",
                   "ajax_action" : "prime_mover_save_dropbox_chunksize_setting",
                   "ajax_key" : "dropbox_chunk_upload_size",
                   "datatype" : "text",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : "#js-save-prime-mover-gdrive-chunk-size", 
                   "spinner_selector" : ".js-save-prime-mover-gdrive-chunk-size-spinner",
                   "data_selector" : "#js-prime_mover_gdrive_chunksize",
                   "ajax_action" : "prime_mover_save_gdrive_chunksize_setting",
                   "ajax_key" : "gdrive_chunk_upload_size",
                   "datatype" : "text",
                   "dialog" : false
                 },
                 { 
                   "button_selector" : "#js-save-prime-mover-gdrivedownload-chunk-size", 
                   "spinner_selector" : ".js-save-prime-mover-gdrivedownload-chunk-size-spinner",
                   "data_selector" : "#js-prime_mover_gdrivedownload_chunksize",
                   "ajax_action" : "prime_mover_save_gdrivedownload_chunksize_setting",
                   "ajax_key" : "gdrive_chunk_download_size",
                   "datatype" : "text",
                   "dialog" : false
                 },                 
                 { 
                   "button_selector" : "#js-save-prime-mover-mysqldump-cnf-setting", 
                   "spinner_selector" : ".js-save-prime-mover-mysqldump-cnf-setting-spinner",
                   "data_selector" : "#js-prime_mover_mysqldump_cnf_setting",
                   "ajax_action" : "prime_mover_save_mysqldump_settings",
                   "ajax_key" : "mysqldump_cnf_path",
                   "datatype" : "text",
                   "dialog" : false
                 }
         ],
     	/**
    	 * Initialize Ajax handler
    	 */
         initializeAjaxHandlers: function() {
              $.each(this.initializeData, function (i, activeSelectors) {                  
		          $('body').on('click', activeSelectors.button_selector, function(){ 
	                  var spinner_selector = activeSelectors.spinner_selector;
	                    if ('text' === activeSelectors.datatype) {
	                        var value = $(activeSelectors.data_selector).val();  
	                    }
	                    
	                    if ('checkbox' === activeSelectors.datatype) {
		                    var value = false;
		                    if ($(activeSelectors.data_selector).is(":checked")) {
		                        value = true;
		                    }
	                    }
	                   
	                    var button_nonce = $(this).attr('data-nonce');	                    
	                    if (true === activeSelectors.dialog) {	                    	                    	
	                    	var dialog_selector = activeSelectors.dialog_selector;
	                    	PrimeMoverControlPanel.showDialogHandler( dialog_selector, button_nonce, activeSelectors.dialog_button_text, 'prime-mover-deleteall-button', spinner_selector, activeSelectors.ajax_action,
	                 	            activeSelectors.ajax_key, value, prime_mover_control_panel_renderer.prime_mover_cancel_button, activeSelectors.data_selector);                     	
	                    } else {	                    	
	                    	if (PrimeMoverControlPanel.isDoingAjax(spinner_selector)) {
		                        return;
		                    } 
		
		                    PrimeMoverControlPanel.triggerProcessing(spinner_selector);		                    
	                    	var data = PrimeMoverControlPanel.defineGenericData(activeSelectors.ajax_action, button_nonce);
		                    
	                    	data[activeSelectors.ajax_key] = value;
		                    PrimeMoverControlPanel.doAjaxRequest(data, spinner_selector, activeSelectors.data_selector);	                    	
	                    }	                    
		          });                  
              }); 
         },
     	/**
     	 * Show a dialog
     	 * This should be reusable by any settings IF necessary
     	 */
         showDialogHandler: function(dialog_selector, button_nonce, button_text, button_class, button_spinner, ajax_action, data_key, data_value, button_cancel_text, data_selector) {
        	 if (typeof(data_selector) === 'undefined') {
                 var data_selector = '';
             }
             $(dialog_selector).dialog({
		        resizable: false,
		        height: "auto",          
		        minWidth: 320,
		        maxWidth: 600,
		        dialogClass: 'prime-mover-user-dialog',
		        modal: true,
		        fluid: true,
			    buttons: [
				  {
					  text: button_text,
					  "class": button_class,
					  click: function() {				    	  
					      $( this ).dialog( "close" );
			                  var spinner_selector = button_spinner;
			                  PrimeMoverControlPanel.triggerProcessing(spinner_selector);                     	        
			
			                  var data = PrimeMoverControlPanel.defineGenericData(ajax_action, button_nonce);
			                  data[data_key] = data_value;
			                  
			                  PrimeMoverControlPanel.doAjaxRequest(data, spinner_selector, data_selector);				    	  
					  }
			      },
			      {
				      text: button_cancel_text,					 
				      click: function() {							 
			            $( this ).dialog( "close" );						 
				      }
			      },				    
			    ],
 	        });
            PrimeMoverControlPanel.handle_responsive_dialog();
         }, 
         /**
     	 * AJAX request helper
     	 */
 	    doAjaxRequest: function(data, spinner_selector, data_selector) {
             if (typeof(data_selector) === 'undefined') {
             	var data_selector = '';
             }
             PrimeMoverControlPanel.doing_ajax[spinner_selector] = true;
 	         $.post(ajaxurl, data, function( response ) {                 
 		         $(spinner_selector).html(response.message);
                 if ( 'saved_settings' in response && data_selector) {
                      var saved_settings = response.saved_settings;  
                      PrimeMoverControlPanel.executeOtherAfterSavedHooks(data_selector, saved_settings);
                      $(data_selector).val(saved_settings);
                 }
 		        if ( response.save_status) {                     
                      $(spinner_selector).addClass('notice notice-success');		   
 		        } else if (saved_settings)  {                     
                      $(spinner_selector).addClass('notice notice-warning');
                } else {                                        
                      $(spinner_selector).addClass('notice notice-error');
                }
                PrimeMoverControlPanel.doing_ajax[spinner_selector] = false;                   
                if ('reload' in response && true === response.reload) {
  				    location.reload();
  				}                 
 	       }).fail(function(xhr, status, error) {	    	       
 	    	    var error_text = prime_mover_control_panel_renderer.prime_mover_panel_error;
 	    	    if (error && status) {
 	    	      var status = status.toUpperCase();
 	    	      error_text = status + ': ' + error;  
 	    	    }	    	       
                $(spinner_selector).html(error_text);
                $(spinner_selector).addClass('notice notice-error');
                PrimeMoverControlPanel.doing_ajax[spinner_selector] = false; 	           
 	       });		
 	     },        
    	/**
    	 * Doing ajax property
    	 */
         doing_ajax: [],
    	/**
    	 * Check if an element spinner is doing ajax
    	 */
         isDoingAjax: function(spinner_selector) {
             if (spinner_selector in PrimeMoverControlPanel.doing_ajax) {
                return PrimeMoverControlPanel.doing_ajax[spinner_selector];
             } else {
                return false;
             }                                          
         },
    	/**
    	 * Toggle dropbox token
    	 */
	toggleDropBoxToken: function() {        		   	
            var dropboxInputType = '#js-prime_mover_dropbox_access_key';
            var dropboxCheckBox = '#js-prime_mover_dropbox_token_checkbox'; 	
	    $('body').on('click',dropboxCheckBox, function(){ 
                var concealclass = 'conceal-authorization-keys';
                if ($(dropboxInputType).hasClass(concealclass)) {                   
                   $(dropboxInputType).removeClass(concealclass);
                } else {
                   $(dropboxInputType).addClass(concealclass);
                }        		 	  
	    });	    
	},
	/**
	 * Toggle dropbox token
	 */
    toggleEncryptionKey: function() {        		   	
        var dropboxInputType = '#js-prime_mover_encryption_key_panel';
        var dropboxCheckBox = '#js-prime_mover_encryption_key_panel_checkbox'; 	
        $('body').on('click',dropboxCheckBox, function(){ 
            var concealclass = 'conceal-authorization-keys';
            if ($(dropboxInputType).hasClass(concealclass)) {                   
               $(dropboxInputType).removeClass(concealclass);
            } else {
               $(dropboxInputType).addClass(concealclass);
            }        		 	  
       });	    
    },
    	/**
    	 * Toggle MySQLdump config path
    	 */
	toggleMySQLDumpConfigPath: function() {        		   	
            var configInputType = '#js-prime_mover_mysqldump_cnf_setting';
            var configCheckBox = '#js-prime_mover_mysqldump_cnf_checkbox'; 	
	    $('body').on('click',configCheckBox, function(){ 
                var concealclass = 'conceal-authorization-keys';
                if ($(configInputType).hasClass(concealclass)) {                   
                   $(configInputType).removeClass(concealclass);
                } else {
                   $(configInputType).addClass(concealclass);
                }        		 	  
	    });	    
	},
    	/**
    	 * Hide and show authorization keys
    	 */
         showHideAuthorizationKeys: function() {
            var authorization_checkbox = '#js-prime_mover_edit_authorization_keys';
            var authorized_domains_textarea = '#js-prime-mover-authorized-domains';
            var concealclass = 'conceal-authorization-keys';
            var span_text = '#js-show-hide-authorization-text';
     	    $('body').on('click',authorization_checkbox, function(){                
                if ($(authorized_domains_textarea).hasClass(concealclass)) {                   
                   $(authorized_domains_textarea).removeClass(concealclass);
                } else {
                   $(authorized_domains_textarea).addClass(concealclass);
                }
	    });
         },
         /**
      	 * Hide and show Gdrive credentials
      	 */
          showHideGDriveCredentials: function() {
              var gdrive_checkbox = '#js-prime_mover_edit_gdrive';
              var gdrive_settings_textarea = '#js-prime-mover-gdrive-settings';
              var concealclass = 'conceal-authorization-keys';
              var span_text = '#js-show-hide-gdrive-text';
       	      $('body').on('click',gdrive_checkbox, function(){                
                  if ($(gdrive_settings_textarea).hasClass(concealclass)) {                   
                     $(gdrive_settings_textarea).removeClass(concealclass);
                  } else {
                     $(gdrive_settings_textarea).addClass(concealclass);
                  }
  	          });
           },        
    	 /**
    	  * Generate authorization key for this site
          * This method fires when there is a request to autogenerate authorization key
    	  */
         generateAuthorizationKey: function() {
            var generatebutton = '#js-prime-mover-autogenerate-key';
     	    $('body').on('click',generatebutton, function(){  

                var hostdomain = prime_mover_control_panel_renderer.prime_mover_host_domain;
                var randomstring = PrimeMoverControlPanel.randomString();
                var authorization_key = hostdomain + ':' + randomstring;
                var clipboard_el = '.js-prime-mover-copy-key'; 

                var authorized_domains_textarea = '#js-prime-mover-authorized-domains';
                var current_value = $.trim($(authorized_domains_textarea).val());
                var cleaned = current_value.replace(/ /g,'');
                
                var arrayOfLines = cleaned.split('\n');
                var filtered = arrayOfLines.filter(function (el) {
                    if (0 === el.length || el === null) {
                        return false;
                    }
                    return true;
                });
                var arrayLength = filtered.length;
                var domains = [];
                for (var i = 0; i < arrayLength; i++) {
                    var input_data = filtered[i];
                    if ( input_data.indexOf(":") !== -1 ) {
                        var myarr = input_data.split(":");
                        var domain_data = myarr[0];
                        domains.push(domain_data);
                        if (domain_data === hostdomain) {
                            PrimeMoverControlPanel.authorizationKeyClipBoardHandler(clipboard_el, authorization_key);
                            filtered[i] = authorization_key;
                        }                        
                    }
                } 
                if( $.inArray(hostdomain, domains) === -1 ){
                    PrimeMoverControlPanel.authorizationKeyClipBoardHandler(clipboard_el, authorization_key);
                    filtered.push(authorization_key);
                }                
                var processed_value = filtered.join("\n");
                $(authorized_domains_textarea).val(processed_value);
                
	    });
         },
    	/**
    	 * Clipboard handler
    	 */
        authorizationKeyClipBoardHandler: function(clipboard_el, authorization_key) {
            var saved_value = $(clipboard_el).attr('data-saved-value');
            $(clipboard_el).attr('data-clipboard-text',authorization_key);
            if (saved_value !== authorization_key) {
                $('.js-prime-mover-copy-key').hide();
            }            
        },
    	/**
    	 * Copy key to clipboard
    	 */
    	copyKeyToClipBoard: function() {
    		this.clipBoardHelper('.js-prime-mover-copy-key', '#js-prime-mover-clipboard-key-confirmation');
    	},
    	/**
    	 * Copy key to clipboard
    	 */
    	copyEncryptionKeyToClipBoard: function() {
    		this.clipBoardHelper('.js-prime-mover-copy-encryption-key', '#js-prime-mover-clipboard-encryption-key-confirmation');
    	},
    	/**
    	 * Clipboard helper
    	 */
    	clipBoardHelper: function(clipboardjs_params_selector, onsuccess_selector) {
           var clipboard = new ClipboardJS(clipboardjs_params_selector);    		
    		
    		clipboard.on('success', function(e) {                   
                    $(onsuccess_selector).fadeIn('fast').delay(3000).fadeOut();
    		});    		
    	},
    	/**
    	 * Generate random string for authorization key
         * Reference: https://stackoverflow.com/questions/10726909/random-alpha-numeric-string-in-javascript
    	 */
         randomString: function () {
             var length = 64;
             var chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
             var result = '';
             
             for (var i = length; i > 0; --i) result += chars[Math.floor(Math.random() * chars.length)];
             return result;
        },
    	/**
    	 * Trigger saving processing
    	 */
        triggerProcessing: function(spinner_selector) {
	        var spinner = '<img src="' + prime_mover_control_panel_renderer.prime_mover_settings_ajax_spinner_gif + '" />'
	        $(spinner_selector).html(spinner);
	        $(spinner_selector).removeClass('notice notice-error');
                $(spinner_selector).removeClass('notice notice-success');
                $(spinner_selector).removeClass('notice notice-warning');
        },
    	/**
    	 * Compute backup dir size
    	 */
        computeBackupDirSize: function() {
	    $('body').on('click','#js-prime-mover-backup-directory-size-button', function(){ 
                var spinner_selector = '.js-prime-mover-backup-directory-size-spinner';
                if (PrimeMoverControlPanel.isDoingAjax(spinner_selector)) {
                    return;
                } 
                PrimeMoverControlPanel.triggerProcessing(spinner_selector);	   

	        var button_nonce = $(this).attr('data-nonce');	
                var data = PrimeMoverControlPanel.defineGenericData('prime_mover_computedir_size', button_nonce);

                data.compute_dir_size = 'yes';
                PrimeMoverControlPanel.doAjaxRequest(data, spinner_selector);	        
	    });
        },
    	/**
    	 * AJAX handler for deleting all backups upon request
    	 */
       clearLogs: function() {
	    $('body').on('click','#js-clear-prime-mover-troubleshooting', function(){ 

                 var dialog_selector = '#js-prime-mover-panel-clearall-dialog';
                 var button_nonce = $(this).attr('data-nonce');

                 PrimeMoverControlPanel.showDialogHandler(dialog_selector, button_nonce, prime_mover_control_panel_renderer.prime_mover_clearall_button, 
'prime-mover-deleteall-button', '.js-save-prime-mover-clear-log-spinner', 'prime_mover_clear_troubleshooting_log', 'clear_confirmation', 'clearlog', prime_mover_control_panel_renderer.prime_mover_cancel_button);                        
	    });	
        },
    	/**
    	 * AJAX handler for deleting all backups upon request
    	 */
        deleteAllBackups: function() {
	        $('body').on('click','#js-delete_all_backup_zips_network', function(){ 

             var dialog_selector = '#js-prime-mover-panel-deleteall-dialog';
             var button_nonce = $(this).attr('data-nonce');

             PrimeMoverControlPanel.showDialogHandler(dialog_selector, button_nonce, prime_mover_control_panel_renderer.prime_mover_delete_continue_button, 
'prime-mover-deleteall-button', '.js-delete_all_backup_zips_network-spinner', 'prime_mover_delete_all_backups_request', 'delete_confirmation', 'yes', prime_mover_control_panel_renderer.prime_mover_cancel_button);               
	        });
        },
    	/**
    	 * AJAX handler for resetting back to default settings
    	 */
        resetToDefaults: function() {
	    $('body').on('click','#js-prime-mover-reset-settings', function(){ 

                 var dialog_selector = '#js-prime-mover-panel-resettodefault-dialog';
                 var button_nonce = $(this).attr('data-nonce');

                 PrimeMoverControlPanel.showDialogHandler(dialog_selector, button_nonce, prime_mover_control_panel_renderer.prime_mover_delete_continue_button, 
'prime-mover-deleteall-button', '.js-reset-back-to-defaults-migration-spinner', 'prime_mover_reset_settings', 'reset_confirmation', 'yes', prime_mover_control_panel_renderer.prime_mover_cancel_button);               
	    });
        },
        /**
         * Fluid Dialog handler
         */
        fluidDialog: function() {            
            var $visible = $(".ui-dialog:visible");        	    
            $visible.each(function () {
                var $this = $(this);
                var dialog = $this.find(".ui-dialog-content").data("ui-dialog");        	        
                if (dialog.options.fluid) {
                    var wWidth = $(window).width();        	            
                    if (wWidth < (parseInt(dialog.options.maxWidth) + 50))  {        	                
                        $this.css("max-width", "90%");
                    } else {        	        	
                	var adjusted_width = 0.9 * parseInt(dialog.options.maxWidth);                	
                        $this.css("max-width", adjusted_width + "px");
                    }        	            
                    dialog.option("position", dialog.options.position);
                }
           });       	
        },
        /**
         * Handle responsive dialog
         */
        handle_responsive_dialog: function() {
	    $(window).resize(function () {
	      PrimeMoverControlPanel.fluidDialog();
  	    });
	    PrimeMoverControlPanel.fluidDialog();        	
        },
    	/**
    	 * Define ajax data
    	 */
        defineGenericData: function(action, nonce) {
	  	var data = {
		 action: action,
		 dataType: 'json',	 
		 savenonce: nonce			 		    				    					
		};
                return data;
        },
    	/**
    	 * Execute other hooks after response is received
    	 */
        executeOtherAfterSavedHooks: function(data_selector, saved_settings) {
            if ('#js-prime-mover-authorized-domains' === data_selector) {
                PrimeMoverControlPanel.showClipBoardButtonWhenSiteKeyExist(saved_settings);
            }
        },
    	/**
    	 * Slide toggler handler for activated plugins
    	 */
        slideTogglerActivatedPlugins: function() {
            var toggling_el = '#js-prime-mover-toggle-activated-plugins'; 
            var checkboxes_el = '#js-prime-mover-activated-plugins-helper input:checkbox';
            
            var checked_el = '#js-prime-mover-activated-plugins-helper input:checkbox:checked';
            var toggler = '#js-prime-mover-activated-plugins-helper';
            var excluded_plugins_textarea = '#js-prime-mover-excluded-plugins';
	    
            $('body').on('click',toggling_el, function(){                
                $(toggler).slideToggle( "fast", function() {
                    if ($(this).is(":visible")) {
                        $(toggling_el).text(prime_mover_control_panel_renderer.prime_mover_close_text);
                    } else {
                        $(toggling_el).text(prime_mover_control_panel_renderer.prime_mover_expand_text);
                    }
                });      		 	  
	    });

            $(checkboxes_el).change(function() {
                var searchIDs = $(checked_el).map(function(){
                return $(this).val();
                }).get();

                var processed_value = searchIDs.join("\n");
                $(excluded_plugins_textarea).val(processed_value);                
            }); 
        },
    	/**Show clipboard when site key exist
    	 */
        showClipBoardButtonWhenSiteKeyExist: function(saved_settings) {
            var found = false;
            var saved_value = $.trim(saved_settings);
            if ( ! saved_value ) {
                $('.js-prime-mover-copy-key').hide();
                return; 
            }
            var hostdomain = prime_mover_control_panel_renderer.prime_mover_host_domain;
            var arrayOfLines = saved_value.split('\n');
            
            var filtered = arrayOfLines.filter(function (el) {
                if (0 === el.length || el === null) {
                        return false; 
                }
                return true;
            });
            var arrayLength = filtered.length;
            for (var i = 0; i < arrayLength; i++) {
                var input_data = filtered[i];
                if ( input_data.indexOf(":") !== -1 ) {
                    var myarr = input_data.split(":");
                    var domain_data = myarr[0];
                    if (domain_data === hostdomain) {
                        found = true;
                        $('.js-prime-mover-copy-key').show();
                    }                        
                 }
            }
            if (false === found) {
                $('.js-prime-mover-copy-key').hide();
            }
        },
    };
}(jQuery));
