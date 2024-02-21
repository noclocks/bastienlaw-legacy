(function ($) {
	$(function(){
		PrimeMoverCoreJsObject.init();	
	});
	window.PrimeMoverCoreJsObject = {
			/**
			 * Initialize
			 */
			init: function() {
				this.handleExportType();
				this.doExport();	
				this.doImport();   
				this.exportAutomation();
				this.restoreBackupFree();
			},
			/**
			 * Restore from backup path available in Free version also.
			 */
			restoreBackupFree: function() {
				var backup_path = this.getUrlParameter('prime_mover_backup_path');				
				var blog_id = this.getUrlParameter('prime_mover_backup_blogid'); 
				
				if ( ! backup_path || ! blog_id) {
	        		return;
	        	}				
                var prime_mover_tools = this.prime_mover_migration_tools;    
				window.history.replaceState({}, "Title", prime_mover_tools); 

				var args = {import_restorepath:backup_path};	            		              		  
				PrimeMoverCoreJsObject.initiateSpinner(blog_id);
				PrimeMoverCoreJsObject.prime_mover_track_progress('import', blog_id, false, args);
			},			
			/**
			 * Prime Mover Migration Tools
			 */
			prime_mover_migration_tools: prime_mover_js_ajax_renderer.prime_mover_migration_tools_url,
			/**
	    	 * Export automation
	    	 */
	        exportAutomation: function() {        	
	        	var blog_id = this.getUrlParameter('blog_id');  
	        	var action = this.getUrlParameter('action'); 	
	        	
	        	if ('prime_mover_create_backup_action' !== action || ! blog_id) {
	        		return;
	        	}        	
	        	$('#js-prime_mover_exporting_blog_' + blog_id).click();   
	        	
	        	var prime_mover_tools = this.prime_mover_migration_tools;                	
            	window.history.replaceState({}, "Title", prime_mover_tools);   
	        },
	        /**
	         * Get URL parameter
	         */
			getUrlParameter: function(sParam) {
			    var sPageURL = window.location.search.substring(1),
			        sURLVariables = sPageURL.split('&'),
			        sParameterName,
			        i;
		
			    for (i = 0; i < sURLVariables.length; i++) {
			        sParameterName = sURLVariables[i].split('=');
		
			        if (sParameterName[0] === sParam) {
			        	var str = sParameterName[1];
			            return sParameterName[1] === undefined ? true :  decodeURIComponent(str.replace(/\+/g, ' ')); 
			        }
			    }
	    	},
			/**
			 * Handle export type
			 */
			handleExportType: function() {								
				var multisite_export_checkbox = '.js-prime-mover-export-type';			
				$(multisite_export_checkbox).change(function() {					
				    $('.js-prime-mover-export-now-button').prop('disabled', true);
				    var blog_id = $(this).attr('data-blog-id');	
				    var export_type = $(this).val();
				    if ('multisite-export' === export_type) {
				    	$("#js-prime-mover-export-to-multisite-div-" + blog_id).slideToggle();
				    	$("#js-prime-mover-export-targetid-"+ blog_id).val('');
				    	
				    	$('.js-prime-mover-export-now-button').prop('disabled', true);
						$("#js-prime-mover-export-targetid-"+ blog_id).on('keyup', function () {							
							var value = $(this).val();
							value = value.trim();							
							$('.js-prime-mover-export-now-button').prop('disabled', true);
							if (value && PrimeMoverCoreJsObject.isValidBlogId(value)) {
								 $('.js-prime-mover-export-now-button').prop('disabled', false);	
							}
						});
				    } else {
				    	$("#js-prime-mover-export-to-multisite-div-" + blog_id).slideUp();
				    	$('.js-prime-mover-export-now-button').prop('disabled', false);	
				    } 				   				
				});							
			},
			/**
			 * Checks if valid ID is passed
			 */
			isValidBlogId: function(str) {    		
				var n = Math.floor(Number(str));
				return n !== Infinity && String(n) === str && n > 0;    		   		
			},
			/**
			 * Search something in JavaScript object
			 */
			searchInObject: function(nameKey, myArray, ret_type, exact){
				if (typeof(ret_type) === 'undefined') {
					var ret_type = 'bool';
				} 
				if (typeof(exact) === 'undefined') {
					var exact = true;
				}   
				for (var i=0; i < myArray.length; i++) {					
					if (exact && myArray[i].filename === nameKey) {
						if ('bool' === ret_type) {
							return true;        		
						} 
						if ('key' === ret_type) {
							return i;
						}    	            
					}
					var given_string = myArray[i].filename;
					if (false === exact && given_string.includes(nameKey) && 'bool' === ret_type) {						
						return true; 
					}
				}
				return false;
			},
			/**
			 * Request import
			 */
			doImport: function() {
				var import_column = 'body';
				$(import_column).on('click','input.js-prime_mover_importbrowsefile' , function(){ 
					$(this).val( null );	
					var blog_id = $(this).attr("data-multisiteblogid");
					if (blog_id) {
						$('#js-prime_mover_import_progress_span_p_'+ blog_id ).removeClass('notice notice-success');
						$('#js-prime_mover_import_progress_span_p_'+ blog_id ).removeClass('notice notice-error');
						$('#js-multisite_import_span_'+ blog_id ).removeClass('notice notice-error prime-mover-corrupt-package-error');
						PrimeMoverCoreJsObject.cleanProgressSpans(blog_id, 'import');   				
					}    			
				});	
				$(import_column).on('change','input.js-prime_mover_importbrowsefile' , function(){     			
					var blog_id = $(this).attr("data-multisiteblogid");	
					if ( ( parseInt( blog_id ) > 0 ) && ( parseInt( $(this)[0].files[0].size ) > 0 ) ) {
						var uploaderror_selector = PrimeMoverCoreJsObject.getGenericFailSelector(blog_id);
						PrimeMoverCoreJsObject.initializeDialogMonitor(uploaderror_selector, true);
						var ext = $(this).val().split('.').pop().toLowerCase();    				
						if($.inArray(ext, ['zip', 'wprime']) == -1) {
							PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-wrong-filetype-" + blog_id, 0, '', false, prime_mover_js_ajax_renderer.prime_mover_invalid_package); 
						} else if (prime_mover_js_ajax_renderer.prime_mover_phpuploads_misconfigured) {
							PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-wrong-filetype-" + blog_id, blog_id, 'import', true, prime_mover_js_ajax_renderer.prime_mover_upload_misconfiguration_error);   				    	
						} else if ('readonly' === prime_mover_js_ajax_renderer.prime_mover_config_writable) {
							PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-wrong-filetype-" + blog_id, blog_id, 'import', true, prime_mover_js_ajax_renderer.prime_mover_caching_enabled_error);
							
						} else if ($.inArray(ext, ['wprime']) !== -1) {	
							PrimeMoverCoreJsObject.initiateSpinner(blog_id, 'zip_analysis');	
							var the_import_package = $(this)[0].files[0];
							var package_size = the_import_package.size;
							var prime_mover_fileupload_sizelimit = Number(prime_mover_js_ajax_renderer.prime_mover_browser_upload_limit); 
							if (package_size > prime_mover_fileupload_sizelimit) {
								error = prime_mover_js_ajax_renderer.prime_mover_exceeded_browser_limit;
								error = error.replace('{{WPRIME_EXPORT_PATH}}', prime_mover_js_ajax_renderer.wprime_export_path + blog_id);
								
								PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-wrong-filetype-" + blog_id, blog_id, 'import', true, error); 
							} else {																
								let file = the_import_package;								
								let password = '';
								archiveOpenFile(file, password, function(archive, err) {									
									if (archive) {			
										PrimeMoverCoreJsObject.readFootPrintFromTar(archive, blog_id, the_import_package);
									} 
								});															
							}						
						} else {							
							if ('no' === prime_mover_js_ajax_renderer.is_zip_extension_installed) {							 
								PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-wrong-filetype-" + blog_id, blog_id, 'import', true, prime_mover_js_ajax_renderer.no_zip_extension_error); 
								
							} else {
								PrimeMoverCoreJsObject.initiateSpinner(blog_id, 'zip_analysis');
								var the_import_package = $(this)[0].files[0];    					
								PrimeMoverCoreJsObject.processZipJs(the_import_package, blog_id);   								
							}
							
							                     
						}				
					}		
				});    		
			},
			/**
			 * Get filebasename
			 */
			getFileBasename: function(path) {
			    return path.replace(/.*\//, '');
			},
			/**
			 * Analyze restore mode of WPRIME package
			 */
			getTarPackageRestoreMode: function(config) {     		
				var restore_mode = [];				
				var export_option = config['export_options'];
				if ('complete_export_mode' === export_option) {
					restore_mode['complete_export_mode'] = prime_mover_js_ajax_renderer.prime_mover_export_mode_texts.complete_export_mode;		
				} else if ('development_package' === export_option) {
					restore_mode['development_package'] = prime_mover_js_ajax_renderer.prime_mover_export_mode_texts.development_package;	
				} else if ('db_and_media_export' === export_option) {
					restore_mode['db_and_media_export'] = prime_mover_js_ajax_renderer.prime_mover_export_mode_texts.db_and_media_export;
				} else if ('dbonly_export' === export_option) {
					restore_mode['dbonly_export'] = prime_mover_js_ajax_renderer.prime_mover_export_mode_texts.dbonly_export;
				}				

				return restore_mode;   		
			},
			/**
			 * Read footprint from .wprime archive
			 */
			readFootPrintFromTar: function(archive, blog_id, the_import_package) {				
				let entries = [];				
				var foldername = '';
				for (let entry of archive.entries) {				  
				  var filename = PrimeMoverCoreJsObject.getFileBasename(entry.name);					
					if (prime_mover_js_ajax_renderer.prime_mover_wprime_config === filename) {
						foldername = PrimeMoverCoreJsObject.dirname(entry.name);
						foldername = foldername[0];
						
						entries[foldername] = entry;	
						break;
					}	
				}				
				
				archive.entries = entries;				
				for (var key in archive.entries) {
				    var entry = archive.entries[key];
				    if (entry.is_file) {						
						entry.readData(function(data, err) {
							let blob = new Blob([data], {type: 'application/json'});				
							var myReader = new FileReader();
							myReader.addEventListener("loadend", function(e){
								var json_string = e.srcElement.result;
								var config = JSON.parse(json_string);
								var exported_mode_texts = PrimeMoverCoreJsObject.getTarPackageRestoreMode(config);
								var encrypted = config['encrypted'];
								var signature_to_check = config['prime_mover_encrypted_signature'];
								if (encrypted) {
									var data = {
											action: 'prime_mover_verify_encrypted_package',
											dataType: 'json',	 
											stringtocheck: signature_to_check,
											encrypted_media: 'true',
											prime_mover_decryption_check_nonce: prime_mover_js_ajax_renderer.prime_mover_decryption_check_nonce,
											blog_id: blog_id,
											package_ext: 'wprime'
									};

									$.post(ajaxurl, data, function(response) {
										if (response.result) {											
											PrimeMoverCoreJsObject.triggerImportAfterPackageCheck(json_string, blog_id, foldername, the_import_package, true, exported_mode_texts, true, true);				 	    	    	
										} else {
											var error_message = response.error;
											PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-wrong-filetype-" + blog_id, blog_id, 'import', true, error_message);  													 	    	    	
										}			 	    	 
									}).fail(function(xhr, status, error) {
										var ajax_handler_object = this;	 	    	
										PrimeMoverCoreJsObject.doErrorHandling(blog_id, 'import', ajax_handler_object, null, true, 'handleEncryptedPackageCheck');		   	 	    
									}); 
								} else {									
									PrimeMoverCoreJsObject.triggerImportAfterPackageCheck(json_string, blog_id, foldername, the_import_package, false, exported_mode_texts, false, true);	
								}								
							});
							myReader.readAsText(blob);
						});
					}
				}
			},
			/**
			 * Get dirname
			 */
		    dirname: function(path) {
		         return path.match(/.*\//);
		    },
			/**
			 * Process zip
			 */
			processZipJs: function(the_import_package, blog_id) {
				zip.workerScriptsPath = prime_mover_js_ajax_renderer.prime_mover_zipjs_workers;    					
				zip.createReader(new zip.BlobReader(the_import_package), function(reader) {
					reader.getEntries(function(entries) {
						if (entries.length) {
							var zipfoldername = entries[0].filename;
							var signaturefile = zipfoldername + 'signature.enc';
							var mediasignaturefile = zipfoldername + 'media.enc';

							var encrypted = false; 
							var encrypted_media = false;					
							var signature_key = PrimeMoverCoreJsObject.searchInObject(signaturefile, entries, 'key');
							if (false !== signature_key) {
								encrypted = true;
							}				

							var media_signature_key = PrimeMoverCoreJsObject.searchInObject(mediasignaturefile, entries, 'key');
							if (false !== media_signature_key) {
								encrypted_media = true;
							}	

							PrimeMoverCoreJsObject.handleEncryptedPackageCheck(encrypted, signaturefile, entries, signature_key, reader, the_import_package, zipfoldername, blog_id, encrypted_media);				    
						} else {
							PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-wrong-filetype-" + blog_id, blog_id, 'import', true, prime_mover_js_ajax_renderer.prime_mover_invalid_package); 
						}
					});
				}, function(error) {			
					var package_size = the_import_package.size;
					var prime_mover_fileupload_sizelimit = Number(prime_mover_js_ajax_renderer.prime_mover_browser_upload_limit);
					if (package_size > prime_mover_fileupload_sizelimit) {						
						error = prime_mover_js_ajax_renderer.prime_mover_exceeded_browser_limit;
						error = error.replace('{{WPRIME_EXPORT_PATH}}', prime_mover_js_ajax_renderer.wprime_export_path + blog_id);
					}
					PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-wrong-filetype-" + blog_id, blog_id, 'import', true, error); 
				});    		
			},
			/**
			 * Generate random string for upload speed testing
			 */
			getRandomString: function ( sizeInMb ) {
				var chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789~!@#$%^&*()_+`-=[]\{}|;':,./<>?";
				var iterations = sizeInMb * 1024 * 1024;
				var result = '';
				for( var index = 0; index < iterations; index++ ) {
					result += chars.charAt( Math.floor( Math.random() * chars.length ) );
				};     
				return result;
			},
			/**
			 * Check upload speed method helper
			 */
			checkUploadSpeed: function () {
				return new Promise(function (resolve, reject) {    		   
					var xhr = new XMLHttpRequest();
					var url = '?cache=' + Math.floor( Math.random() * 10000 );
					var data = PrimeMoverCoreJsObject.getRandomString( 1 );
					var startTime;

					xhr.onreadystatechange = function ( event ) {
						if (xhr.readyState === 4) {  
							if (200 === xhr.status) {  
								uploadspeed = Math.round( 1024 / ( ( new Date() - startTime ) / 1000 ) ); 
								return resolve(uploadspeed);
							} else {  
								var uploadspeed = false;
								return resolve(uploadspeed);
							}  
						} 
					};
					xhr.open( 'POST', url, true );
					startTime = new Date();
					xhr.send(data);
				});
			}, 
			/**
			 * Calculate chunk size based on several factors
			 * particularly server limits and user upload speed
			 * Note that uploadspeed is in kb/s
			 */
			calculateChunkSizeBasedOnUploadSpeed: function(uploadspeed) {
				var maximum_slice = Number( prime_mover_js_ajax_renderer.prime_mover_slice_size );
				var theoretical_max = 16 * 1024 * 1024;
				var practical_max = maximum_slice;
				if (theoretical_max < maximum_slice) {
					practical_max = theoretical_max;
				}

				if ( ! uploadspeed ) {	     			
					return 98304;
				}

				uploadspeed = Number(uploadspeed);
				var ideal_chunk_based_on_uploadspeed = uploadspeed * 15 * 1024;

				if (ideal_chunk_based_on_uploadspeed < practical_max) {
					practical_max = ideal_chunk_based_on_uploadspeed;
				}

				return practical_max;

			},
			/**
			 * Calculate upload speed from client browser
			 * to the site server where the migration will take place
			 */
			calculateUploadSpeed: function (blog_id) {
				return new Promise(async function (resolve, reject) {
					var uploadspeed = [];
					let speeddata;
					for (var i of [1,2]) {
						speed_data = await PrimeMoverCoreJsObject.checkUploadSpeed();
						if (speed_data) {
							uploadspeed.push(speed_data);		
						}
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Upload speed measurement data: ' + speed_data, 'import', 'calculateUploadSpeed');

					}
					if (uploadspeed === undefined || uploadspeed.length == 0) {
						return resolve(false);
					}

					const nums = uploadspeed.filter(n => !isNaN(n)).map(Number)
					const ave = nums.reduce((x, y) => x + y) / (nums.length || 1);

					return resolve(ave);	
				});
			},
			/**
			 * Do after client package validation
			 */
			doAfterPackageClientValidation: function(the_import_package, entries, reader, zipfoldername, blog_id, encrypted, encrypted_media) { 
				var exported_mode_texts = PrimeMoverCoreJsObject.analyzeRestoreMode(entries, zipfoldername, blog_id);							  
				var footprint_path = zipfoldername + 'footprint.json';		    
				var footprint_key = PrimeMoverCoreJsObject.searchInObject(footprint_path, entries, 'key');	 
				if (footprint_key) {
					entries[footprint_key].getData(new zip.TextWriter(), function(text) { 
						PrimeMoverCoreJsObject.triggerImportAfterPackageCheck(text, blog_id, zipfoldername, the_import_package, encrypted, exported_mode_texts, encrypted_media, false);		
						reader.close(function() {});	

					}, function(current, total) {});             
				} else {
					PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-wrong-filetype-" + blog_id, blog_id, 'import', true, prime_mover_js_ajax_renderer.prime_mover_invalid_package); 
				}   		
			},			
			/**
			 * Trigger after package check
			 * used for both wprime and zip packages
			 * @param text
			 * @param blog_id
			 * @param zipfoldername
			 * @param the_import_package
			 * @param encrypted
			 * @param exported_mode_texts
			 * @param encrypted_media
			 * @returns
			 */
			triggerImportAfterPackageCheck: async function(text, blog_id, zipfoldername, the_import_package, encrypted, exported_mode_texts, encrypted_media, is_wprime) {	
				
				var footprint_var = JSON.parse(text);				
				var environment = prime_mover_js_ajax_renderer.prime_mover_environment;
				var site_title = footprint_var['site_title'];               
				
				var correctBlogID = Number(blog_id);
				var wrongselector = '#js-prime-mover-wrong-importedsite-' + blog_id;
				zipfoldername.replace(/\s+/g, '');				

				var blogidimported = PrimeMoverCoreJsObject.getBlogIDfromFolderName(zipfoldername);				
				var error = '';		
				var mainsite_id = prime_mover_js_ajax_renderer.prime_mover_mainsite_id;
				
				$match = true;
				var the_export_type = '';
				if ('prime_mover_export_type' in footprint_var) {
					the_export_type = footprint_var.prime_mover_export_type;
				}
				
				if (!blogidimported || !correctBlogID) {
					$match = false;						
					error = prime_mover_js_ajax_renderer.prime_mover_package_mismatch_undefined_blogid;
				}		
				
				if ($match && the_export_type && the_export_type !== environment) {
					error = prime_mover_js_ajax_renderer.prime_mover_package_mismatch_export_type;
					error = error.replace('{{SOURCE_PACKAGE_TYPE}}', footprint_var.prime_mover_export_type);
					error = error.replace('{{TARGET_PACKAGE_TYPE}}', environment);
					
					$match = false;
				}	
				
				if ($match && blogidimported !== correctBlogID) {
					$match = false;					
				}				
				
				if (!$match && !error && !the_export_type && 'multisite' === environment && 1 === blogidimported) {				
					error = prime_mover_js_ajax_renderer.prime_mover_package_mismatch_export_type;
					error = error.replace('{{SOURCE_PACKAGE_TYPE}}', 'single-site');
					error = error.replace('{{TARGET_PACKAGE_TYPE}}', environment);
					
					$match = false;				
				}
				
				if (!$match && !error && 'multisite' === environment) {				
					error = prime_mover_js_ajax_renderer.prime_mover_package_mismatch_subsite_mismatch;
				}
				
				if (!$match && !error && 'single-site' === environment) {					
					error = prime_mover_js_ajax_renderer.prime_mover_package_mismatch_singlesite_mismatch;
				}							
				
				if ($match && 'multisite' === environment && blog_id === mainsite_id && !the_export_type) {
					$match = false;
					error = prime_mover_js_ajax_renderer.prime_mover_package_mismatch_mainsite;
				}
				
				if ($match) {
					let uploadspeed = await PrimeMoverCoreJsObject.calculateUploadSpeed(blog_id);
					var uploadspeed_identifier = PrimeMoverCoreJsObject.generateUniqueIdentifier('import', blog_id, '_uploadSpeedId_' );
					PrimeMoverCoreJsObject[uploadspeed_identifier] = uploadspeed;
					
					if (false === uploadspeed) {
						PrimeMoverCoreJsObject.user_notices(wrongselector, correctBlogID, 'import', true, prime_mover_js_ajax_renderer.prime_mover_bailout_upload_text, 
								undefined, undefined, undefined, prime_mover_js_ajax_renderer.prime_mover_package_error_heading);						
					} else {
						
						PrimeMoverCoreJsObject.cleanProgressSpans(blog_id, 'import'); 
    					PrimeMoverCoreJsObject.generalImportWarningDialog(blog_id, the_import_package, '', '', '', encrypted, 
    							exported_mode_texts, site_title, the_import_package.name, '', encrypted_media); 						
					}
					    					
				} else {					
					PrimeMoverCoreJsObject.user_notices(wrongselector, correctBlogID, 'import', true, error, undefined, undefined, undefined, prime_mover_js_ajax_renderer.prime_mover_package_mismatch_heading);					
				}	
			},		
			/**
			 * Handle encrypted package check
			 */
			handleEncryptedPackageCheck: function(encrypted, signaturefile, entries, signature_key, reader, the_import_package, zipfoldername, blog_id, encrypted_media) {
				if (encrypted) {
					entries[signature_key].getData(new zip.TextWriter(), function(text) {		        	
						var data = {
								action: 'prime_mover_verify_encrypted_package',
								dataType: 'json',	 
								stringtocheck: text,
								encrypted_media: encrypted_media,
								prime_mover_decryption_check_nonce: prime_mover_js_ajax_renderer.prime_mover_decryption_check_nonce,
								blog_id: blog_id,
								package_ext: 'zip'
						};

						$.post(ajaxurl, data, function(response) {
							if (response.result) {
								PrimeMoverCoreJsObject.doAfterPackageClientValidation(the_import_package, entries, reader, zipfoldername, blog_id, encrypted, encrypted_media); 			 	    	    	
							} else {
								var error_message = response.error;
								PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-wrong-filetype-" + blog_id, blog_id, 'import', true, error_message);   
								reader.close(function() {});			 	    	    	
							}			 	    	 
						}).fail(function(xhr, status, error) {
							var ajax_handler_object = this;	 	    	
							PrimeMoverCoreJsObject.doErrorHandling(blog_id, 'import', ajax_handler_object, null, true, 'handleEncryptedPackageCheck');		   	 	    
						}); 		        	
					}, function(current, total) {}); 			    				    	   							    	
				} else {
					PrimeMoverCoreJsObject.doAfterPackageClientValidation(the_import_package, entries, reader, zipfoldername, blog_id, encrypted, encrypted_media);	    	
				}		    
			},
			/**
			 * Analyze restore mode
			 */
			analyzeRestoreMode: function(zipfiles, zipfoldername, blog_id) {     		
				var restore_mode = [];
				var plugins = zipfoldername + 'plugins' + '/';
				var media = zipfoldername + 'media.zip';			

				if (PrimeMoverCoreJsObject.searchInObject(plugins, zipfiles, 'bool', false)) {
					if (PrimeMoverCoreJsObject.searchInObject(media, zipfiles)) {
						restore_mode['complete_export_mode'] = prime_mover_js_ajax_renderer.prime_mover_export_mode_texts.complete_export_mode;					
					} else {
						restore_mode['development_package'] = prime_mover_js_ajax_renderer.prime_mover_export_mode_texts.development_package;				
					}				
				} else if (PrimeMoverCoreJsObject.searchInObject(media, zipfiles)) {
					restore_mode['db_and_media_export'] = prime_mover_js_ajax_renderer.prime_mover_export_mode_texts.db_and_media_export;
				} else {
					restore_mode['dbonly_export'] = prime_mover_js_ajax_renderer.prime_mover_export_mode_texts.dbonly_export;
				} 

				return restore_mode;   		
			},
			/**
			 * Human readable filesize
			 * Credits: https://stackoverflow.com/questions/10420352/converting-file-size-in-bytes-to-human-readable-string#answer-14919494
			 */
			humanFileSize: function (bytes, si) {
				var thresh = si ? 1000 : 1024;
				if(Math.abs(bytes) < thresh) {
					return bytes + ' B';
				}
				var units = si
				? ['kB','MB','GB','TB','PB','EB','ZB','YB']
				: ['KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB'];
				var u = -1;
				do {
					bytes /= thresh;
					++u;
				} while(Math.abs(bytes) >= thresh && u < units.length - 1);
				return bytes.toFixed(1)+' '+units[u];
			},
			/**
			 * Compute time to upload in minutes
			 * take note package_size is already in bytes
			 * upload_speed is still in kb
			 */
			computeTimeToUpload: function(upload_speed, package_size) {    		
				var chunk_size = this.calculateChunkSizeBasedOnUploadSpeed(upload_speed);
				var minutes = (package_size / chunk_size) * (5/60);
                
				var margin = (0.02) * minutes;
				minutes = minutes + margin;
				var roundup = this.roundUp(minutes, 0);
				
				var refresh_factor = this.computeUploadRefreshFactor();
				roundup = roundup * refresh_factor;				
				
				if (roundup < 2) {
					return roundup + ' ' + prime_mover_js_ajax_renderer.prime_mover_minute_text;
				} else {
					return roundup + ' ' + prime_mover_js_ajax_renderer.prime_mover_minutes_text;
				}

			},
			/**
			 * Compute refresh upload factor
			 */
			computeUploadRefreshFactor: function() {				
				var refresh_upload_time = prime_mover_js_ajax_renderer.prime_mover_upload_refresh_interval;
				var refresh_factor = (1/5) * (refresh_upload_time) * (1/1000);
				
				return refresh_factor;
			},
			/**
			 * Round up helper
			 */
			roundUp: function(num, precision) {
				precision = Math.pow(10, precision)
				return Math.ceil(num * precision) / precision
			},
			/**
			 * Returns upload speed if set
			 * Otherwise false
			 */
			measuredUploadSpeed : function(blog_id) {
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Is local: ' + prime_mover_js_ajax_renderer.is_local_server, 'import', 'measuredUploadSpeed');
				var uploadspeed_identifier = this.generateUniqueIdentifier('import', blog_id, '_uploadSpeedId_' );        	
				var uploadspeed = false;
				if (PrimeMoverCoreJsObject.isUniqueIdentifierSet(uploadspeed_identifier)) {
					uploadspeed = PrimeMoverCoreJsObject[uploadspeed_identifier];
				}

				return uploadspeed;        	
			},
			/**
			 * Set dialog restore warning info
			 */
			setDialogWarningRestoreInfoText: function(target_selector, blog_id, restore_mode, encrypted, exported_mode_texts, site_title, source, packagesize, encrypted_media, package_size_raw, is_wprime) {    		
				var restoration_mode_selector = target_selector + ' .js-prime-mover-warning-restoration-mode';
				var restoration_blog_id = target_selector + ' .js-prime-mover-warning-target-blog-id';
				var restoration_site_title = target_selector + ' .js-prime-mover-warning-target-site-title';
				var restoration_source_origin = target_selector + ' .js-prime-mover-warning-source-of-origin';

				var restoration_encrypted_status = target_selector + ' .js-prime-mover-warning-restoring-encrypted';
				var restoration_encrypted_wprime = target_selector + ' .js-prime-mover-encrypted-wprime';
				var restoration_encrypted_note = target_selector + ' .js-prime-mover-encrypted-package-note';
				var exported_texts_selector = target_selector + ' .js-prime-mover-warning-scope-mode';

				var restoration_encrypted_media_status = target_selector + ' .js-prime-mover-encrypted-media';
				var exported_texts_selector = target_selector + ' .js-prime-mover-warning-scope-mode';
				var restoration_packagesize_selector = target_selector + ' .js-prime-mover-package-size-dialog';
				var encrypted_media_li = target_selector + ' #js-restoration-encrypted-mediafiles-prime-mover';

				var prime_mover_uploadtime_selector = target_selector + ' .js-prime-mover-upload-option-selected';
				var prime_mover_uploadtime_span = target_selector + ' #js-prime-mover-computed-uploadtime';
                var wprime_li = target_selector + ' #js-restoration-encrypted-wprime';				
                var restorationzip_db_selector = target_selector + ' #js-restoration-encrypted-database-prime-mover';
                
				var uploadspeed = PrimeMoverCoreJsObject.measuredUploadSpeed(blog_id);            
				var time_it_takes;
				if (uploadspeed) {    			
					time_it_takes = PrimeMoverCoreJsObject.computeTimeToUpload(uploadspeed, package_size_raw);
					$(prime_mover_uploadtime_span).text(time_it_takes);   		    
					$(prime_mover_uploadtime_selector).show();
				}
                if (is_wprime) {
                	$(restoration_encrypted_wprime).html(prime_mover_js_ajax_renderer.prime_mover_restore_noencryption_warning);               	        	
                } else {                	
                	$(restoration_encrypted_status).html(prime_mover_js_ajax_renderer.prime_mover_restore_noencryption_warning);
    				$(restoration_encrypted_media_status).html(prime_mover_js_ajax_renderer.prime_mover_restore_noencryption_warning);        
                }
				
				var restore_mode_slug = '';
				var export_mode_description = ''
					for(var key in exported_mode_texts) {
						restore_mode_slug = key;
						export_mode_description = exported_mode_texts[restore_mode_slug];  			
					}				
				$(encrypted_media_li).hide(); 
				$(wprime_li).hide(); 				
				if ('complete_export_mode' === restore_mode_slug || 'db_and_media_export' === restore_mode_slug) {
					if (!is_wprime) {
						$(encrypted_media_li).show(); 					
					}					        	
				}                        
				$(exported_texts_selector).html(export_mode_description);
				$(restoration_encrypted_note).html('');   		
				$(restoration_packagesize_selector).html(packagesize);
				if (is_wprime) {
					$(wprime_li).show(); 
					$(restorationzip_db_selector).hide();
					$(encrypted_media_li).hide(); 
				} else {
					$(restorationzip_db_selector).show();
				}				
				if (true === encrypted) {
					if (is_wprime) {
						$(restoration_encrypted_wprime).html(prime_mover_js_ajax_renderer.prime_mover_restore_encrypted_package_warning);
												
					} else {
						$(restoration_encrypted_status).html(prime_mover_js_ajax_renderer.prime_mover_restore_encrypted_package_warning);  
						$(restoration_encrypted_note).html(prime_mover_js_ajax_renderer.prime_mover_encrypted_note);  					
					}					
				}			
				if (true === encrypted_media && !is_wprime) {
					$(restoration_encrypted_media_status).html(prime_mover_js_ajax_renderer.prime_mover_restore_encrypted_package_warning); 
				}
				if ('restore_within_server' === restore_mode) {
					$(restoration_mode_selector).html(prime_mover_js_ajax_renderer.prime_mover_restorewithinserver_mode);    			
				} else if ('restore_from_remote_url' === restore_mode) {    			
					$(restoration_mode_selector).html(prime_mover_js_ajax_renderer.prime_mover_restore_remote_url_mode);  			
				} else {
					$(restoration_mode_selector).html(prime_mover_js_ajax_renderer.prime_mover_upload_package_mode); 
				}
				if (restoration_blog_id.length) {
					$(restoration_blog_id).html(blog_id);     			
				}
				$(restoration_site_title).html(site_title);  
				$(restoration_source_origin).html(source);  
			},    	
			/**
			 * General import warning dialog
			 */
			generalImportWarningDialog: function(blog_id, the_import_package, gearbox_call, restore_path, restore_mode, encrypted, exported_mode_texts, site_title, source, packagesize, encrypted_media) {			
				if (typeof(gearbox_call) === 'undefined') {
					var gearbox_call = false;
				} 
				if (typeof(restore_path) === 'undefined') {
					var restore_path = null;
				}
				if (typeof(restore_mode) === 'undefined') {
					var restore_mode = null;
				} 
				if (typeof(encrypted) === 'undefined') {
					var encrypted = null;
				}
				if (typeof(exported_mode_texts) === 'undefined') {
					var exported_mode_texts = [];
				} 
				if (typeof(site_title) === 'undefined') {
					var site_title = null;
				} 
				if (typeof(source) === 'undefined') {
					var source = null;
				}
				if (typeof(packagesize) === 'undefined') {
					var packagesize = null;
				} 
				if (typeof(encrypted_media) === 'undefined') {
					var encrypted_media = false;
				} 
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'General Import warning triggered.', 'import', 'generalImportWarningDialog');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Import package: ' + the_import_package, 'import', 'generalImportWarningDialog');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Gearbox call: ' + gearbox_call, 'import', 'generalImportWarningDialog');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Restore path: ' + restore_path, 'import', 'generalImportWarningDialog');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Restore mode: ' + restore_mode, 'import', 'generalImportWarningDialog');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Site title: ' + site_title, 'import', 'generalImportWarningDialog');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Source: ' + source, 'import', 'generalImportWarningDialog');

				var target_selector = "#js-prime-mover-import-warning-confirm-" + blog_id;
				var dialog_selector = $(target_selector);
				var data = $(dialog_selector).data();            

				var packagesize_raw;
				if ( ! packagesize ) {            	
					var packagesize = the_import_package.size;  
					packagesize_raw = packagesize;
					packagesize = this.humanFileSize(packagesize, true);
				}             

				data.gearbox_call = gearbox_call;
				data.restore_path = restore_path;
				data.restore_mode = restore_mode;
				
				var is_wprime = false;
				if (PrimeMoverCoreJsObject.isWprimePackage(gearbox_call, restore_path, source)) {
					is_wprime = true;
				}
				
				PrimeMoverCoreJsObject.setDialogWarningRestoreInfoText(target_selector, blog_id, restore_mode, encrypted, exported_mode_texts, site_title, source, packagesize, encrypted_media, packagesize_raw, is_wprime);            
				$(dialog_selector).dialog({
					resizable: false,
					height: "auto",
					width: "auto",
					maxWidth: 600,
					dialogClass: 'prime-mover-user-dialog',
					modal: true,
					fluid: true,	            
					buttons: [
						{
							text: prime_mover_js_ajax_renderer.yes_button,	
							"class": 'button-primary',
							click: function() {
								$( this ).dialog( "close" );
								var gearbox_called = $(this).data('gearbox_call');
								if (gearbox_called) {
									var args = {import_restorepath:restore_path};	            		  
									PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Gearbox same site server restoration and user confirms warning', 'import', 'generalImportWarningDialog');	            		  
									PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, args, 'import', 'generalImportWarningDialog');

									PrimeMoverCoreJsObject.initiateSpinner(blog_id);
									PrimeMoverCoreJsObject.prime_mover_track_progress('import', blog_id, false, args);
								} else {	        
									PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Core upload support and user confirms warning, firing import handler.', 'import', 'generalImportWarningDialog');	            		  

									var args = {import_package_upload: the_import_package};
									PrimeMoverCoreJsObject.initiateSpinner(blog_id);
									PrimeMoverCoreJsObject.prime_mover_track_progress('import', blog_id, false, args);
								}				           	  
							}
						},
						{
							text: prime_mover_js_ajax_renderer.cancel_button,					 
							click: function() {
								var dialog_object = this;
								$(dialog_object).dialog( "close" );	
								PrimeMoverCoreJsObject.doWhenImportWarningIsCancelled(blog_id, dialog_object, restore_mode, restore_path);
							}
						},				    
						],
						create: function() {
							var dialog_object = this;
							$(dialog_object).closest('div.ui-dialog')
							.find('.ui-dialog-titlebar-close')
							.click(function(e) {	
								restore_mode = $(dialog_object).data('restore_mode');
								restore_path = $(dialog_object).data('restore_path');
								PrimeMoverCoreJsObject.doWhenImportWarningIsCancelled(blog_id, dialog_object, restore_mode, restore_path); 		                        
							});
						},
						open: function( event, ui ) {							
							$('.ui-dialog a').blur();	    	 
						}
				});
				PrimeMoverCoreJsObject.handle_responsive_dialog();   		
			},
			/**
			 * Checks if WPrime package
			 */
			isWprimePackage: function(gearbox_call, restore_path, source) {				
				var filename = '';
				if (gearbox_call && restore_path) {
					filename = PrimeMoverCoreJsObject.getFileBasename(restore_path);
				}
				if (!filename && source) {
					filename = source;
				}				
				var ext = filename.split('.').pop().toLowerCase();  
				return ('wprime' === ext);				
			},
			/**
			 * Do when import warning is cancelled
			 */
			doWhenImportWarningIsCancelled: function(blog_id, dialog_object, restore_mode, restore_path) {    		
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'User does not want to confirm import warning, cancelled.', 'import', 'generalImportWarningDialog');

				var gearbox_called = $(dialog_object).data('gearbox_call');
				if ( gearbox_called && 'restore_from_remote_url' === restore_mode) {
					PrimeMoverCoreJsObject.requestToDeleteTmpFile(restore_path, blog_id, false, true, 'import', 'yes');
				}
				PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, 'import', true );
				PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);    	    
			},    	
			/**
			 * Lock and unlock buttons that is currently running a process
			 */
			lockDownButtons: function(blog_id, lock) {
				if ( ! blog_id ) {
					return;
				}

				if (typeof(lock) === 'undefined') {
					var lock = true;
				}

				var export_button = '#js-prime_mover_exporting_blog_' + blog_id;
				var import_button = '#js-prime_mover_importing_blog_' + blog_id;
				var gearbox_restore_button = '#js-prime_mover_restorebackup_' + blog_id;            
				var upload_label = '#js-prime-mover-browseupload-label-' + blog_id;            


				var canBeDisabledSelectors = [
					export_button,
					import_button,
					gearbox_restore_button
					];

				var arrayLength = canBeDisabledSelectors.length;
				for (var i = 0; i < arrayLength; i++) {
					var processed = canBeDisabledSelectors[i];

					if ($(processed).length ) {
						if (lock) {
							$(processed).prop('disabled', true);           		
						} else {
							$(processed).prop('disabled', false);          		
						}            	
					}               
				}        
                var contact_dev = 'a.js-prime-mover-contact-dev';
				if ($(contact_dev).length) {
					if (lock) {
						$(contact_dev).addClass('prime-mover-disabled-links disabled');           		
					} else {
						$(contact_dev).removeClass('prime-mover-disabled-links disabled');         		
					} 
				}
				if ($(upload_label).length ) {
					if (lock) {
						$(upload_label).addClass('disabled');     		
					} else {
						$(upload_label).removeClass('disabled');        		
					}            	
				}    		
			},
			/**
			 * Request export
			 */
			doExport: function() {
				var export_column = '.js-prime-mover-sites-page';
				$(export_column).on('click','input.js-prime_mover_exportbutton', function(){ 
									
					var blog_id = $(this).attr("data-multisiteblogid");
					var primemoverbutton_class = $(this).attr("data-primemover-button-class");
					$( "#js-prime-mover-export-dialog-confirm-" + blog_id ).dialog({
						resizable: false,
						height: "auto",
						width: "auto",
						maxWidth: 600,
						dialogClass: 'prime-mover-user-dialog',
						modal: true,
						fluid: true,
						buttons: [
							{
								text: prime_mover_js_ajax_renderer.export_now_button,	
								"class": 'js-prime-mover-export-now-button button-primary',
								click: function() {
									$( this ).dialog( "close" );
									var export_option = $('input[name=prime-mover-export-mode-' + blog_id + ']:checked').val();								
									if ( export_option ) {
										PrimeMoverCoreJsObject.prime_mover_exporter( export_option, blog_id );
									}			           	  
								}
							},
							{
								text: prime_mover_js_ajax_renderer.cancel_button,					 
								click: function() {
									$( this ).dialog( "close" );
								}
							},				    
							],
							close: function( event, ui ) {								
								PrimeMoverCoreJsObject.hideClipBoardButton(blog_id);
								
								$('#js-prime_mover_exporting_blog_' + blog_id).removeClass('button');
								$('#js-prime_mover_exporting_blog_' + blog_id).addClass(primemoverbutton_class);																	
							},
							open: function( event, ui ) {								
								$('#js-prime_mover_export_progress_span_p_'+ blog_id).removeClass('notice notice-success');
								$('#js-prime_mover_export_progress_span_p_'+ blog_id).removeClass('notice notice-error');	
								
								$('#js-multisite_import_span_'+ blog_id ).removeClass('notice notice-error prime-mover-corrupt-package-error');
								$('#js-prime_mover_import_progress_span_p_'+ blog_id).removeClass('notice notice-success');
								$('#js-prime_mover_import_progress_span_p_'+ blog_id).removeClass('notice notice-error');
								
								$('.js-prime-mover-export-now-button').prop('disabled', true);
								$('#js-multisite_export_span_'+ blog_id ).html('');
								
								PrimeMoverCoreJsObject.cleanProgressSpans(blog_id, 'export');
								PrimeMoverCoreJsObject.cleanProgressSpans(blog_id, 'import');
								
								$('#js-prime_mover_exporting_blog_' + blog_id).removeClass('button-primary');
								$('#js-prime_mover_exporting_blog_' + blog_id).addClass('button');						 

								if( $("#js-prime-mover-export-targetid-" + blog_id).length){ 				    			 
									$("#js-prime-mover-export-targetid-" + blog_id).val('');	
								}								
								
								$("#js-prime-mover-export-as-single-site-" + blog_id).prop('checked', false);
								$("#js-prime-mover-export-as-multisite-" + blog_id).prop('checked', false);
								
								if( $("#js-prime-mover-export-to-multisite-div-" + blog_id).length){
									$("#js-prime-mover-export-to-multisite-div-" + blog_id).hide();
								}			    	 
							}
					});
					PrimeMoverCoreJsObject.handle_responsive_dialog();
				});    		
			},
			/**
			 * Hide clipboard button if applicable
			 */
			hideClipBoardButton: function (blog_id) {
				var clipboardbutton = "#js-prime-mover-copy-url-clipboard-" + blog_id;
				if( ! $(clipboardbutton).length){
					return;
				}
				$(clipboardbutton).hide();
			},
			/**
			 * Generate refresh ID
			 */
			generateUniqueIdentifier: function ( mode, blog_id, identifier_string ) {
				if (typeof(identifier_string) === 'undefined') {
					var identifier_string = '_refreshID_';
				}
				var refreshid = mode + identifier_string + blog_id;
				return refreshid;
			},
			/**
			 * Checks if refresh ID is set
			 */
			isUniqueIdentifierSet: function(refreshid) {
				if(PrimeMoverCoreJsObject.hasOwnProperty(refreshid)){
					return true;
				} else {
					return false;
				}    		
			},
			/**
			 * Do boot actions
			 */
			doBootActions: function(mode, blog_id, args) {
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Doing boot actions..', mode, 'doBootActions');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, args, mode, 'doBootActions');
				if ('import_restorepath' in args && args.import_restorepath) { 
					
					PrimeMoverCoreJsObject.lockDownButtons(blog_id, true);
					var restore_path = args.import_restorepath;
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Restore path: ' + restore_path, mode, 'doBootActions');

					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Gearbox called and user confirms warning, firing import handler.', 'import', 'doBootActions');
					PrimeMoverCoreJsObject.import_handler( blog_id, restore_path );  			
				} else if (args instanceof FormData) {
					
					this.doDiffPostAjax(mode, blog_id, args);   			
				} else if ('import_package_upload' in args) {
					
					PrimeMoverCoreJsObject.lockDownButtons(blog_id, true);
					var the_import_package = args.import_package_upload;
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Core import package upload triggered after boot.', 'import', 'doBootActions');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, the_import_package, 'import', 'doBootActions');    			

					PrimeMoverCoreJsObject.user_confirms_import_to_proceed( blog_id, the_import_package );
				} else if ('prime_mover_export_nonce' in args) {
					
					PrimeMoverCoreJsObject.lockDownButtons(blog_id, true);
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Core export support requested after boot', 'export', 'doBootActions');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, args, 'import', 'doBootActions');
					PrimeMoverCoreJsObject.doExporterAjax(blog_id, args);
				} else if ('import_package_url' in args) {
					
					PrimeMoverCoreJsObject.lockDownButtons(blog_id, true);
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Gearbox restore from remote URL requested after boot', 'export', 'doBootActions');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, args, 'import', 'doBootActions');

					var package_url = args.import_package_url;				    			     			  
					PrimeMoverCoreJsObject.fetchFileToRestore(package_url, blog_id);
				}
			},
			/**
			 * Fetch file to restore
			 */
			fetchFileToRestore: function(package_url, blog_id) {
				var data = {
						action: 'prime_mover_download_remote_url',
						dataType: 'json',	 
						package_url: package_url,
						prime_mover_downloadfile_nonce: prime_mover_js_ajax_renderer.prime_mover_downloadfile_nonce,
						blog_id: blog_id
				};		
				var start_time = new Date().getTime();
				$.ajax({
					url: ajaxurl,
					type: 'post',		 			            
					data: data, 
					retryLimit: prime_mover_js_ajax_renderer.prime_mover_retry_limit,
					tryCount : 0,
					success: function( response ) {
						if (PrimeMoverCoreJsObject.prime_mover_is_js_object(response) && 'retry_download' in response) {
							
                            PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, "Retrying fetching package request URL: " + package_url, 'import', 'fetchFileToRestore');
                            PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, "Retrying fetching package request for blog ID: " + blog_id, 'import', 'fetchFileToRestore');                           
                            
							setTimeout(function() { PrimeMoverCoreJsObject.fetchFileToRestore(package_url, blog_id); }, prime_mover_js_ajax_renderer.prime_mover_fetch_restore_core_interval); 
						}
					},
					error : function(xhr, textStatus, errorThrown ) {
						var mode = 'import';
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'FetchFilesRestore failed detected, error thrown is ' + errorThrown, mode, 'fetchFileToRestore');

						var refreshid =  PrimeMoverCoreJsObject.generateUniqueIdentifier(mode, blog_id);
						var progress_identifier =  PrimeMoverCoreJsObject.generateProgressIdentifier(refreshid, mode);	            
						var in_progress = false;
						var retry = false;

						if (typeof PrimeMoverCoreJsObject[progress_identifier] !== 'undefined') {
							var latest_progress = PrimeMoverCoreJsObject[progress_identifier];
							if (latest_progress) {
								in_progress = true;
							}
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Latest progress fetch file to restore check: ' + latest_progress, mode, 'fetchFileToRestore');
						} 
						if ( ! in_progress ) {
							retry = true;
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'FetchFilesRestore failed detected, retry status is ' + retry, mode, 'fetchFileToRestore');
							var ajax_handler_object = this;
							PrimeMoverCoreJsObject.doErrorHandling(blog_id, mode, ajax_handler_object, null, retry, 'fetchFileToRestore');	
							
						} else {							
							var request_time_on_error = Math.round((new Date().getTime() - start_time) / (1000));
							if ( ! errorThrown ) {
								errorThrown = prime_mover_js_ajax_renderer.prime_mover_unknown_js_error;
							}  
							
							var msg = prime_mover_js_ajax_renderer.prime_mover_downloadzip_error_message;
							msg = msg.replace('{{PROGRESSSERVERERROR}}', errorThrown);
							msg = msg.replace('{{BLOGID}}', blog_id);
							msg = msg.replace('{{FIXEDSECONDS}}', request_time_on_error);                            
                            
							var tmp_file_to_delete = '';	
							if (PrimeMoverCoreJsObject.download_tmp_path) {
								tmp_file_to_delete = PrimeMoverCoreJsObject.download_tmp_path;  		
							}	
							if (tmp_file_to_delete) {								
								PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Temporary downloaded zip file to be deleted: ' + tmp_file_to_delete, mode, 'fetchFileToRestore');
								PrimeMoverCoreJsObject.requestToDeleteTmpFile(tmp_file_to_delete, blog_id, false, false, mode, 'yes');
							}
							
							PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, mode, true );
							var uploaderror_selector = PrimeMoverCoreJsObject.getGenericFailSelector(blog_id);							
							if ( false === PrimeMoverCoreJsObject.dialogIsOpen(uploaderror_selector, true) ) {               			              		
								PrimeMoverCoreJsObject.user_notices( uploaderror_selector, blog_id, mode, true, msg );
							} 
							PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);							
						}            	
					} 
				});		 	  	 	  
			},   	
			/**
			 * Generate Progress Identifier
			 */
			generateProgressIdentifier: function(refreshid, mode ) {
				return mode + '_' + refreshid; 		
			},
			/**
			 * Download tmp path on gearbox
			 */
			download_tmp_path: '',
			/**
			 * Do status actions
			 */
			doStatusActions: function(status, refreshid, mode, response, blog_id, pulse) {
				if (status) {	 	    	 		
					if ( 'stoptracking' === status ) {		
						if ( false === PrimeMoverCoreJsObject[refreshid] ) {
							return;
						}
						clearTimeout(PrimeMoverCoreJsObject[refreshid]);
						PrimeMoverCoreJsObject[refreshid] = false; 	
						pulse = false;
						if ( 'import' === mode ) {
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Import stop tracking detected, processing import result handler', mode, 'doStatusActions'); 
							PrimeMoverCoreJsObject.import_result_handler( blog_id, response.import_result);
						} else {
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Export stop tracking detected, processing export success handler', mode, 'doStatusActions');
							PrimeMoverCoreJsObject.export_success_handler( blog_id, response.export_result);
						}
						PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
					} else if ('diffdetected' === status) {
						if ( false === PrimeMoverCoreJsObject[refreshid] ) {
							return;
						}
						clearTimeout(PrimeMoverCoreJsObject[refreshid]);	
						PrimeMoverCoreJsObject[refreshid] = false;
						pulse = false;
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Diff detected, processing import result handler diff processor', mode, 'doStatusActions');
						PrimeMoverCoreJsObject.import_result_handler( blog_id, response.import_result);
					} else if ('package_downloaded' === status) {	
						if ( false === PrimeMoverCoreJsObject[refreshid] ) {
							return;
						}
						clearTimeout(PrimeMoverCoreJsObject[refreshid]);	
						PrimeMoverCoreJsObject[refreshid] = false;
						pulse = false;
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Package downloaded detected processing download success handler', mode, 'doStatusActions');
						PrimeMoverCoreJsObject.download_success_handler( blog_id, response.download_result);
					} else if ('downloading_package' === status) {
						if ( false === PrimeMoverCoreJsObject[refreshid] ) {
							return;
						} 	           	    
						if ('total_download_size' in response && 'ongoing_size' in response) {							
							var progress_span_selector = '#js-multisite_import_progress_span_'+ blog_id;
							$(progress_span_selector).html( prime_mover_js_ajax_renderer.prime_mover_downloading_progress_text + '..<progress title="' + prime_mover_js_ajax_renderer.prime_mover_download_percent_progress + '" value="0"></progress>');     	 				;
							$(progress_span_selector + ' progress').attr({
								value: response.ongoing_size,
								max: response.total_download_size,
							});   	 				
						}  	 			 
						if ('download_tmp_path' in response) {
							PrimeMoverCoreJsObject.download_tmp_path = response.download_tmp_path;						
						}						
					} else if ('uploading_to_dropbox' === status) {
						if ( false === PrimeMoverCoreJsObject[refreshid] ) {
							return;
						} 	           	    
						if ('total_upload_size' in response && 'ongoing_size' in response) {
							var progress_span_selector = '#js-multisite_export_progress_span_'+ blog_id;
							$(progress_span_selector).html( prime_mover_js_ajax_renderer.prime_mover_dropbox_upload_progress_text + '..<progress title"' + prime_mover_js_ajax_renderer.prime_mover_dropbox_upload_progress + '" value="0"></progress>');     	 				;
							$(progress_span_selector + ' progress').attr({
								value: response.ongoing_size,
								max: response.total_upload_size,
							});   	 				
						}  	 			 
					} else if ('uploading_to_gdrive' === status) {
						if ( false === PrimeMoverCoreJsObject[refreshid] ) {
							return;
						} 	           	    
						if ('total_upload_size' in response && 'ongoing_size' in response) {
							var progress_span_selector = '#js-multisite_export_progress_span_'+ blog_id;
							$(progress_span_selector).html( prime_mover_js_ajax_renderer.prime_mover_gdrive_upload_progress_text + '..<progress title"' + prime_mover_js_ajax_renderer.prime_mover_gdrive_upload_progress + '" value="0"></progress>');     	 				;
							$(progress_span_selector + ' progress').attr({
								value: response.ongoing_size,
								max: response.total_upload_size,
							});   	 				
						}  	 			 
					} else {
						if ( false === PrimeMoverCoreJsObject[refreshid] ) {
							return;
						} 	           	    
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Ongoing status: ' + status, mode, 'doStatusActions');
						var progress_selector = '#js-multisite_' + mode + '_progress_span_'+ blog_id;
						if ('boot' !== status) {
							$(progress_selector).text(status);   	 				
						}    	 			
					}
				} else if (response.logexist && response.error_msg ) {
					if ( false === PrimeMoverCoreJsObject[refreshid] ) {
						return;
					}	 	    		
					clearTimeout(PrimeMoverCoreJsObject[refreshid]);
					PrimeMoverCoreJsObject[refreshid] = false;
					pulse = false;
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Fatal runtime errors found during progress status check, aborting..', mode, 'doStatusActions');
					PrimeMoverCoreJsObject.displayRunTimeError(blog_id, mode, 'doStatusActions', response);	        	
				} else {
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Status not defined:' + status, mode, 'doStatusActions');
				}   

				return pulse;
			},
			/**
			 * Progress AJAX
			 */
			progressAjax: function(data, mode, blog_id, progress_identifier, refreshid, args) {    		 		
				if (typeof(args) === 'undefined') {
					var args = {};
				}
				$.ajax({
					url: ajaxurl,
					type: 'post',		 			            
					data: data,
					retryLimit : prime_mover_js_ajax_renderer.prime_mover_retry_limit,
					success: function( response ) {
						data.trackercount++; 
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Generic success response received for progress ajax.', mode, 'progressAjax');
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, response, mode, 'progressAjax');
						if (PrimeMoverCoreJsObject.prime_mover_is_js_object(response)) {							
							if ( 'import' === mode ) {
								var status = response.import_status;
							} else {
								var status = response.export_status;
							}	
							var progress_interval = prime_mover_js_ajax_renderer.prime_mover_standard_progress_interval;
							if ('boot' === status) {
								progress_interval = prime_mover_js_ajax_renderer.prime_mover_upload_refresh_interval
							}
							
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Progress identifier is: ' + progress_identifier , mode, 'progressAjax');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Progress identifier status is: ' + status , mode, 'progressAjax');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Progress interval used is: ' + progress_interval , mode, 'progressAjax');
							
							PrimeMoverCoreJsObject[progress_identifier] = status;
							var pulse = true;

							if ('bootup' in response && response.bootup) {
								PrimeMoverCoreJsObject[progress_identifier] = 'boot';
								PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Bootup Response', mode, 'progressAjax');
								PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, args, mode, 'progressAjax');	 	    	 		
								PrimeMoverCoreJsObject.doBootActions(mode, blog_id, args);
							} else {
								pulse = PrimeMoverCoreJsObject.doStatusActions(status, refreshid, mode, response, blog_id, pulse);
							}	 
							if (pulse) {
								PrimeMoverCoreJsObject[refreshid] = setTimeout(function() { PrimeMoverCoreJsObject.progressAjax(data, mode, blog_id, progress_identifier, refreshid); }, progress_interval);	 	    	 		
							}	            		
						} else {							
							data.noresponsecount++;		
							var msg = prime_mover_js_ajax_renderer.prime_mover_progress_error_message;							
							if ('string' === typeof response) {
								msg = msg.replace('{{PROGRESSSERVERERROR}}', response);
								msg = msg.replace('{{BLOGID}}', blog_id);
							}							
							
							clearprogressspans = true;							
							if (data.noresponsecount <= this.retryLimit) {                                
								var pulse = true;
								PrimeMoverCoreJsObject[refreshid] = setTimeout(function() { PrimeMoverCoreJsObject.progressAjax(data, mode, blog_id, progress_identifier, refreshid); }, prime_mover_js_ajax_renderer.prime_mover_standard_progress_interval);	 	                         

							} else if (data.noresponsecount > this.retryLimit ) {

								PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'The NO RESPONSE progress ajax request has reached a retry limit.', mode, 'progressAjax');
								PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Limit try count: ' + data.noresponsecount, mode, 'progressAjax');
								PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Retry limit: ' + this.retryLimit, mode, 'progressAjax');

								PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, mode, true );
								var uploaderror_selector = PrimeMoverCoreJsObject.getGenericFailSelector(blog_id);
								if ( false === PrimeMoverCoreJsObject.dialogIsOpen(uploaderror_selector, true) ) {               			              		
									PrimeMoverCoreJsObject.user_notices( uploaderror_selector, blog_id, mode, clearprogressspans, msg );
								}
								PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
							}						 	    	 			            		
						}
					},
					error : function(xhr, textStatus, errorThrown ) {
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Error detected on ajax track progress request, error thrown is ' + errorThrown , mode, 'progressAjax'); 
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Progress failed request detected, retrying again.', mode, 'progressAjax'); 

						if ( ! errorThrown) {
							errorThrown = prime_mover_js_ajax_renderer.prime_mover_unknown_js_error;
						}
						data.errorcount++;
						var msg = prime_mover_js_ajax_renderer.prime_mover_progress_error_message;
						msg = msg.replace('{{PROGRESSSERVERERROR}}', errorThrown);
						msg = msg.replace('{{BLOGID}}', blog_id);

						clearprogressspans = true;	 
						if (data.errorcount <= this.retryLimit) {

							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'The progress ajax request has been retried.', mode, 'progressAjax');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Current try count: ' + data.errorcount, mode, 'progressAjax');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Retry limit: ' + this.retryLimit, mode, 'progressAjax');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Arguments passed to progress re-sending:', mode, 'progressAjax');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, args, mode, 'progressAjax');
							PrimeMoverCoreJsObject[refreshid] = setTimeout(function() { PrimeMoverCoreJsObject.progressAjax(data, mode, blog_id, progress_identifier, refreshid, args); }, prime_mover_js_ajax_renderer.prime_mover_standard_progress_interval);   	                         

						} else if ( data.errorcount > this.retryLimit ) {

							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'The progress ajax request has reached a retry limit.', mode, 'progressAjax');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Limit try count: ' + data.errorcount, mode, 'progressAjax');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Retry limit: ' + this.retryLimit, mode, 'progressAjax');

							PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, mode, true );
							var uploaderror_selector = PrimeMoverCoreJsObject.getGenericFailSelector(blog_id);
							if ( false === PrimeMoverCoreJsObject.dialogIsOpen(uploaderror_selector, true) ) {               			              		
								PrimeMoverCoreJsObject.user_notices( uploaderror_selector, blog_id, mode, clearprogressspans, msg );
							}
							PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
						}
					} 
				});	  		
			},
			/**
			 * Track import/export progress
			 */
			prime_mover_track_progress: function( mode, blog_id, diffmode, args) {
				if (typeof(mode) === 'undefined') {
					var mode = 'import';
				}
				if (typeof(diffmode) === 'undefined') {
					var diffmode = false;
				} 
				if (typeof(args) === 'undefined') {
					var args = {};
				}
				var refreshid = this.generateUniqueIdentifier(mode, blog_id);
				var progress_identifier = this.generateProgressIdentifier(refreshid, mode);          			

				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Progress tracking requested with the following parameters', mode, 'prime_mover_track_progress');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Blog ID: ' + blog_id, mode, 'prime_mover_track_progress');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Mode: ' + mode, mode, 'prime_mover_track_progress');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Refresh ID: ' + refreshid, mode, 'prime_mover_track_progress');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Progress Identifier: ' + progress_identifier, mode, 'prime_mover_track_progress');

				var data = {
						action: 'prime_mover_monitor_' + mode +  '_progress',
						dataType: 'json',	 
						blog_id : blog_id		   				 			 		    				    					
				};	

				if ( 'import' === mode ) {
					data.prime_mover_import_progress_nonce = prime_mover_js_ajax_renderer.prime_mover_import_progress_nonce;
				} else {    			
					data.prime_mover_export_progress_nonce = prime_mover_js_ajax_renderer.prime_mover_export_progress_nonce;
				}

				data.trackercount = 1;
				data.errorcount = 0;
				data.noresponsecount = 0;
				data.diffmode = diffmode;

				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Progress triggered, starting progress ajax...', mode, 'prime_mover_track_progress'); 
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, args, mode, 'prime_mover_track_progress'); 

				this.progressAjax(data, mode, blog_id, progress_identifier, refreshid, args);        
			},
			/**
			 * Do a clean shutdown of export and import processes AFTER FINAL RESULT is delivered to the client browser
			 */
			shutdown_process: function(mode, process_id, blog_id) {
				var data = {
						action: 'prime_mover_shutdown_' + mode +  '_process',
						dataType: 'json',	 
						process_id : process_id,
						blog_id: blog_id
				};

				if ( 'import' === mode ) {
					data.prime_mover_import_shutdown_nonce = prime_mover_js_ajax_renderer.prime_mover_import_shutdown_nonce;
				} else {    			
					data.prime_mover_export_shutdown_nonce = prime_mover_js_ajax_renderer.prime_mover_export_shutdown_nonce;
				}
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, mode + ' shutdown started ajax request for' + process_id, mode, 'shutdown_process'); 
				$.ajax({
					url: ajaxurl,
					type: 'post',		 			            
					data: data,
					retryLimit : prime_mover_js_ajax_renderer.prime_mover_retry_limit,
					tryCount : 0,
					success: function( response ) {
						if ( 'status' in response ) { 
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Response received: ' + response.status, mode, 'shutdown_process');	            		
						} else {
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Empty response received', mode, 'shutdown_process');	
						}	            	
					},
					error : function(xhr, textStatus, errorThrown ) {
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Error dected on shutdown request, error thrown is ' + errorThrown , mode, 'shutdown_process'); 
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Retrying the shutdown request...', mode, 'shutdown_process'); 

						var ajax_handler_object = this;	
						PrimeMoverCoreJsObject.doErrorHandling( blog_id, mode, ajax_handler_object, null, true, 'shutdown_process');			            	
					} 
				});	 		
			},
			/**
			 * Import result handler
			 */
			import_result_handler: function(blog_id, response) {
				var myObj_prime_mover_import_status = response;			 	    	 				 	    	 	
				if ( true  === myObj_prime_mover_import_status.status ) {							 	    		
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Import success detected. Clearing intervals.', 'import', 'import_result_handler');
					PrimeMoverCoreJsObject.import_success_handler( blog_id, myObj_prime_mover_import_status );
					PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, false ); 	    		

					if ('process_id' in myObj_prime_mover_import_status) {
						var process_id = myObj_prime_mover_import_status.process_id;
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Import shutdown requested to server for process id' + process_id, 'import', 'import_result_handler');  	    			    
						PrimeMoverCoreJsObject.shutdown_process('import', process_id, blog_id);  	    			
					} 	    		
				} else if ( true === myObj_prime_mover_import_status.diff_detected ) {
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Diff processed.', 'import', 'import_result_handler');
					PrimeMoverCoreJsObject.shutdown_process('import', myObj_prime_mover_import_status.process_id, blog_id);
					var import_diff_selector = "#js-prime-mover-import-diff-confirm-" + blog_id;
					$( import_diff_selector ).html( myObj_prime_mover_import_status.diff );

					var data = $(import_diff_selector).data(); 	            
					data.myObj_prime_mover_import_status = myObj_prime_mover_import_status; 	            
					$( import_diff_selector ).dialog({
						resizable: false,
						height: 600,
						width: "auto",
						maxWidth: 600,
						dialogClass: 'prime-mover-user-dialog',
						modal: true,
						fluid: true,  
						buttons: [
							{
								text: prime_mover_js_ajax_renderer.yes_button,	
								"class": 'button-primary',
								click: function() {
									$( this ).dialog( "close" );
									$( import_diff_selector ).text('');
									PrimeMoverCoreJsObject.user_approves_diff( myObj_prime_mover_import_status, blog_id );			           	  
								}
							},
							{
								text: prime_mover_js_ajax_renderer.no_button,					 
								click: function() {
									$( this ).dialog( "close" );
									PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
									PrimeMoverCoreJsObject.doAfterClose( import_diff_selector, myObj_prime_mover_import_status, blog_id );
								}
							},				    
							],	 		        
							create: function() {
								$(this).closest('div.ui-dialog')
								.find('.ui-dialog-titlebar-close')
								.click(function(e) {	
									myObj_prime_mover_import_status = data.myObj_prime_mover_import_status;
									PrimeMoverCoreJsObject.doAfterClose( import_diff_selector, myObj_prime_mover_import_status, blog_id );
									PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
									e.preventDefault();
								});
							}
					});
					PrimeMoverCoreJsObject.handle_responsive_dialog();	

				} else {								 	    		
					$('#js-multisite_import_span_'+ blog_id ).html( myObj_prime_mover_import_status.import_not_successful );
					$('#js-multisite_import_span_'+ blog_id ).css( 'color', 'red' );

					PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id );
					$('#js-multisite_import_span_'+ blog_id ).parent().addClass('notice notice-error');
					$('#js-multisite_import_span_'+ blog_id ).css( 'width', '100%' );
					$('#js-multisite_import_span_'+ blog_id ).css( 'float', 'none' );
					
					var process_id = myObj_prime_mover_import_status.process_id;
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Error occurred and Import shutdown requested to server for process id' + process_id, 'import', 'import_result_handler');  	    		

					PrimeMoverCoreJsObject.shutdown_process('import', process_id, blog_id); 
				}    		
			},
			/**
			 * Download success handler
			 */
			download_success_handler: function(blog_id, response) { 				
				if (response.download_status) {		 	    	

					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Response download status defined, value is ' + response.download_status, 'import', 'download_success_handler');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Response package encrypted status, value is ' + response.encrypted, 'import', 'download_success_handler');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Response package description type, value is ' + response.package_description, 'import', 'download_success_handler');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Response message, value is ' + response.message, 'import', 'download_success_handler');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Response site title value is ' + response.site_title, 'import', 'download_success_handler');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Response download URL value is ' + response.source, 'import', 'download_success_handler');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Response download package size is ' + response.package_size, 'import', 'download_success_handler');

					var wrongselector = '#js-prime-mover-wrong-importedsite-' + blog_id;
					if (response.package_description) {
						if (response.encrypted && 'can_decrypt' in response && 'decryption_error' in response && ! response.can_decrypt) {
							PrimeMoverCoreJsObject.user_notices(wrongselector, blog_id, 'import', true, response.decryption_error);
							PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);	 					
						} else if (response.encrypted_media && 'can_decrypt_media' in response && ! response.can_decrypt_media) {
							PrimeMoverCoreJsObject.user_notices(wrongselector, blog_id, 'import', true, prime_mover_js_ajax_renderer.prime_mover_media_decryption_error);
							PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);	 					
						} else {	 					
							/** response.package_description is an array */
							PrimeMoverCoreJsObject.generalImportWarningDialog(blog_id, '', true, response.message, 'restore_from_remote_url',
									response.encrypted, response.package_description, response.site_title, response.source, response.package_size, response.encrypted_media); 		 					
						} 				
					} else {			 		
						var error_message = prime_mover_js_ajax_renderer.prime_mover_invalid_package;
						if ('decryption_error' in response) {
							error_message = response.decryption_error;
						}
						PrimeMoverCoreJsObject.user_notices(wrongselector, blog_id, 'import', true, error_message);
						PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
					}				
				} else {
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Response download status is not defined.', 'import', 'download_success_handler');
					PrimeMoverCoreJsObject.import_failed_handler( blog_id, response.message, false);	 	    		

					PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id );
					$('#js-multisite_import_span_'+ blog_id ).addClass('notice notice-error prime-mover-corrupt-package-error');
					PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
				}	   		
				PrimeMoverCoreJsObject.shutdown_process('import', response.process_id, blog_id);
			},
			/**
			 * Export success handler
			 */
			export_success_handler: function(blog_id, response) {    		
				var myObj_prime_mover_export_status = response;	 	    	 	
				if ( true  === myObj_prime_mover_export_status.status ) {	
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Export final success clearing intervals.', 'export', 'export_success_handler');
					PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, 'export' );
					$('#js-multisite_export_span_'+ blog_id ).html('');	 
					$('#js-prime_mover_export_progress_span_p_'+ blog_id ).addClass('notice notice-success');
					if ( 'download_link' in myObj_prime_mover_export_status && 'generated_filename' in myObj_prime_mover_export_status) { 
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Streaming download link.', 'export', 'export_success_handler');
						
						var diag_selector_export = "#js-prime-mover-export-done-dialog-" + blog_id;
						var text_selector = ".js-prime-move-download-button-hero-" + blog_id;

						if ('prime_mover_export_downloaded' in myObj_prime_mover_export_status) {
							$('#js-multisite_export_span_'+ blog_id ).html(myObj_prime_mover_export_status.prime_mover_export_downloaded);
							PrimeMoverCoreJsObject.user_notices(diag_selector_export, blog_id, '', false, myObj_prime_mover_export_status.download_link, '', false, text_selector); 
						}
						
					} else {		 	    	
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Showing download link.', 'export', 'export_success_handler');
						$('#js-multisite_export_span_'+ blog_id ).html("<p style='margin-top:10px;'>" + myObj_prime_mover_export_status.message + "</p>");
						PrimeMoverCoreJsObject.showClipBoardButtonIfSupported(blog_id, myObj_prime_mover_export_status.restore_url);
					}
				} else if ( 'export_not_successful' in myObj_prime_mover_export_status ) {
					$('#js-prime_mover_export_progress_span_p_'+ blog_id ).addClass('notice notice-error');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Export not successful received:' + myObj_prime_mover_export_status.export_not_successful, 'export', 'export_success_handler');
					$('#js-multisite_export_span_'+ blog_id ).html( myObj_prime_mover_export_status.export_not_successful );
					$('#js-multisite_export_span_'+ blog_id ).css( 'color', 'red' );		 	    		
					PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, 'export'); 
				}
				if ('process_id' in myObj_prime_mover_export_status) {
					var process_id = myObj_prime_mover_export_status.process_id;
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Export shutdown requested to server for process id' + process_id, 'export', 'export_success_handler');  
					PrimeMoverCoreJsObject.shutdown_process('export', process_id, blog_id);	    		
				}
			},
			/**
			 * Set export location if overriden by extension
			 */
			prime_mover_set_export_location: function( blog_id ) {
				var export_location = 'default';
				if( ! $(".js-prime_mover_target_export_location_class").length){    			
					return export_location;    			
				}
				var export_location_selector = 'input#js-prime-mover-targetexportloc-' + blog_id;
				if ($(export_location_selector).is(':checked')) {
					export_location = 'export_directory';
				}
				return export_location;    		
			},
			/**
			 * Set encryption options if set
			 */
			prime_mover_set_encryption_option: function( blog_id ) {
				var encryption = false;
				if( ! $(".js-prime-mover-encryptiondb_class").length){    			
					return encryption;    			
				}
				var encryption_mode_selector = 'input#js-prime-mover-encryptiondb-' + blog_id;
				if ($(encryption_mode_selector).is(':checked')) {
					encryption = true;
				}
				return encryption;    		
			},
			/**
			 * Set user export options if set
			 */
			prime_mover_set_userexport_option: function(blog_id) {
				var userexport = false;
				if( ! $(".js-prime-mover-userexport_class").length){    			
					return userexport;    			
				}
				
				var userexport_mode_selector = 'input#js-prime-mover-userexport-' + blog_id;
				if ($(userexport_mode_selector).is(':checked')) {
					userexport = true;
				}
				
				return userexport;    		
			},
			/**
			 * Set dropbox export option if set
			 */
			prime_mover_set_dropbox_option: function(blog_id) {
				var dropbox_upload = false;
				if( ! $(".js-prime_mover_dropbox_class").length){    			
					return dropbox_upload;    			
				}
				var dropbox_selector = 'input#js-prime-mover-savetodropbox-' + blog_id;
				if ($(dropbox_selector).is(':checked')) {
					dropbox_upload = true;
				}
				return dropbox_upload;    		
			}, 
			/**
			 * Set force UTF8 option if set
			 */
			prime_mover_set_forceutf8_option: function(blog_id) {
				var force_utf8 = false;
				if( ! $(".js-prime_mover_forceutf8dump_class").length){    			
					return force_utf8;    			
				}
				var forceutf8_selector = 'input#js-prime-mover-forceutf8dump-' + blog_id;
				if ($(forceutf8_selector).is(':checked')) {
					force_utf8 = true;
				}
				return force_utf8;    		
			},
			/**
			 * Set GDrive export option if set
			 */
			prime_mover_set_gdrive_option: function(blog_id) {
				var gdrive_upload = false;
				if( ! $(".js-prime_mover_gdrive_class").length){    			
					return gdrive_upload;    			
				}
				var gdrive_selector = 'input#js-prime-mover-savetogdrive-' + blog_id;
				if ($(gdrive_selector).is(':checked')) {
					gdrive_upload = true;
				}
				return gdrive_upload; 				
			},
			/**
			 * Set export id based on export type
			 * @mainsitesupport_affected
			 */
			prime_mover_get_targetexport_id: function(blog_id) {
				var multisite_export = false;
				var targetid = 0;
				var export_type = this.getExportType(blog_id);	
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Analyzed export type: ' + export_type, 'export', 'prime_mover_get_targetexport_id');
				
				if ('single-site-export' === export_type) {
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Single site export triggered', 'export', 'prime_mover_get_targetexport_id');
					return 1;
				}				
				if ('multisite-export' === export_type) {
					multisite_export = true;
				}
				if ('multisitebackup-export' === export_type) {
					return blog_id;
				} else if (multisite_export) {
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Multisite export triggered', 'export', 'prime_mover_get_targetexport_id');
					var value = $("#js-prime-mover-export-targetid-" + blog_id).val();
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Multisite export retrieved target ID: ' + value, 'export', 'prime_mover_get_targetexport_id');
					value = value.trim();  
					if (value) 	{
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Multisite export target ID: ' + value, 'export', 'prime_mover_get_targetexport_id');
					    return value;
					} 		
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Multisite export target ID: ' + targetid, 'export', 'prime_mover_get_targetexport_id');
					return targetid;   					
				} else {	
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Not multisite export target ID: ' + targetid, 'export', 'prime_mover_get_targetexport_id');
					return targetid;
				} 					
			},  
			/**
			 * Overall progress circle indicator markup
			 */
			returnProgressCircleMarkup: function() {
				return '<div class="progress-circle" title="' + prime_mover_js_ajax_renderer.prime_mover_overall_percent_progress  + '"><span>0%</span><div class="left-half-clipper"><div class="first50-bar"></div><div class="value-bar"></div></div></div>';
			},
			/**
			 * Get export type
			 */
			getExportType: function(blog_id) {
				var export_type = $('input[name=prime-mover-export-type-' + blog_id + ']:checked').val();
				return export_type;
			},
			/**
			 * Export process
			 * @mainsitesupport_affected
			 */
			prime_mover_exporter: function ( export_option, blog_id ) {
				
				var html_ajax_loader= this.returnProgressCircleMarkup();
				var export_location = this.prime_mover_set_export_location(blog_id);
				var encryption_on = this.prime_mover_set_encryption_option(blog_id);
				
				var user_export = this.prime_mover_set_userexport_option(blog_id);
				var dropbox_upload = this.prime_mover_set_dropbox_option(blog_id);
				var forceutf8 = this.prime_mover_set_forceutf8_option(blog_id);
				
				var gdrive_upload = this.prime_mover_set_gdrive_option(blog_id);
				var targetexport_id = this.prime_mover_get_targetexport_id(blog_id);					
				var export_type = this.getExportType(blog_id);
				
				$('#js-multisite_export_span_'+ blog_id ).html(html_ajax_loader );					
				var data = {
						action: 'prime_mover_process_export',
						dataType: 'json',	   				
						prime_mover_export_nonce: prime_mover_js_ajax_renderer.prime_mover_export_nonce,
						multisite_blogid_to_export: blog_id,
						multisite_export_options: export_option,
						multisite_export_location: export_location,
						prime_mover_encrypt_db: encryption_on,
						prime_mover_userexport_setting: user_export,
						prime_mover_dropbox_upload: dropbox_upload,
						prime_mover_force_utf8: forceutf8,
						prime_mover_gdrive_upload: gdrive_upload,
						prime_mover_export_targetid: targetexport_id,
						prime_mover_export_type: export_type
				};		    	
				PrimeMoverCoreJsObject.prime_mover_track_progress('export', blog_id, false, data);   		    		
			},
			/**
			 * Show clipboard button if supported
			 */
			showClipBoardButtonIfSupported: function(blog_id, restore_url) {
				var clipboard_el = "#js-prime-mover-copy-url-clipboard-" + blog_id;    		
				if ( $(clipboard_el).length ) {    			
					$(clipboard_el).show();
					$(clipboard_el).attr('data-clipboard-text',restore_url);
				}
			},
			/**
			 * Get Blog ID from zip folder name
			 */
			getBlogIDfromFolderName: function (import_folder) {
				if ( ! import_folder ) {
					return false;
				}
				var notrailing = import_folder.replace(/\/$/, "");
				var splitted = notrailing.split("_");
				var lastItem = splitted.pop();
				return Number(lastItem);    		
			},
			/**
			 * Log Processing Error Analysis for debugging purposes
			 * Enable this constant in wp-config.php
			 * define('PRIME_MOVER_JS_ERROR_ANALYSIS', true);
			 * Clear browser cache then retry the upload
			 * Log is viewable in browser console
			 */
			logProcessingErrorAnalysis: function (blog_id, text, mode, source) {
				if (typeof(source) === 'undefined' || ! source) {
					source = 'not defined';
				}
				if (prime_mover_js_ajax_renderer.prime_mover_upload_error_analysis) {
					if ( this.prime_mover_is_js_object(text)) {
						console.log(mode + ' log for BlogID ' + blog_id + ' Object result Functional Source: ' + source);
						console.log(text);
					} else {
						console.log(mode + ' log for BlogID ' + blog_id + ': ' + text + ' Functional Source: ' + source);
					}    			
				}
			},
			/**
			 * Get error selectors
			 */
			getErrorSelectors: function(mode) {
				var selector = '#js-multisite_import_span_';
				if ( 'export' === mode ) {
					selector = '#js-multisite_export_span_';		    	
				}
				return selector;
			},
			/**
			 * Display Runtime errors
			 */
			displayRunTimeError: function(blog_id, handling_mode, source, myObj_errorlog) {
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Error handling found error log file.', handling_mode, source); 
				var selector = this.getErrorSelectors(handling_mode);
				$( selector + blog_id ).html( myObj_errorlog.error_msg );
				$( selector + blog_id ).css( 'color', 'red' );
				$( selector + blog_id ).parent().addClass('notice notice-error');
				if ( 'import' === handling_mode ) {
					PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id );
				}
				if ( 'export' === handling_mode ) {
					PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, 'export' );
				}	 	    
				$('#js-multisite_import_span_'+ blog_id ).css( 'float', 'none' );
				$('#js-multisite_import_span_'+ blog_id ).css( 'width', '100%' ); 
				PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
			},
			/**
			 * Error handling for Multisite migration AJAX processes
			 */
			doErrorHandling: function( blog_id, mode, ajax_handler_object, errorselector, retry, source ) {
				if (typeof(ajax_handler_object) === 'undefined') {
					ajax_handler_object = null;
				}
				if (typeof(errorselector) === 'undefined') {
					errorselector = '';
				}
				if (typeof(retry ) === 'undefined') {
					retry = true;
				}
				if (typeof(source ) === 'undefined') {
					source = '';
				}
				var handling_mode = 'import';
				var data = {
						action: 'prime_mover_check_if_error_log_exist',
						dataType: 'json',			   				
						prime_mover_errorlog_nonce: prime_mover_js_ajax_renderer.prime_mover_errorlog_nonce,
						error_blog_id: blog_id
				};				    
				var selector = PrimeMoverCoreJsObject.getErrorSelectors(mode);
				if ('export' === mode ) {
					handling_mode = 'export';
				}

				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Starting doErrorHandling before ajax post.', handling_mode, source); 	 		
				$.ajax({
					url: ajaxurl,
					type: 'post',			 			            
					data: data,	
					retryLimit : prime_mover_js_ajax_renderer.prime_mover_retry_limit,
					tryCount : 0,
					success: function( response ) {		 	    	 		 	    	 	
						var myObj_errorlog = response;
						if ( myObj_errorlog.logexist && myObj_errorlog.error_msg ) {
							PrimeMoverCoreJsObject.displayRunTimeError(blog_id, handling_mode, source, myObj_errorlog);
						} else if (ajax_handler_object) {	
							if (retry) {
								PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'No error log file, we are retrying the ajax ' + handling_mode + ' request.', handling_mode, source);	
								PrimeMoverCoreJsObject.retryAjaxHandler(ajax_handler_object, handling_mode, blog_id);	 	    	 			
							} 	    	 	  	    	 		
						} else if (errorselector) {	 	    	 		
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'No error log file and ajax handler object not set OR retry is set to false, we are displaying an error notice for ' + handling_mode + ' request.', handling_mode, source);
							PrimeMoverCoreJsObject.user_notices( errorselector + blog_id, blog_id, 'import', true );
						}			                
					},
					error : function(xhr, textStatus, errorThrown ) {
						this.tryCount++;
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, handling_mode + ' retry mode is' + retry, handling_mode, source);
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'The error handling ajax returns an error.', handling_mode, source);
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'The error returned is ' + errorThrown, handling_mode, source);            	
						if (retry) {
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'We are now retrying the original ' + handling_mode + ' request', handling_mode, source);
							PrimeMoverCoreJsObject.retryAjaxHandler(ajax_handler_object, handling_mode, blog_id, source);	            		
						}

					} 
				});	        
			},
			/**
			 * Retry helper
			 */
			retryHelper: function(ajax_handler_object) {
				$.ajax(ajax_handler_object);    		
			},
			/**
			 * Retry ajax handler for error handling
			 */
			retryAjaxHandler: function(ajax_handler_object, mode, blog_id, source) {
				if (typeof( blog_id ) === 'undefined') {
					return;
				}
				if (typeof( source ) === 'undefined') {
					source = ''
				}
				if ( ! ajax_handler_object ) {
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Ajax handler object is not defined.', mode, source);
					return;
				}
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Retry ajax handler method called.', mode, source);
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Set retry limit is ' + ajax_handler_object.retryLimit, mode, source);
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Current retry count is ' + ajax_handler_object.tryCount, mode, source);

				if ('import' === mode) {
					if (ajax_handler_object.tryCount <= ajax_handler_object.retryLimit) {
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'The original ajax request has been retried.', mode, source);
						setTimeout(function() { PrimeMoverCoreJsObject.retryHelper(ajax_handler_object); }, prime_mover_js_ajax_renderer.prime_mover_retry_request_resending);            		
						return;
					} else if ( ajax_handler_object.tryCount > ajax_handler_object.retryLimit ) {
						PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, mode, true );
						var uploaderror_selector = PrimeMoverCoreJsObject.getGenericFailSelector(blog_id);
						if ( false === PrimeMoverCoreJsObject.dialogIsOpen(uploaderror_selector, true) ) {               			
							var text = '';
							if ('errorReturned' in ajax_handler_object) {
								text = ajax_handler_object.errorReturned;
							}
							var clearprogressspans = false;
							var blogid = 0;
							var notice_mode = '';
							PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
							if ('clearprogressspans' in ajax_handler_object) {
								clearprogressspans = ajax_handler_object.clearprogressspans;
								blogid = blog_id;
								notice_mode = mode;
							}             		
							PrimeMoverCoreJsObject.user_notices( uploaderror_selector, blogid, notice_mode, clearprogressspans, text );
						}
					}   			
				}   		
			},
			/**
			 * Get generic fail selector
			 */
			getGenericFailSelector: function(blog_id) {
				return '#js-prime-mover-import-generic-fail-' + blog_id;
			},
			/**
			 * Helper dialog for outputting user notices
			 */
			user_notices: function ( selector, blog_id, mode, clearprogress, text, custom_dialog_class, reload, text_selector, heading_title) {
				if (typeof( blog_id ) === 'undefined') {
					blog_id = 0;
				}
				if (typeof(mode) === 'undefined') {
					mode = '';
				}    		
				if (typeof(clearprogress) === 'undefined') {
					clearprogress = false;
				}
				if (typeof(text) === 'undefined') {
					text = '';
				}
				if (typeof(custom_dialog_class) === 'undefined' || ! custom_dialog_class) {
					custom_dialog_class = 'prime-mover-user-dialog';
				}				
				if (typeof(reload) === 'undefined') {
					reload = false;
				}
				if (typeof(text_selector) === 'undefined') {					
					text_selector = selector + " p";
				} 
				if (typeof(heading_title) === 'undefined') {					
					heading_title = '';
				} 
				
				var data = $(selector).data();
				var download_selector_button = '.js-prime-move-download-button-hero-' + blog_id + ' a';
				data.blog_id = blog_id;
				data.mode = mode;
				data.clearprogress = clearprogress;
				if (text) {
					$(text_selector).html(text);
				}			
				
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'We are displaying a user notice with following args.', mode, 'user_notices');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Selector: ' + selector, mode, 'user_notices');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Clear progress: ' + clearprogress, mode, 'user_notices');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Text: ' + text, mode, 'user_notices');
                
				var dialog_params = {
						resizable: false,
						height: "auto",	            
						minWidth: 320,
						maxWidth: 600,
						dialogClass: custom_dialog_class,
						modal: true,
						title: heading_title,
						fluid: true,			
						buttons: [
							{
								text: prime_mover_js_ajax_renderer.ok_button,	
								"class": 'button-primary',
								click: function() {
									var dialog_object = this;				    	  
									$(dialog_object).dialog( "close" );		            	  
									PrimeMoverCoreJsObject.runThisWhenUserNoticeIsClosed(dialog_object, selector);
								}
							}				    
							],
							create: function() {
								var dialog_object = this;
								$(dialog_object).closest('div.ui-dialog')
								.find('.ui-dialog-titlebar-close')
								.click(function(e) {						     
									PrimeMoverCoreJsObject.runThisWhenUserNoticeIsClosed(dialog_object, selector);						  
								});
							},
							open: function( event, ui ) {							
								$('.ui-dialog a').blur();				
								if ($(download_selector_button).length) {
									$(download_selector_button).fadeTo(750, 0.6).fadeTo(750, 1).fadeTo(750, 0.6).fadeTo(750, 1);
								}												
							},
							close: function(event, ui) {							
								if (reload && 'single-site' === prime_mover_js_ajax_renderer.prime_mover_environment) {
								    location.reload();		
								}
						          
						    }
				};
				
				if (!heading_title) {
					delete dialog_params.title;
				}
				
				$( selector ).dialog(dialog_params);
				PrimeMoverCoreJsObject.handle_responsive_dialog();   		
			},
			/**
			 * Run this user notice when closed
			 */
			runThisWhenUserNoticeIsClosed: function(dialog_object, selector) {
				var blog_id = $(dialog_object).data('blog_id');
				var mode = $(dialog_object).data('mode');	
				var clearprogress = $(dialog_object).data('clearprogress');

				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'A user notice is closed with the following parameters:', mode, 'user_notices');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Multisite ID: ' + blog_id, mode, 'user_notices');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Clear progress: ' + clearprogress, mode, 'user_notices');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Mode: ' + mode, mode, 'user_notices');

				if (blog_id && clearprogress && mode) {
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'A clear progress has been successfully requested after closing notice.', mode, 'user_notices');
					PrimeMoverCoreJsObject.cleanProgressSpans(blog_id, mode);			    		  
				}    		
				PrimeMoverCoreJsObject.initializeDialogMonitor(selector, true);
			},
			/**
			 * Initiate spinner
			 */
			initiateSpinner: function(blog_id, spinner_mode) {
				if (typeof( spinner_mode ) === 'undefined') {
					spinner_mode = 'upload';
				}
				var spinner_text = prime_mover_js_ajax_renderer.prime_mover_spinner_upload_text;
				if ('download' === spinner_mode ) {
					spinner_text = prime_mover_js_ajax_renderer.prime_mover_spinner_download_text;
				}
				if ('zip_analysis' === spinner_mode) {
					spinner_text = prime_mover_js_ajax_renderer.prime_mover_spinner_zipanalysis_text;
				}
								
				var html_ajax_loader = PrimeMoverCoreJsObject.returnProgressCircleMarkup();
				$('#js-multisite_import_span_'+ blog_id ).html( html_ajax_loader );
				$('#js-multisite_import_progress_span_'+ blog_id ).html( spinner_text + '..<progress title="' + prime_mover_js_ajax_renderer.prime_mover_file_transfer_progress + '" value="0"></progress>');   		
			},
			/**
			 * User confirms to proceed to the import
			 */
			user_confirms_import_to_proceed: function( blog_id, the_import_package ) {
				PrimeMoverCoreJsObject.processFile( the_import_package, blog_id);   		
			},
			/**
			 * Validate if server upload limits insufficient
			 * file_size value should already be valid at this point
			 */
			validate_if_server_upload_limits_insufficient: function ( file_size ) { 
				$insufficient	= false;  
				var package_size	= file_size;
				var server_upload_max_size	= prime_mover_js_ajax_renderer.prime_mover_upload_max_size;
				if ( package_size > server_upload_max_size ) {
					$insufficient	= true;
				}
				return $insufficient;
			},
			/**
			 * Compute recommended upload_max_size and post_max_size based on given imported package
			 * file_size value should already be valid at this point
			 */
			compute_recommended_upload_parameters: function ( file_size ) {    			  		
				var package_size	= file_size;
				var upload_max_size_recommended	= Math.ceil( package_size + package_size * 0.10 );

				return upload_max_size_recommended;
			},
			/**
			 * Import success handler
			 */
			import_success_handler: function ( blog_id, myObj_prime_mover_import_status ) {  
				if (typeof(myObj_prime_mover_import_status) === 'undefined') {
					myObj_prime_mover_import_status = null;
				}
				var checked_success = prime_mover_js_ajax_renderer.prime_mover_complete_import_png;
				var completion_text = '';
				var text_selector = "#js-prime-mover-import-done-dialog-" + blog_id + " h3";
				if ( myObj_prime_mover_import_status && this.prime_mover_is_js_object( myObj_prime_mover_import_status ) ) {
					checked_success = myObj_prime_mover_import_status.import_successful;
					completion_text = myObj_prime_mover_import_status.completion_text;
				}
				
				if ((parseInt( blog_id ) > 0 )) {
					$('#js-prime_mover_import_progress_span_p_'+ blog_id ).addClass('notice notice-success');
					$('#js-multisite_import_span_'+ blog_id ).html( '' );	
					$('#js-multisite_import_span_'+ blog_id ).html(checked_success); 
					$('#js-multisite_import_progress_span_'+ blog_id ).html( prime_mover_js_ajax_renderer.prime_mover_restore_done ); 
					
					PrimeMoverCoreJsObject.user_notices( "#js-prime-mover-import-done-dialog-" + blog_id, blog_id, '', false, completion_text, 'prime-mover-success-import-dialog', true, text_selector); 
				}            	
			},
			/**
			 * Import failure handler
			 */
			import_failed_handler: function ( blog_id, myObj_prime_mover_import_status, color_it_red ) { 
				if (typeof(color_it_red) === 'undefined') {
					color_it_red = true;
				}
				if ( ! blog_id ) {
					return;
				}
				if ( this.prime_mover_is_js_object( myObj_prime_mover_import_status ) ) {								 	    		
					$('#js-multisite_import_span_'+ blog_id ).html( myObj_prime_mover_import_status.import_not_successful ); 	    			          		
				} else {        		
					$('#js-multisite_import_span_'+ blog_id ).html( myObj_prime_mover_import_status );
				}        
				if (color_it_red) {
					$('#js-multisite_import_span_'+ blog_id ).css( 'color', 'red' );
				}				
			},
			/**
			 * Clean import and export progress pans
			 */
			cleanProgressSpans: function(blog_id, mode) {
				if (typeof(mode) === 'undefined') {
					mode = 'import';
				}
				var span_selector = '#js-prime_mover_' + mode + '_progress_span_p_' + blog_id + ' span';
				$(span_selector).html('');
				$(span_selector).removeAttr('style');           
			},
			/**
			 * Clear progress text
			 */
			stop_interval_clear_progress_text: function ( blog_id, cleartext, mode, clear_spinner ) {
				if (typeof( cleartext ) === 'undefined') {
					cleartext = true;
				}
				if (typeof( mode ) === 'undefined') {
					mode = 'import';
				}
				if (typeof( clear_spinner ) === 'undefined') {
					clear_spinner = false;
				}
				var refreshid = this.generateUniqueIdentifier( mode, blog_id );
				if ( parseInt( blog_id ) > 0 ) {
					if ( cleartext ) {
						$('#js-multisite_' + mode + '_progress_span_'+ blog_id ).text('');
					}	   
					if ( this.isUniqueIdentifierSet(refreshid) ) {
						clearTimeout(PrimeMoverCoreJsObject[refreshid]); 
						PrimeMoverCoreJsObject[refreshid] = false;
					}
					if ( clear_spinner) {
						$('#js-multisite_' + mode + '_span_'+ blog_id ).text('');
					}
				}          	
			},
			/**
			 * is object
			 */
			prime_mover_is_js_object: function ( obj ) {
				return obj === Object(obj);
			},
			/**
			 * Slice package into 1MB size
			 */
			slice: function( file, start, end ) {
				var slice = file.mozSlice ? file.mozSlice :
					file.webkitSlice ? file.webkitSlice :
						file.slice ? file.slice : noop;

				return slice.bind(file)(start, end);       	
			},
			/**
			 * Noop
			 */
			noop: function() {

			},

			/**
			 * Compute canonical total chunks
			 */
			compute_canonical_total_chunks: function ( size, sliceSize ) {

				var chunks = size/sliceSize;
				chunks = Math.ceil( chunks );

				return chunks;        	
			},        
			/**
			 * Process file uploading by batches
			 */
			processFile: function ( file, blog_id) {    	

				var size = file.size;

				var uploadspeed = PrimeMoverCoreJsObject.measuredUploadSpeed(blog_id);	      
				var sliceSize = this.calculateChunkSizeBasedOnUploadSpeed(uploadspeed);  

				var start = 0;
				var chunks = this.compute_canonical_total_chunks( size, sliceSize );
				var uploadId = this.generateUploadId(blog_id);
				PrimeMoverCoreJsObject[uploadId] = {};

				var uploadrefreshid = this.generateUniqueIdentifier('upload', blog_id);
				PrimeMoverCoreJsObject[uploadrefreshid] = setTimeout(loop, prime_mover_js_ajax_renderer.prime_mover_upload_refresh_interval);
				var slice_part = 1;
				function loop() {

					var end = start + sliceSize;

					if (size - end < 0) {
						end = size;
					}

					var s = PrimeMoverCoreJsObject.slice(file, start, end );
					PrimeMoverCoreJsObject.outputProcessFileDebugLog( start, end, slice_part, chunks, sliceSize, size, uploadspeed );

					PrimeMoverCoreJsObject[uploadId][slice_part] = {slice_start:start, slice_end:end};    
					PrimeMoverCoreJsObject.send(s, start, end, slice_part, chunks, blog_id, file, sliceSize, uploadrefreshid );

					slice_part++;

					if (end < size) {
						start += sliceSize;   
						PrimeMoverCoreJsObject[uploadrefreshid] = setTimeout(loop, prime_mover_js_ajax_renderer.prime_mover_upload_refresh_interval);
					}
				}
			},
			/**
			 * Process file debug logs
			 */
			outputProcessFileDebugLog: function( start, end, slice_part, chunks, sliceSize, size, uploadspeed ) { 
				if ( prime_mover_js_ajax_renderer.prime_mover_debug_uploads ) {
					console.log( '******************************' );
					console.log( 'start: ' + start );
					console.log( 'end: ' + end );
					console.log( 'chunk: ' + slice_part );
					console.log( 'chunks: ' + chunks );
					console.log( 'sliceSize: ' + sliceSize );
					console.log( 'size: ' + size );
					console.log( 'upload: ' + uploadspeed);
					console.log( '******************************' );        		
				}    	    
			},
			/**
			 * Generate upload ID
			 */
			generateUploadId: function(blog_id) {
				var uploadId = 'uploadID_' + blog_id;
				return uploadId;        	
			},
			/**
			 * Sends chunks of package to server
			 */
			send: function( piece, start, end, chunk, chunks, blog_id, file, sliceSize, uploadrefreshid, missing_chunk_to_fix, resume_parts_index, resume_filepath, resume_chunks) {

				if (typeof( missing_chunk_to_fix ) === 'undefined') {
					missing_chunk_to_fix = 0;
				}

				if (typeof( resume_parts_index ) === 'undefined') {
					resume_parts_index = 0;
				}
				
				if (typeof( resume_filepath ) === 'undefined') {
					resume_filepath = '';
				}

				if (typeof( resume_chunks ) === 'undefined') {
					resume_chunks = 0;
				}
				
				var ajaxData = new FormData();
				var filename = file.name;

				ajaxData.set( 'action', 'prime_mover_process_uploads' );
				ajaxData.set( 'prime_mover_uploads_nonce', prime_mover_js_ajax_renderer.prime_mover_uploads_nonce );
				ajaxData.set( 'multisite_blogid_to_import', blog_id );	
				ajaxData.set( 'start', start );
				ajaxData.set( 'end', end );
				
				if ( ! resume_parts_index ) {
					ajaxData.set( 'file', piece, filename );				
				}
					
				ajaxData.set( 'chunk', chunk );
				ajaxData.set( 'chunks', chunks );
				ajaxData.set( 'missing_chunk_to_fix', missing_chunk_to_fix );
				ajaxData.set( 'resume_parts_index', resume_parts_index );
				ajaxData.set( 'resume_filepath', resume_filepath );
				ajaxData.set( 'resume_chunks', resume_chunks);
				
				jQuery.ajax({
					url: ajaxurl,
					type: 'post',
					data: ajaxData,
					cache: false,	
					contentType: false, 
					processData: false,
					tryCount : 0,	
					errorReturned: '',
					clearprogressspans: true,
					retryLimit : prime_mover_js_ajax_renderer.prime_mover_retry_limit,
					success: function( response ) {					            	
						var myObj_prime_mover_upload_status = response;		
						if ('assembled' in myObj_prime_mover_upload_status && true === myObj_prime_mover_upload_status.assembled) {							
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Stopping upload intervals for ID: ' + uploadrefreshid, 'import', 'send');
							if (PrimeMoverCoreJsObject.isUniqueIdentifierSet(uploadrefreshid)) {
								PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Package assembled, stopping upload intervals', 'import', 'send');
								clearTimeout(PrimeMoverCoreJsObject[uploadrefreshid]); 	 	        			
							}	 	        		
						} else if ( ( 'status' in myObj_prime_mover_upload_status && true  === myObj_prime_mover_upload_status.status ) && 
								( 'done' in myObj_prime_mover_upload_status && false ===  myObj_prime_mover_upload_status.done ) &&
								( 'actualprogress' in myObj_prime_mover_upload_status )   
						) {								
							var chunk = myObj_prime_mover_upload_status.chunk;
							var chunks = myObj_prime_mover_upload_status.chunks;
							var actualprogress = myObj_prime_mover_upload_status.actualprogress;

							$('#js-multisite_import_progress_span_' + blog_id + ' progress').attr({
								value: actualprogress,
								max: chunks,
							});                        

						} else if ( ( 'done' in myObj_prime_mover_upload_status && true === myObj_prime_mover_upload_status.done ) &&  
								( 'filepath'in myObj_prime_mover_upload_status && myObj_prime_mover_upload_status.filepath ) ) {							
							var filepath = myObj_prime_mover_upload_status.filepath;
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Upload completed', 'import', 'send');
							PrimeMoverCoreJsObject.import_handler( blog_id, filepath );

						} else if ( 'missing_chunk' in myObj_prime_mover_upload_status ) {	

							var uploadId = PrimeMoverCoreJsObject.generateUploadId(blog_id);							
							var missing_chunk = myObj_prime_mover_upload_status.missing_chunk;                    	                    
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Processing missing chunk found: ' + missing_chunk, 'import', 'send');

							var filesize_under_process = file.size;
							var filename_test = file.name;	                    
							var chunks = PrimeMoverCoreJsObject.compute_canonical_total_chunks( filesize_under_process, sliceSize );	                    

							var uploadfootprint = PrimeMoverCoreJsObject[uploadId];
							var missing_chunk_start = uploadfootprint[missing_chunk].slice_start;
							
							var missing_chunk_end = uploadfootprint[missing_chunk].slice_end;
							var piece_missing_chunk = PrimeMoverCoreJsObject.slice(file, missing_chunk_start, missing_chunk_end );	                    
							var missing_chunk_to_fix = missing_chunk;	                    	                    

							PrimeMoverCoreJsObject.outputMissingChunkDebugLog( missing_chunk, filesize_under_process, filename_test, sliceSize, chunks, missing_chunk_start, missing_chunk_end );
							PrimeMoverCoreJsObject[uploadrefreshid] = setTimeout(function() { PrimeMoverCoreJsObject.send( piece_missing_chunk, missing_chunk_start, missing_chunk_end, missing_chunk, 
									chunks, blog_id, file, sliceSize, uploadrefreshid, missing_chunk_to_fix ); }, prime_mover_js_ajax_renderer.prime_mover_upload_refresh_interval);

						} else if ('resume_parts_index' in myObj_prime_mover_upload_status && myObj_prime_mover_upload_status.resume_parts_index && 
								'resume_filepath' in myObj_prime_mover_upload_status && myObj_prime_mover_upload_status.resume_filepath &&
								'resume_chunks' in myObj_prime_mover_upload_status && myObj_prime_mover_upload_status.resume_chunks
						) {		 	    		
                            
							var resume_parts_index = myObj_prime_mover_upload_status.resume_parts_index;
							var resume_filepath = myObj_prime_mover_upload_status.resume_filepath;
							var resume_chunks = myObj_prime_mover_upload_status.resume_chunks;
							PrimeMoverCoreJsObject[uploadrefreshid] = setTimeout(function() { PrimeMoverCoreJsObject.send( 0, 0, 0, 0, 
									0, blog_id, file, sliceSize, uploadrefreshid, 0, resume_parts_index, resume_filepath, resume_chunks ); }, prime_mover_js_ajax_renderer.prime_mover_upload_refresh_interval);							

						} else if ( 'error' in myObj_prime_mover_upload_status && myObj_prime_mover_upload_status.error ) {								
							PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, 'import', true );
							$('#js-multisite_import_span_'+ blog_id ).html( myObj_prime_mover_upload_status.error );							
							$('#js-multisite_import_span_'+ blog_id ).addClass('notice notice-error prime-mover-corrupt-package-error');
							PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
						}				 	    		                
					},
					error : function(xhr, textStatus, errorThrown ) {	
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Error returned by AJAX chunk upload, textStatus is: ' + textStatus, 'import', 'send');
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Error returned by AJAX chunk upload, error thrown is: ' + errorThrown, 'import', 'send');
						this.tryCount++;
						if (errorThrown) {
							var msg = prime_mover_js_ajax_renderer.prime_mover_upload_error_message;
							msg = msg.replace('{{UPLOADSERVERERROR}}', errorThrown);
							msg = msg.replace('{{BLOGID}}', blog_id);

							this.errorReturned = msg;	
							this.clearprogressspans = true;	            		
						}

						var ajax_handler_object = this;
						PrimeMoverCoreJsObject.doErrorHandling(blog_id, 'import', ajax_handler_object, '', true, 'send');
					}
				});	    	  
			},
			/**
			 * Monitor the number of times the dialog is open.
			 */
			dialogOpened: [],
			/**
			 * Initialize dialog monitor
			 */
			initializeDialogMonitor: function(diagselector, reset) {
				if (typeof(reset) === 'undefined') {
					reset = false;
				} 
				if (typeof(this.dialogOpened[diagselector]) === 'undefined') {
					this.dialogOpened[diagselector] = 1;
				} 
				if (reset) {
					this.dialogOpened[diagselector] = 1;
				}
			},
			/**
			 * Checks if dialog is open
			 */
			dialogIsOpen: function( diagselector, show_only_once ) {
				var ret = false;
				if ( $(diagselector).hasClass("ui-dialog-content") && $(diagselector).dialog("isOpen") ) {
					ret = true;
				}

				if (typeof( show_only_once ) === 'undefined') {
					show_only_once = false;
				} 
				if (show_only_once) {
					this.initializeDialogMonitor(diagselector); 
					if ( false === ret && 1 === this.dialogOpened[diagselector] ) {            		
						this.dialogOpened[diagselector]++;   
						return false;
					} else {
						return true;
					}
				} else {
					return ret;
				}
			},
			/**
			 * Output missing chunk debug log info
			 */
			outputMissingChunkDebugLog: function( missing_chunk, filesize_under_process, filename_test, sliceSize, chunks, missing_start, missing_end ) {
				if ( prime_mover_js_ajax_renderer.prime_mover_debug_uploads ) {
					console.log( '******************************' );
					console.log( 'Requesting to re-upload missing chunk ' + missing_chunk );
					console.log( 'Missing start: ' + missing_start );
					console.log( 'Missing end: ' + missing_end );
					console.log( 'Re-uploading chunk request Filesize test: ' + filesize_under_process );
					console.log( 'Re-uploading chunk request Filename test' + filename_test );	                    
					console.log( 'Re-uploading chunk request Slice size test: ' + sliceSize );
					console.log( 'Total chunks of entire upload package is: ' + chunks ); 
					console.log( '******************************' );
				}        	
			},
			/**
			 * Compute end of missing chunk
			 */
			compute_end_of_missing_chunk: function( sliceSize, chunk, chunks, filesize_under_process ) {

				var end = 0;        	
				if  ( chunk === chunks ) {
					end = filesize_under_process;

				} else {
					end = sliceSize * chunk;        		
				}

				return end;        	
			},
			/**
			 * User approves diff
			 */
			user_approves_diff: function ( myObj_prime_mover_import_status, blog_id ) {			 	    			
				var in_progress_data = myObj_prime_mover_import_status.results;	
				in_progress_data = JSON.stringify( in_progress_data );
				var nonce_to_continue = myObj_prime_mover_import_status.continue_nonce;			 
				
				var next_method = myObj_prime_mover_import_status.next_method;
				var current_method = myObj_prime_mover_import_status.current_method;
				var unzipped_dir = '';
				var process_id = '';
				if ('unzipped_directory' in myObj_prime_mover_import_status) {
					unzipped_dir = myObj_prime_mover_import_status.unzipped_directory;				
				}			
                if ('process_id' in myObj_prime_mover_import_status) {
                	process_id = myObj_prime_mover_import_status.process_id;                        	
                } 
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Next method in DIFF ' + next_method, 'import', 'user_approves_diff');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Current method in DIFF ' + current_method, 'import', 'user_approves_diff');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Unzipped directory in DIFF ' + unzipped_dir, 'import', 'user_approves_diff');

				var continueajaxData = new FormData();
				continueajaxData.set( 'action', 'prime_mover_process_import' );
				continueajaxData.set( 'nonce_to_continue', nonce_to_continue );
				continueajaxData.set( 'data_to_continue', in_progress_data );	
				continueajaxData.set( 'diff_blog_id', blog_id );
				continueajaxData.set( 'prime_mover_next_import_method', next_method);	
				continueajaxData.set( 'prime_mover_current_import_method', current_method);
				continueajaxData.set( 'unzipped_directory', unzipped_dir);
				continueajaxData.set( 'process_id', process_id);
				
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'User approves diff, now resending ajax.', 'import', 'user_approves_diff');	 
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, continueajaxData, 'import', 'user_approves_diff');

				PrimeMoverCoreJsObject.prime_mover_track_progress('import', blog_id, true, continueajaxData);      	
			},
			/**
			 * User rejects diff
			 */
			user_rejects_diff: function ( myObj_prime_mover_import_status, blog_id ) {								 	    			
				if ( 'unzipped_directory' in myObj_prime_mover_import_status.results ) { 				
					PrimeMoverCoreJsObject.requestToDeleteTmpFile(myObj_prime_mover_import_status.results.unzipped_directory, blog_id, true);
				}        	
			},
			/**
			 * Handler to delete any temp files during upload/download process
			 */
			requestToDeleteTmpFile: function(path_to_delete, blog_id, diff_reject, popup_response, mode, tmp_file_mode) {
				if ( ! path_to_delete ) {
					return;
				}  
				if (typeof(popup_response) === 'undefined') {
					popup_response = true;
				}
				if (typeof(mode) === 'undefined') {
					mode = 'import'
				}
				if (typeof(tmp_file_mode) === 'undefined') {
					tmp_file_mode = 'no';
				}
				var data = {
						action: 'multisite_tempfile_cancel',
						dataType: 'json',	 
						temp_file_to_delete : path_to_delete,
						prime_mover_deletetmpfile_nonce: prime_mover_js_ajax_renderer.prime_mover_deletetmpfile_nonce,
						diff_reject: diff_reject,
						blog_id : blog_id,
						mode : mode,
						tmp_file_mode : tmp_file_mode
				};		     					    
				$.post(ajaxurl, data, function( response ) {
					if (popup_response) {
						var myObj_multisite_deletetmpfile_status = response;		
						$('#js-multisite_import_span_'+ blog_id ).html( '' );	
						if ( myObj_multisite_deletetmpfile_status.tempfile_deletion_status ) { 	    	 									 	    			
							PrimeMoverCoreJsObject.user_notices( '#js-prime-mover-cancel-import-diff-' + blog_id );
						} else {	 	    	 				 	    	 		
							PrimeMoverCoreJsObject.user_notices( '#js-prime-mover-cancel-import-diff-fail-' + blog_id );
						}						
					}
				});
				PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id );        	
			},
			/**
			 * Compute overall process percentage progress
			 */
			computeOverallPercentProgress: function(current_method, mode, blog_id) {
			      var methods = [];
				  if ('export' === mode) {
					  methods = prime_mover_js_ajax_renderer.prime_mover_export_method_lists;
				  }
				  if ('import' === mode) {
					  methods = prime_mover_js_ajax_renderer.prime_mover_import_method_lists;
				  }				  
				  
				  var index = methods.indexOf(current_method);				  
				  var progress = index + 1;				  
				  var length = methods.length;				  
				  
				  var decimal = (progress/length) * (100);
				  var numerical =  Math.floor(decimal);
				  
				  percent = numerical + '%';
				  var progress_selector = '#js-multisite_' + mode + '_span_'+ blog_id;				  
				  var overall_progress_selector = progress_selector + ' .progress-circle';				  
				  var percent_class = 'p' + numerical;
				 
				  if (numerical >= 50) {
					  percent_class = 'over50' + ' ' + percent_class;
				  }
				  
				  $(overall_progress_selector).addClass(percent_class);
				  $(overall_progress_selector + ' span').text(percent);
			},
			/**
			 * Do export ajax call
			 */
			doExporterAjax: function(blog_id, data, retry_times, export_processing_times) {	
				if (typeof(retry_times) === 'undefined') {
					retry_times = 0;
				}

				if (typeof(export_processing_times) === 'undefined') {
					var export_processing_times = [];
				}

				var start_time = new Date().getTime();				
				$.post(ajaxurl, data, function( response ) {	
					if (PrimeMoverCoreJsObject.prime_mover_is_js_object(response) && 'next_method' in response && 'current_method' in response) {
						var next_method = response.next_method;
						var current_method = response.current_method;
						PrimeMoverCoreJsObject.computeOverallPercentProgress(current_method, 'export', blog_id);
						data.prime_mover_next_export_method = next_method;
						data.prime_mover_current_export_method = current_method;
						
						var temp_folder_path = '';						
                        if ('temp_folder_path' in response) {
                        	temp_folder_path = response.temp_folder_path;                        	
                        } 
                        data.temp_folder_path = temp_folder_path;
                        
                        var process_id = '';
                        if ('process_id' in response) {
                        	process_id = response.process_id;                        	
                        } 
                        data.process_id = process_id;                        
						setTimeout(function() { PrimeMoverCoreJsObject.doExporterAjax(blog_id, data); }, prime_mover_js_ajax_renderer.prime_mover_standard_immediate_resending);	 	    		
					}	 	    	
				}).fail(function(xhr, status, error) {
					var request_time_on_error = new Date().getTime() - start_time;
					export_processing_times.push(request_time_on_error);					

					var total_time_spent_retrying = ((export_processing_times.reduce((x, y) => x + y)) /1000);		
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Total time spent retrying: ' + total_time_spent_retrying, mode, 'doExporterAjax');
					var mode = 'export';
					var refreshid = PrimeMoverCoreJsObject.generateUniqueIdentifier( mode, blog_id );

					if ( false === PrimeMoverCoreJsObject[refreshid] ) {
						return;
					}

					var current_method = prime_mover_js_ajax_renderer.prime_mover_unknown_export_process_error;
					if (PrimeMoverCoreJsObject.prime_mover_is_js_object(data) && 'prime_mover_next_export_method' in data) {
						current_method = data.prime_mover_next_export_method;	 	    		
					}	
					var ajax_handler_object = this;	
					var retryLimit = prime_mover_js_ajax_renderer.prime_mover_totalwaiting_seconds_error;
					var seconds_per_retry = Math.round((total_time_spent_retrying) / (retry_times + 1));

					if ( ! error ) {
						error = prime_mover_js_ajax_renderer.prime_mover_unknown_js_error;
					}        	    
					var msg = prime_mover_js_ajax_renderer.prime_mover_exportprocess_error_message;
					msg = msg.replace('{{PROGRESSSERVERERROR}}', error);
					msg = msg.replace('{{BLOGID}}', blog_id);
					msg = msg.replace('{{EXPORTMETHODWITHERROR}}', current_method);
					msg = msg.replace('{{RETRYSECONDS}}', seconds_per_retry);
					msg = msg.replace('{{FIXEDSECONDS}}', seconds_per_retry);
					var clearprogressspans = true;	    	     
					if (total_time_spent_retrying > retryLimit) {   
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'The exporter ajax request has reached a retry limit.', mode, 'doExporterAjax');
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Total time spent seconds: ' + total_time_spent_retrying, 'doExporterAjax');
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Retry total time specs ' + retryLimit, mode, 'doExporterAjax');
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'The exporter process data at time of error is: ', mode, 'doExporterAjax');
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, data, mode, 'doExporterAjax');

						var process_id = '';												
						if (PrimeMoverCoreJsObject.prime_mover_is_js_object(data) && 'process_id' in data) {
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Process id with error: ' + process_id, mode, 'doExporterAjax');	
							process_id = data.process_id;	 	    		
						}						
						if (process_id) {
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, "Shutdown requested on export error", mode, 'doExporterAjax');
							PrimeMoverCoreJsObject.shutdown_process(mode, process_id, blog_id); 					
						}						

						var temp_folder_path = '';	
						if (PrimeMoverCoreJsObject.prime_mover_is_js_object(data) && 'temp_folder_path' in data) {
							temp_folder_path = data.temp_folder_path;	 	    		
						}	
						if (temp_folder_path) {							
							PrimeMoverCoreJsObject.requestToDeleteTmpFile(temp_folder_path, blog_id, false, false, 'export');					
						}
						
						PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, mode, true );
						var uploaderror_selector = PrimeMoverCoreJsObject.getGenericFailSelector(blog_id);
						if ( false === PrimeMoverCoreJsObject.dialogIsOpen(uploaderror_selector, true) ) {               			              		
							PrimeMoverCoreJsObject.user_notices( uploaderror_selector, blog_id, mode, clearprogressspans, msg );
						}
						PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
					} else {
						retry_times++;	
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Export request has been tried: ' + retry_times, mode, 'doExporterAjax');
						setTimeout(function() { PrimeMoverCoreJsObject.doExporterAjax(blog_id, data, retry_times, export_processing_times); }, prime_mover_js_ajax_renderer.prime_mover_retry_request_resending);	  						
					} 	 	    
				});    		
			},			
			/**
			 * Formally processes the import after successful uploading
			 */
			import_handler: function( blog_id, filepath, retry_times, ajaxData, import_processing_times) {
				if (typeof(retry_times) === 'undefined') {
					retry_times = 0;
				} 
				if (typeof(ajaxData) === 'undefined') {
					var ajaxData = new FormData();
				}
				if (typeof(import_processing_times) === 'undefined') {
					var import_processing_times = [];
				}
				var start_time = new Date().getTime();	
				ajaxData.set( 'action', 'prime_mover_process_import' );
				ajaxData.set( 'prime_mover_import_nonce', prime_mover_js_ajax_renderer.prime_mover_import_nonce );
				ajaxData.set( 'multisite_blogid_to_import', blog_id );				
				ajaxData.set( 'multisite_import_package_uploaded_file', filepath );
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Import_handling requested for this blog. Starting import..', 'import', 'import_handler');

				$.ajax({
					url: ajaxurl,
					type: 'post',
					data: ajaxData,
					cache: false,	
					contentType: false,	            
					processData: false,
					tryCount : 0,
					retryLimit : prime_mover_js_ajax_renderer.prime_mover_retry_limit,
					success: function( response ) {
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Response received', 'import', 'import_handler');
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, response, 'import', 'import_handler');
						if (PrimeMoverCoreJsObject.prime_mover_is_js_object(response) && 'next_method' in response && 'current_method' in response) {
							var next_method = response.next_method;
							var current_method = response.current_method;
							
							PrimeMoverCoreJsObject.computeOverallPercentProgress(current_method, 'import', blog_id);
							var unzipped_directory = '';
							var process_id = '';
                            if ('unzipped_directory' in response) {
                            	unzipped_directory = response.unzipped_directory;                        	
                            }   
                            if ('process_id' in response) {
                            	process_id = response.process_id;                        	
                            }  
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Next import method to process:', 'import', 'import_handler');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, next_method, 'import', 'import_handler');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Current method being processed:', 'import', 'import_handler');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, current_method, 'import', 'import_handler');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'The current ajax data to re-post is:', 'import', 'import_handler');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, ajaxData, 'import', 'import_handler');

							ajaxData.set( 'prime_mover_next_import_method', next_method);	
							ajaxData.set( 'prime_mover_current_import_method', current_method);
							ajaxData.set( 'unzipped_directory', unzipped_directory);
							ajaxData.set( 'process_id', process_id);							
							setTimeout(function() { PrimeMoverCoreJsObject.import_handler(blog_id, filepath, retry_times, ajaxData); }, prime_mover_js_ajax_renderer.prime_mover_standard_immediate_resending);	 	    		
						} 	     
					},
					error : function(xhr, textStatus, errorThrown ) {	
						var mode = 'import';
						var refreshid = PrimeMoverCoreJsObject.generateUniqueIdentifier( mode, blog_id );
						if ( false === PrimeMoverCoreJsObject[refreshid] ) {
							return;
						}						
						this.tryCount++;
						var import_ajax_handler_object = this;	 
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Error found on import_handler() ajax request, starting error handling and retrying..', mode, 'import_handler');	            	

						var retry = false;
						var progress_identifier =  PrimeMoverCoreJsObject.generateProgressIdentifier(refreshid, mode);	            
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Progress identifier at import_handler error handler: ' + progress_identifier, mode, 'import_handler');

						if (typeof PrimeMoverCoreJsObject[progress_identifier] !== 'undefined') {
							var latest_progress = PrimeMoverCoreJsObject[progress_identifier];	
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'At import handler error, latest progress found is ' + latest_progress, mode, 'import_handler');
							if ('boot' === latest_progress) {
								retry = true;
								PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Still boot process, retrying to avoid stuck on this stage.' + latest_progress, mode, 'import_handler');
							}		            	
						}
						if (retry) {
							PrimeMoverCoreJsObject.doErrorHandling(blog_id, mode, import_ajax_handler_object, null, retry, 'import_handler' );
						} else {
							PrimeMoverCoreJsObject.doImportHandlingRetry(ajaxData, errorThrown, blog_id, retry_times, filepath, false, start_time, import_processing_times);
						}		                        
					} 
				});        	
			},
			/**
			 * Get unzipped directory from data
			 */
			getValueFromFormData: function(ajaxData, key) {
				var value = '';
				var values = [];
				if (PrimeMoverCoreJsObject.prime_mover_is_js_object(ajaxData) && ajaxData.has(key)) {
					values = ajaxData.getAll(key);
					value = values[values.length - 1];
				}
				return value;
			},			
			/**
			 * Do import handling retry
			 * This includes normal retry and those are from diff retries
			 */
			doImportHandlingRetry: function(ajaxData, errorThrown, blog_id, retry_times, filepath, diffmode, start_time, import_processing_times) {				
				
				var request_time_on_error = new Date().getTime() - start_time;
				import_processing_times.push(request_time_on_error);
				
				var total_time_spent_retrying = ((import_processing_times.reduce((x, y) => x + y)) /1000);				
				var current_method = PrimeMoverCoreJsObject.getValueFromFormData(ajaxData, 'prime_mover_next_import_method');
				if ( ! current_method ) {
					current_method = prime_mover_js_ajax_renderer.prime_mover_unknown_import_process_error;	
				}
				
				var retryLimit = prime_mover_js_ajax_renderer.prime_mover_retry_limit;
				var mode = 'import';
				if ( ! errorThrown ) {
					error = prime_mover_js_ajax_renderer.prime_mover_unknown_js_error;
				}   				
				
				var retryLimit = prime_mover_js_ajax_renderer.prime_mover_totalwaiting_seconds_error;
				var seconds_per_retry = Math.round((total_time_spent_retrying) / (retry_times + 1));				
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Current total time spent trying: ' + total_time_spent_retrying, mode, 'doImportHandlingRetry');
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Current retry limit setting: ' + retryLimit, mode, 'doImportHandlingRetry');
				
				var msg = prime_mover_js_ajax_renderer.prime_mover_importprocess_error_message;
				msg = msg.replace('{{PROGRESSSERVERERROR}}', errorThrown);
				msg = msg.replace('{{BLOGID}}', blog_id);
				msg = msg.replace('{{IMPORTMETHODWITHERROR}}', current_method);				
				msg = msg.replace('{{RETRYSECONDS}}', seconds_per_retry);
				msg = msg.replace('{{FIXEDSECONDS}}', seconds_per_retry);				
				
				var clearprogressspans = true;	    	     
				if (total_time_spent_retrying > retryLimit) {			

					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'The importer ajax request has reached a retry limit.', mode, 'doImportHandlingRetry');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Total time retrying: ' + total_time_spent_retrying, 'doImportHandlingRetry');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Retry limit set in seconds: ' + retryLimit, mode, 'doImportHandlingRetry');
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Post approval diff mode is' + diffmode, mode, 'doImportHandlingRetry');
				
					PrimeMoverCoreJsObject.stop_interval_clear_progress_text( blog_id, true, mode, true );
					var process_id = PrimeMoverCoreJsObject.getValueFromFormData(ajaxData, 'process_id');	
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Process id with error: ' + process_id, mode, 'doImportHandlingRetry');
					if (process_id) {
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, "Shutdown requested on import error", mode, 'doImportHandlingRetry');
						PrimeMoverCoreJsObject.shutdown_process('import', process_id, blog_id); 					
					}						
					var unzipped_directory = PrimeMoverCoreJsObject.getValueFromFormData(ajaxData, 'unzipped_directory');	
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Unzipped directory that need to be deleted: ' + unzipped_directory, mode, 'doImportHandlingRetry');
					if (unzipped_directory) {
						PrimeMoverCoreJsObject.requestToDeleteTmpFile(unzipped_directory, blog_id, false, false);					
					}
					
					var uploaderror_selector = PrimeMoverCoreJsObject.getGenericFailSelector(blog_id);
					if ( false === PrimeMoverCoreJsObject.dialogIsOpen(uploaderror_selector, true) ) {               			              		
						PrimeMoverCoreJsObject.user_notices( uploaderror_selector, blog_id, mode, clearprogressspans, msg );
					}
					PrimeMoverCoreJsObject.lockDownButtons(blog_id, false);
				} else {
					retry_times++;	
					PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'IMPORTER REQUEST HAS BEEN TRIED: ' + retry_times, mode, 'doImportHandlingRetry');
					if (diffmode) {
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'RETRYING IMPORT FOR NEXT METHOD IN DIFF MODE', mode, 'doImportHandlingRetry');
						setTimeout(function() { PrimeMoverCoreJsObject.doDiffPostAjax(mode, blog_id, ajaxData, retry_times, import_processing_times); }, prime_mover_js_ajax_renderer.prime_mover_retry_request_resending);					
					} else {
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'RETRYING IMPORT FOR NEXT METHOD IN STANDARD MODE', mode, 'doImportHandlingRetry');
						setTimeout(function() { PrimeMoverCoreJsObject.import_handler(blog_id, filepath, retry_times, ajaxData, import_processing_times); }, prime_mover_js_ajax_renderer.prime_mover_retry_request_resending);						
					}					
				}							
			},
			/**
			 * Do diff post ajax on import
			 */
			doDiffPostAjax: function(mode, blog_id, args, retry_times, import_processing_times) {
				if (typeof(retry_times) === 'undefined') {
					retry_times = 0;
				} 
				if (typeof(import_processing_times) === 'undefined') {
					var import_processing_times = [];
				}
				var start_time = new Date().getTime();	
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Continue diff processing...', mode, 'doDiffPostAjax');   
				PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, args, mode, 'doDiffPostAjax');
				$.ajax({
					url: ajaxurl,
					type: 'post',			 			            
					data: args,
					cache: false,	
					contentType: false,            
					processData: false,
					success: function( response ) {	
						PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'Success response after diff approval received', mode, 'doDiffPostAjax');
						if (PrimeMoverCoreJsObject.prime_mover_is_js_object(response) && 'next_method' in response && 'current_method' in response) {
							var next_method = response.next_method;
							var current_method = response.current_method;
							
							PrimeMoverCoreJsObject.computeOverallPercentProgress(current_method, 'import', blog_id);
							args.set( 'prime_mover_next_import_method', next_method);	
							args.set( 'prime_mover_current_import_method', current_method);
                            
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'After diff approval next import method' + next_method, mode, 'doDiffPostAjax');
							PrimeMoverCoreJsObject.logProcessingErrorAnalysis(blog_id, 'After diff approval current import method' + current_method, mode, 'doDiffPostAjax');

							setTimeout(function() { PrimeMoverCoreJsObject.doDiffPostAjax(mode, blog_id, args); }, prime_mover_js_ajax_renderer.prime_mover_standard_immediate_resending);	 	    		
						}						
					},
					error : function(xhr, textStatus, errorThrown ) {
						PrimeMoverCoreJsObject.doImportHandlingRetry(args, errorThrown, blog_id, retry_times, '', true, start_time, import_processing_times);
					}					
				}); 				
			},
			/**
			 * Do after closing diff dialog for which user cancelled.
			 */
			doAfterClose: function( import_diff_selector, myObj_prime_mover_import_status, blog_id ) {       	  
				$( import_diff_selector ).text('');
				PrimeMoverCoreJsObject.user_rejects_diff( myObj_prime_mover_import_status, blog_id );      	
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
					PrimeMoverCoreJsObject.fluidDialog();
				});
				PrimeMoverCoreJsObject.fluidDialog();        	
			}

	};    
}(jQuery));
