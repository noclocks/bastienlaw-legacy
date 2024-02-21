(function ($) {
	$(function(){
		var $window = $(window);
		PrimeMoverBackups.initialize();
		PrimeMoverBackups.deleteBackups();	
		PrimeMoverBackups.copyURLToClipBoard();
		PrimeMoverBackups.cleanUpUrlAfterBackupRefresh();
		PrimeMoverBackups.warnFreeBackupRestoration();	
		PrimeMoverBackups.checkWidth($window);
		PrimeMoverBackups.narrowScreens($window);
		
    });
    var PrimeMoverBackups = {
    	/**
    	 * Mobile responsive checks on backup menu screen.
    	 */
        narrowScreens: function($window) {
        	$window.resize(function() {
        		PrimeMoverBackups.checkWidth($window);
        	});
        },
        /**
         * Backup menu icons responsive check
         */
        checkWidth: function($window) {
		        var windowsize = $window.width();		        
		        if (windowsize > 768 && windowsize < 1280 ) {		        		        	
		        	$('.js-prime-mover-download-button-backup').html('<span class="dashicons dashicons-download"></span>');
		        	$('.js-prime-mover-restore-icon').html('<span class="dashicons dashicons-backup"></span>');
		        	$('.js-prime-mover-clipboard-button-responsive').html('<span class="dashicons dashicons-migrate"></span>');
		        	$('.js-prime-mover-upgrade-button-simple').html('<span class="dashicons dashicons-cart prime-mover-cart-dashicon"></span>');
		        } else {	        	
		        	$('.js-prime-mover-download-button-backup').html(prime_mover_js_backups_renderer.download_text);
		        	$('.js-prime-mover-restore-icon').html(prime_mover_js_backups_renderer.restore_package_text);
		        	$('.js-prime-mover-clipboard-button-responsive').html(prime_mover_js_backups_renderer.copy_restore_url);
		        	$('.js-prime-mover-upgrade-button-simple').html(prime_mover_js_backups_renderer.upgradetoprotext);
		        }
		},
		/**
		 * Clean up URL after backup refresh
		 */
    	cleanUpUrlAfterBackupRefresh: function() {
    		$(window).load(function () {
    			if (PrimeMoverBackups.maybeCleanUpUrl()) {    				
        		    window.history.replaceState({}, "Title", prime_mover_js_backups_renderer.backup_menu_url);   				
    			}    			  
    		});
    	},
    	/**
    	 * Maybe clean up URL
    	 */
    	maybeCleanUpUrl: function() {
    		var field = 'prime_mover_refresh_backups';
    		var url = window.location.href;
    		if(url.indexOf('?' + field + '=') != -1)
    		    return true;
    		else if(url.indexOf('&' + field + '=') != -1)
    		    return true;
    		return false  		
    	},
    	/**
    	* Initialize
    	*/
        initialize: function() {
        	$('body').on('click','.js-prime-mover-clear-site', function(e){     			
    		    $('.js-prime-mover-site-selector').val('');	         	
    		});  
        	
        	$(".js-prime-mover-site-selector").change(function() {
        		   $(this).closest("form").submit();
            });
        },
        /**
    	 * Delete backups handler
    	 */
    	deleteBackups: function() {
    		var deleteZipFileselector = '#doaction, #doaction2';    		
    		$('body').on('click',deleteZipFileselector, function(e){     			
    			var deleteval = $('#bulk-action-selector-top').val();
    			if ('-1' === deleteval) {
    				deleteval = $('#bulk-action-selector-bottom').val();
    			}
                if ( 'delete' !== deleteval) {
                	return;
                }                
                if ( 0 === $("input[name='prime_mover_backup[]']:checkbox:checked").length) {
                   return;
                }                
                e.preventDefault();                
    			var dialog_selector = "#js-prime-mover-confirm-backups-delete";
    			var msg = prime_mover_js_backups_renderer.deleteBackupFileWarning;    			
    			
            	$(dialog_selector + " span").html(msg);           	
            	PrimeMoverBackups.showDeleteDialog(dialog_selector);            	
    		});    		
    	},
        /**
         * Warn free backup restoration
         */
        warnFreeBackupRestoration: function() {
        	var warnFreeBackupRestoreselector = '.js-prime-mover-restore-free-backups';    		
    		$('body').on('click',warnFreeBackupRestoreselector, function(e){     			 
                e.preventDefault();    			
    			   			
    			var target_uri = $(this).attr('href');    
    			if ('readonly' === prime_mover_js_backups_renderer.prime_mover_config_writable) {
    				var dialog_selector = "#js-prime-mover-block-free-restore-cached-enabled";
    				PrimeMoverBackups.showCachingEnabledErrorRestoreDialog(dialog_selector); 
    			} else {
    				var dialog_selector = "#js-prime-mover-confirm-backups-free-restore";
    				PrimeMoverBackups.showFreeBackupRestoreDialog(dialog_selector, target_uri);      
    			}            	      	
    		});           	
        }, 
        /**
    	 * Show restore warning on free plan
    	 */
    	showCachingEnabledErrorRestoreDialog: function(dialog_selector) { 
    		$(dialog_selector).dialog({
	            resizable: false,
	            height: "auto",	            
	            minWidth: 320,
	            dialogClass: 'prime-mover-user-dialog',
	            maxWidth: 600,
	            modal: true,
	            fluid: true,
				buttons: [
				    {
				      text: prime_mover_js_backups_renderer.cancelbutton,
				      "class" : "button-primary",
				      click: function() {				    	  					    	  
				    	  $( this ).dialog( "close" );				    	  
					  }
				    }				    
				 ],
				 open: function( event, ui ) {							
						$('.prime-mover-user-dialog button').blur();	    	 
			     },
	          });
            PrimeMoverBackups.handle_responsive_dialog();   		
    	},        
    	/**
    	 * Show restore warning on free plan
    	 */
    	showFreeBackupRestoreDialog: function(dialog_selector, target_uri) {
    		var warnFreeBackupRestoreselector = '.js-prime-mover-restore-free-backups';  
    		$(dialog_selector).dialog({
	            resizable: false,
	            height: "auto",	            
	            minWidth: 320,
	            dialogClass: 'prime-mover-user-dialog',
	            maxWidth: 600,
	            modal: true,
	            fluid: true,
				buttons: [
				    {
				      text: prime_mover_js_backups_renderer.freerestorebutton,	
				      "class" : "button-primary",
				      click: function() {				    	  					    	  
				    	  window.location = target_uri;				    	  
					  }
				    },
				    {
					 text: prime_mover_js_backups_renderer.cancelbutton,					 
					 click: function() {							 
						 $( this ).dialog( "close" );						 
					 }
					},				    
				 ],
				 open: function( event, ui ) {							
						$('.prime-mover-user-dialog button').blur();	    	 
			     },
	          });
            PrimeMoverBackups.handle_responsive_dialog();   		
    	},
    	/**
    	 * Delete dialog handler
    	 */
    	showDeleteDialog: function(dialog_selector) {                           			
            $(dialog_selector).dialog({
	            resizable: false,
	            height: "auto",	            
	            minWidth: 320,
	            dialogClass: 'prime-mover-user-dialog',
	            maxWidth: 600,
	            modal: true,
	            fluid: true,
				buttons: [
				    {
				      text: prime_mover_js_backups_renderer.deletebutton,
				      "class": "button button-primary",
				      click: function() {				    	  	
				    	  $('#prime_mover_backups-filter').submit();
					  }
				    },
				    {
					 text: prime_mover_js_backups_renderer.cancelbutton,					 
					 click: function() {							 
						 $( this ).dialog( "close" );						 
					 }
					},				    
				 ],
				 open: function( event, ui ) {							
					 $('.prime-mover-user-dialog button').blur();	    	 
			     },
	          });
            PrimeMoverBackups.handle_responsive_dialog();
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
		 * Copy to clipboard
		 */
		copyURLToClipBoard: function() {
    		var clipboard = new ClipboardJS('.js-prime-mover-copy-clipboard-menu');    		
    		
    		clipboard.on('success', function(e) {
    			var target_id = $(e.trigger).attr('data-clipboard-id'); 
    			$('#' + target_id).fadeIn('fast').delay(3000).fadeOut();
    		});
    	},
		/**
		 * Handle responsive dialog
		 */
		handle_responsive_dialog: function() {
			$(window).resize(function () {
				PrimeMoverBackups.fluidDialog();
			});
			PrimeMoverBackups.fluidDialog();        	
		}   	
    };
}(jQuery));
