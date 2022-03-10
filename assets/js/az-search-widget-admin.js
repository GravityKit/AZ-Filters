/**
 * AZ Search Widget Functionality
 *
 * @package   GravityView A-Z Entry Filter
 * @license   GPL2+
 * @author    GravityView <hello@gravityview.co>
 * @link      http://gravityview.co
 * @copyright Copyright 2014, Katz Web Services, Inc.
 *
 * @since 1.0
 */

(function( $ ) {

    var gvAZFilterWidget = {

        selectFields : null,

        init: function() {

            // Load the filter fields
            $('body').on('dialogopen', '.gv-dialog-options', gvAZFilterWidget.openDialog );

            // hook on assigned form change to clear settings
			$('#gravityview_form_id').change( gvAZFilterWidget.clearSettings );

        },

        /**
         * When opening the dialog, trigger renderSelect
         * @param  {jQuery Event} e
         * @return {void}
         */
        openDialog: function(e) {
        	e.preventDefault();

            // If this is AZ Filter widget, process
            if( $( e.target ).parents('[data-fieldid="az_filter"]').length ) {
            	gvAZFilterWidget.renderSelect( $(this).parents('.gv-fields') );
        	}

        },

        renderSelect: function( parent ) {

        	var $select = $('select[name*=filter_field]', parent);

        	// Store the previous value to restore later
        	var selectval = $select.val();

        	if( typeof( $select.data('fields') ) === 'undefined' ) {

        		// While it's loading, disable the field, remove previous options, and add loading message.
        		$select.prop('disabled', 'disabled').empty().append('<option>'+ gvGlobals.loading_text + '</option>');


				var data = {
					action: 'gv_sortable_fields_form',
					nonce: gvGlobals.nonce,
				};

				data.form_id = $('#gravityview_form_id').val();

				$.post( ajaxurl, data, function( response ) {
					if( response && response !== '0' ) {

						$select.data( 'fields', response );

						$select
							.empty()
							.append( response )
							.val( selectval )
							.prop('disabled', null );
					}
				});

			}

        },

        /**
         * When the form changes, we need to wipe the configurations.
         * @return {void}
         */
        clearSettings: function() {

			$('select[name*=filter_field]').each( function() {
				$(this).val('');
				$select.data( 'fields', null );
			});
		}

    }; // end

    $(document).ready( function() {
        gvAZFilterWidget.init();
    });

}(jQuery));