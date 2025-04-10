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
                this.addControlButtons();
                this.setupEventListeners();
                this.setupFormSubmission();
                this.initializeCheckedStates();
            },

            /**
             * Add control buttons for checking/unchecking all items
             */
            addControlButtons: function() {
                const $controlsDiv = $('<div class="fhc-controls"></div>');
                const $checkAllBtn = $('<button type="button" class="button fhc-check-all">' + fhcL10n.checkAll + '</button>');
                const $uncheckAllBtn = $('<button type="button" class="button fhc-uncheck-all">' + fhcL10n.uncheckAll + '</button>');
                
                $controlsDiv.append($checkAllBtn).append(' ').append($uncheckAllBtn);
                $('.fhc-grid-container').prepend($controlsDiv);
            },

            /**
             * Setup event listeners for checkboxes and buttons
             */
            setupEventListeners: function() {
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
                });
                
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
                });
                
                // Check all button click event
                $('.fhc-check-all').on('click', function(e) {
                    e.preventDefault();
                    $('.fhc-menu-item input[type="checkbox"], .fhc-submenu-item input[type="checkbox"]').prop('checked', true);
                    $('.fhc-menu-group').addClass('fhc-checked fhc-has-checked-submenu');
                });
                
                // Uncheck all button click event
                $('.fhc-uncheck-all').on('click', function(e) {
                    e.preventDefault();
                    $('.fhc-menu-item input[type="checkbox"], .fhc-submenu-item input[type="checkbox"]').prop('checked', false);
                    $('.fhc-menu-group').removeClass('fhc-checked fhc-has-checked-submenu');
                });
                
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
            },
            
            /**
             * Setup form submission to ensure proper saving
             */
            setupFormSubmission: function() {
                $('form').on('submit', function() {
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
