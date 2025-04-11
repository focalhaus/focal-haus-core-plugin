/**
 * Focal Haus Core - Admin JavaScript
 *
 * @package Focal_Haus_Core
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        /**
         * Function to check/uncheck all submenu items when parent is checked/unchecked
         */
        function updateSubmenuItems($parentCheckbox) {
            const isChecked = $parentCheckbox.prop('checked');
            const $menuGroup = $parentCheckbox.closest('.fhc-menu-group');
            const $subCheckboxes = $menuGroup.find('.fhc-submenu-item input[type="checkbox"]');
            
            // Check/uncheck all submenu items
            $subCheckboxes.prop('checked', isChecked);
            
            // Update group classes
            updateMenuGroupClasses($menuGroup);
        }
        
        /**
         * Function to update menu group classes based on checked state
         */
        function updateMenuGroupClasses($menuGroup) {
            const $mainCheckbox = $menuGroup.find('.fhc-menu-item input[type="checkbox"]');
            const $subCheckboxes = $menuGroup.find('.fhc-submenu-item input[type="checkbox"]');
            const hasCheckedSubmenus = $subCheckboxes.is(':checked');
            
            // Update classes based on state
            if ($mainCheckbox.prop('checked')) {
                $menuGroup.addClass('fhc-checked');
            } else {
                $menuGroup.removeClass('fhc-checked');
            }
            
            if (hasCheckedSubmenus) {
                $menuGroup.addClass('fhc-has-checked-submenu');
            } else {
                $menuGroup.removeClass('fhc-has-checked-submenu');
            }
        }
        
        /**
         * Function to update parent checkbox state based on submenu checkboxes
         */
        function updateParentCheckbox($submenuCheckbox) {
            const $menuGroup = $submenuCheckbox.closest('.fhc-menu-group');
            const $mainCheckbox = $menuGroup.find('.fhc-menu-item input[type="checkbox"]');
            const $subCheckboxes = $menuGroup.find('.fhc-submenu-item input[type="checkbox"]');
            const allSubsChecked = $subCheckboxes.length > 0 && 
                                  $subCheckboxes.length === $subCheckboxes.filter(':checked').length;
            
            // Update parent checkbox state
            $mainCheckbox.prop('checked', allSubsChecked);
            
            // Update group classes
            updateMenuGroupClasses($menuGroup);
        }
        
        /**
         * Function to update toggle all checkbox state
         */
        function updateToggleAllCheckbox() {
            const $allCheckboxes = $('.fhc-menu-item input[type="checkbox"], .fhc-submenu-item input[type="checkbox"]');
            const $checkedCheckboxes = $allCheckboxes.filter(':checked');
            const allChecked = $allCheckboxes.length > 0 && 
                              $checkedCheckboxes.length === $allCheckboxes.length;
            
            $('#fhc_toggle_all').prop('checked', allChecked);
            $('#fhc_toggle_all_text').text(allChecked ? 'Uncheck All' : 'Check All');
        }
        
        /**
         * Initialize the menu items state
         */
        function initializeMenuItems() {
            // First ensure parent checkboxes are properly reflected in their submenu items
            $('.fhc-menu-group').each(function() {
                const $menuGroup = $(this);
                const $mainCheckbox = $menuGroup.find('.fhc-menu-item input[type="checkbox"]');
                
                if ($mainCheckbox.is(':checked')) {
                    // Check all submenu items if parent is checked
                    const $subCheckboxes = $menuGroup.find('.fhc-submenu-item input[type="checkbox"]');
                    $subCheckboxes.prop('checked', true);
                    $menuGroup.addClass('fhc-checked fhc-has-checked-submenu');
                } else {
                    // Check if any submenu items are checked
                    const $checkedSubmenus = $menuGroup.find('.fhc-submenu-item input[type="checkbox"]:checked');
                    if ($checkedSubmenus.length > 0) {
                        $menuGroup.addClass('fhc-has-checked-submenu');
                        
                        // If all submenu items are checked, check the parent too
                        const $allSubmenus = $menuGroup.find('.fhc-submenu-item input[type="checkbox"]');
                        if ($checkedSubmenus.length === $allSubmenus.length && $allSubmenus.length > 0) {
                            $mainCheckbox.prop('checked', true);
                            $menuGroup.addClass('fhc-checked');
                        }
                    }
                }
            });
            
            // Update toggle all checkbox
            updateToggleAllCheckbox();
        }
        
        // SETUP EVENT HANDLERS
        
        // Main menu checkbox change
        $(document).on('change', '.fhc-menu-item input[type="checkbox"]', function() {
            updateSubmenuItems($(this));
            updateToggleAllCheckbox();
        });
        
        // Submenu checkbox change
        $(document).on('change', '.fhc-submenu-item input[type="checkbox"]', function() {
            updateParentCheckbox($(this));
            updateToggleAllCheckbox();
        });
        
        // Toggle All checkbox
        $('#fhc_toggle_all').on('change', function() {
            const isChecked = $(this).prop('checked');
            
            // Update text
            $('#fhc_toggle_all_text').text(isChecked ? 'Uncheck All' : 'Check All');
            
            // Check/uncheck all checkboxes
            $('.fhc-menu-item input[type="checkbox"], .fhc-submenu-item input[type="checkbox"]').prop('checked', isChecked);
            
            // Update all menu group classes
            $('.fhc-menu-group').each(function() {
                updateMenuGroupClasses($(this));
            });
        });
        
        // Form submission handler
        $('form').on('submit', function() {
            // Disable Toggle All checkbox to exclude it from submission
            $('#fhc_toggle_all').attr('disabled', true);
            
            // Disable unchecked checkboxes
            $('input[type="checkbox"]:not(:checked)').attr('disabled', true);
        });
        
        // Google Tag Manager settings
        $('#fhc_gtm_exclude_logged_in').on('change', function() {
            if ($(this).prop('checked')) {
                $('.fhc-exclude-roles-row').show();
            } else {
                $('.fhc-exclude-roles-row').hide();
            }
        });
        
        // Select all/none roles
        $('.fhc-select-all-roles').on('click', function(e) {
            e.preventDefault();
            $('.fhc-roles-grid input[type="checkbox"]').prop('checked', true);
        });
        
        $('.fhc-select-none-roles').on('click', function(e) {
            e.preventDefault();
            $('.fhc-roles-grid input[type="checkbox"]').prop('checked', false);
        });
        
        // Initialize on page load
        initializeMenuItems();
    });
})(jQuery);
