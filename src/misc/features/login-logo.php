<?php
/**
 * Custom Login Logo Feature.
 *
 * @package Focal_Haus_Core
 * @subpackage Misc
 */

namespace FocalHaus\misc\features;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class for handling the custom login logo feature.
 */
class Login_Logo extends Feature_Base {
    
    /**
     * Feature identifier
     *
     * @var string
     */
    protected $id = 'login_logo';
    
    /**
     * Feature setting key in options array
     *
     * @var string
     */
    protected $setting_key = 'custom_login_logo';
    
    /**
     * Initialize the feature.
     */
    public function init_feature() {
        // Only initialize if we have a logo URL set
        $settings = $this->parent->get_settings();
        if (!empty($settings['login_logo_url'])) {
            add_action('login_head', array($this, 'custom_login_logo'));
        }
    }
    
    /**
     * Render the settings field.
     */
    public function render_settings_field() {
        $settings = $this->parent->get_settings();
        ?>
        <tr>
            <th scope="row">
                <label for="fhc_custom_login_logo">
                    <?php esc_html_e('Change WordPress Login Logo', 'focal-haus-core'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="fhc_misc_settings[custom_login_logo]" 
                           id="fhc_custom_login_logo"
                           value="1" 
                           <?php checked(isset($settings['custom_login_logo']) && $settings['custom_login_logo']); ?> />
                    <?php esc_html_e('Use custom logo on the WordPress login page.', 'focal-haus-core'); ?>
                </label>
                
                <div class="fhc-media-uploader" style="margin-top: 10px;">
                    <input type="hidden" name="fhc_misc_settings[login_logo_url]" id="fhc_login_logo_url" value="<?php echo esc_attr($settings['login_logo_url']); ?>" />
                    
                    <div class="fhc-logo-preview" style="margin-bottom: 10px; max-width: 150px;">
                        <?php if (!empty($settings['login_logo_url'])): ?>
                            <img src="<?php echo esc_url($settings['login_logo_url']); ?>" style="max-width: 100%; height: auto;" />
                        <?php endif; ?>
                    </div>
                    
                    <input type="button" id="fhc_logo_upload_button" class="button" value="<?php esc_attr_e('Select Logo', 'focal-haus-core'); ?>" />
                    <?php if (!empty($settings['login_logo_url'])): ?>
                        <input type="button" id="fhc_logo_remove_button" class="button" value="<?php esc_attr_e('Remove Logo', 'focal-haus-core'); ?>" />
                    <?php endif; ?>
                    
                    <p class="description">
                        <?php esc_html_e('Select an image to use as the login page logo. Recommended size: 300px wide.', 'focal-haus-core'); ?>
                    </p>
                </div>
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Media uploader
                    $('#fhc_logo_upload_button').click(function(e) {
                        e.preventDefault();
                        
                        var image_frame;
                        
                        if (image_frame) {
                            image_frame.open();
                        }
                        
                        // Define image_frame as wp.media object
                        image_frame = wp.media({
                            title: '<?php esc_attr_e('Select Media', 'focal-haus-core'); ?>',
                            multiple: false,
                            library: {
                                type: 'image'
                            }
                        });
                        
                        image_frame.on('select', function() {
                            // Get media attachment details from the frame state
                            var attachment = image_frame.state().get('selection').first().toJSON();
                            
                            // Send the attachment URL to our custom image input field.
                            $('#fhc_login_logo_url').val(attachment.url);
                            
                            // Update preview
                            $('.fhc-logo-preview').html('<img src="' + attachment.url + '" style="max-width: 100%; height: auto;" />');
                            
                            // Show remove button
                            if (!$('#fhc_logo_remove_button').length) {
                                $('#fhc_logo_upload_button').after(' <input type="button" id="fhc_logo_remove_button" class="button" value="<?php esc_attr_e('Remove Logo', 'focal-haus-core'); ?>" />');
                            }
                        });
                        
                        // Open media uploader
                        image_frame.open();
                    });
                    
                    // Remove image
                    $(document).on('click', '#fhc_logo_remove_button', function(e) {
                        e.preventDefault();
                        $('#fhc_login_logo_url').val('');
                        $('.fhc-logo-preview').html('');
                        $(this).remove();
                    });
                });
                </script>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Customize the WordPress login page logo.
     *
     */
    public function custom_login_logo() {
        $settings = $this->parent->get_settings();
        $logo_url = esc_url($settings['login_logo_url']);
        if (empty($logo_url)) return;
        
        echo '<style type="text/css">
        h1 a { 
            background-image: url(' . $logo_url . ') !important;
            background-size: contain !important;
            width: 300px !important;
            height: 200px !important;
            max-width: 300px !important;
        }
        </style>';
    }
}
