/**
 * Focal Haus Core - Admin JavaScript
 *
 * @package Focal_Haus_Core
 */

(function($) {
    'use strict';

    // Initialize when the DOM is ready
    $(document).ready(function() {
        const fhcAdmin = {
            /**
             * Initialize the admin functionality
             */
            init: function() {
                this.setupEventListeners();
                this.setupFormSubmission();
                this.initializeCheckedStates();
            },

            /**
             * Setup event listeners for checkboxes and buttons
             */
            setupEventListeners: function() {
                // Toggle All checkbox
                $('#fhc_toggle_all').on('change', function() {
                    const isChecked = $(this).prop('checked');
                    
                    // Update checkbox text
                    $('#fhc_toggle_all_text').text(isChecked ? 'Uncheck All' : 'Check All');
                    
                    // Check/uncheck all main menu and submenu checkboxes
                    $('.fhc-menu-item input[type="checkbox"], .fhc-submenu-item input[type="checkbox"]').prop('checked', isChecked);
                    
                    // Update menu group classes
                    if (isChecked) {
                        $('.fhc-menu-group').addClass('fhc-checked fhc-has-checked-submenu');
                    } else {
                        $('.fhc-menu-group').removeClass('fhc-checked fhc-has-checked-submenu');
                    }
                });
                
                // Main menu checkbox change event
                $('.fhc-menu-item input[type="checkbox"]').on('change', function() {
                    const $menuGroup = $(this).closest('.fhc-menu-group');
                    const isChecked = $(this).prop('checked');
                    
                    // Check/uncheck all submenu items
                    $menuGroup.find('.fhc-submenu-item input[type="checkbox"]').prop('checked', isChecked);
                    
                    // Add/remove checked class to the menu group
                    if (isChecked) {
                        $menuGroup.addClass('fhc-checked');
                    } else {
                        $menuGroup.removeClass('fhc-checked');
                    }
                    
                    // Update toggle all checkbox state
                    this.updateToggleAllState();
                }.bind(this));
                
                // Submenu checkbox change event
                $('.fhc-submenu-item input[type="checkbox"]').on('change', function() {
                    const $menuGroup = $(this).closest('.fhc-menu-group');
                    const $allSubmenus = $menuGroup.find('.fhc-submenu-item input[type="checkbox"]');
                    const $checkedSubmenus = $allSubmenus.filter(':checked');
                    
                    // If any submenu is checked, add checked class to the menu group
                    if ($checkedSubmenus.length > 0) {
                        $menuGroup.addClass('fhc-has-checked-submenu');
                    } else {
                        $menuGroup.removeClass('fhc-has-checked-submenu');
                    }
                    
                    // If all submenus are checked, check the main menu checkbox
                    if ($checkedSubmenus.length === $allSubmenus.length && $allSubmenus.length > 0) {
                        $menuGroup.find('.fhc-menu-item input[type="checkbox"]').prop('checked', true);
                        $menuGroup.addClass('fhc-checked');
                    } else {
                        $menuGroup.find('.fhc-menu-item input[type="checkbox"]').prop('checked', false);
                        $menuGroup.removeClass('fhc-checked');
                    }
                    
                    // Update toggle all checkbox state
                    this.updateToggleAllState();
                }.bind(this));
                
                // Tab navigation
                $('.nav-tab').on('click', function() {
                    // This is handled by WordPress, but we could add custom behavior here if needed
                });
            },
            
            /**
             * Initialize the checked states based on current selections
             */
            initializeCheckedStates: function() {
                $('.fhc-menu-group').each(function() {
                    const $menuGroup = $(this);
                    const $mainCheckbox = $menuGroup.find('.fhc-menu-item input[type="checkbox"]');
                    const $allSubmenus = $menuGroup.find('.fhc-submenu-item input[type="checkbox"]');
                    const $checkedSubmenus = $allSubmenus.filter(':checked');
                    
                    if ($mainCheckbox.prop('checked')) {
                        $menuGroup.addClass('fhc-checked');
                    }
                    
                    if ($checkedSubmenus.length > 0) {
                        $menuGroup.addClass('fhc-has-checked-submenu');
                    }
                    
                    // If all submenus are checked, check the main menu checkbox
                    if ($checkedSubmenus.length === $allSubmenus.length && $allSubmenus.length > 0) {
                        $mainCheckbox.prop('checked', true);
                        $menuGroup.addClass('fhc-checked');
                    }
                });
                
                // Initialize toggle all checkbox state
                this.updateToggleAllState();
            },
            
            /**
             * Update the state of the "Check All / Uncheck All" toggle
             */
            updateToggleAllState: function() {
                const $allCheckboxes = $('.fhc-menu-item input[type="checkbox"], .fhc-submenu-item input[type="checkbox"]');
                const $checkedCheckboxes = $allCheckboxes.filter(':checked');
                const $toggleAll = $('#fhc_toggle_all');
                const $toggleAllText = $('#fhc_toggle_all_text');
                
                // If all checkboxes are checked, set the toggle to "checked" and text to "Uncheck All"
                if ($checkedCheckboxes.length === $allCheckboxes.length && $allCheckboxes.length > 0) {
                    $toggleAll.prop('checked', true);
                    $toggleAllText.text('Uncheck All');
                } else {
                    $toggleAll.prop('checked', false);
                    $toggleAllText.text('Check All');
                }
            },
            
            /**
             * Setup form submission to ensure proper saving
             */
            setupFormSubmission: function() {
                $('form').on('submit', function() {
                    // Exclude the toggle-all checkbox from submission
                    $('#fhc_toggle_all').attr('disabled', true);
                    
                    // Make sure unchecked checkboxes are not included in the form submission
                    $('input[type="checkbox"]:not(:checked)').each(function() {
                        $(this).attr('disabled', true);
                    });
                    
                    // Add a hidden field to indicate form submission
                    $(this).append('<input type="hidden" name="fhc_form_submitted" value="1">');
                    
                    return true;
                });
            }
        };
        
        // Initialize the admin functionality
        fhcAdmin.init();
    });
})(jQuery);
