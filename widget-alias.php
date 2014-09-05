<?php
/**
 * Plugin Name: Widget Alias
 * Plugin URI:  http://mightyminnow.com
 * Description: Creates an alias widget so you only have to edit once.
 * Version:     1.5
 * Author:      MIGHTYminnow
 * Author URI:  http://mightyminnow.com
 * License:     GPLv2+
 */

/**
 * To-dos:
 *
 * Add <option> hover state to show aliased widget with border
 * i18n
 */


// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    _e('Hi there!  I\'m just a plugin, not much I can do when called directly.', 'widget-alias');
    exit;
}

// Definitions
define( 'WA_PLUGIN_NAME', 'Widget Alias' );
define( 'WA_PLUGIN_VERSION', '1.5' );


/**
 * Enqueue jQuery
 */
function enqueue_admin_scripts() {

    wp_enqueue_style( 'widget-alias-css', plugin_dir_url( __FILE__ ) . 'lib/css/widget-alias.css' );
    wp_enqueue_script( 'widget-alias-jquery', plugin_dir_url( __FILE__ ) . 'lib/js/widget-alias.js', array('jquery'), WA_PLUGIN_VERSION, true );

    // Load plugin text domain
    load_plugin_textdomain( 'widget-alias', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    // jQuery strings for translation
    $translation_array = array( 
        'aliasCountText' => _x( 'This widget is aliased', 'Text preceeding alias count', 'widget-alias' ),
        'timeSing' => _x( 'time', 'singular of "time"', 'widget-alias' ),
        'timePlur' => _x( 'times', 'plural of "time"', 'widget-alias' ),
    );
    wp_localize_script( 'widget-alias-jquery', 'translations', $translation_array );

}
add_action( 'admin_enqueue_scripts','enqueue_admin_scripts' );

/**
 * Registers widget and loads plugin text domain
 *
 * @package Widget Alias
 * @since   1.0
 */
function wa_init() {

    // Register widget
    register_widget( 'WidgetAlias');

}
add_action( 'widgets_init', 'wa_init' );

/**
 * The main Super Simple Related Posts Widget class
 *
 * @package Widget Alias
 * @since   1.0
 */
class WidgetAlias extends WP_Widget {
    
    // Object variables
    public $override_title, $alias_id;

    /**
     * PHP5 constructor - initializes widget and sets defaults
     *
     * @package Widget Alias
     * @since   1.0
     */
    public function __construct() {
        
        // Set widget options
        $widget_options = array(
            'classname' => 'widget-alias',
            'description' => __('An alias widget to reproduce the output of an existing widget.', 'widget-alias') );
            parent::WP_Widget('widget-alias', __('Widget Alias', 'widget-alias'), $widget_options
        );

        // Add shortcode to output specific widget [wa title="title"]
        add_shortcode( 'wa', array( $this, 'wa_shortcode' ) );

    }
    
    /**
     * Output the widget settings form
     *
     * @package Widget Alias
     * @since   1.0
     *
     * @param   array $instance the current widget settings
     */
    public function form( $instance ) {

        global $wp_registered_sidebars;

        // Map all undefined $instance properties to defaults
        if ( !empty( $this->defaults ) )
            $instance = wp_parse_args( (array) $instance, $this->defaults );

        // Get all existing widgets
        $sidebar_widgets = get_option( 'sidebars_widgets' );

        // Remove 'wp_inactive_widgets' and 'array_version' key/value pairs
        unset( $sidebar_widgets['wp_inactive_widgets'] );
        unset( $sidebar_widgets['array_version'] );

        // Begin <select> element
        $select = '<select id="' . $this->get_field_id( 'alias-widget-id' ) . '" name="' . $this->get_field_name( 'alias-widget-id' ) . '" class="widefat" >' . "\n";

        // Add default option
        $select .= "\t" . '<option value="none">None</option>' . "\n";

        // Loop through each sidebar
        foreach ( $sidebar_widgets as $sidebar => $widgets ) {
          
            if ( empty( $widgets ) )
                continue;

            // Reset $sidebars_output and $widgets_output string variables
            $widgets_output = '';

            // Output <option> for each widget in sidebar (excluding this widget to avoid recursion)
            foreach( $widgets as $widget ) {
                if ( $this->id != $widget )
                    $widgets_output .= "\t" . '<option value="'. $widget . '"' . selected( !empty( $instance['alias-widget-id'] ) ? $instance['alias-widget-id'] : '', $widget, __return_false() ) . '>' . $widget . '</option>' . "\n";
            }

            // Add <optgroup> for each sidebar that has widgets
            if ( !empty( $widgets_output ) )
                $select .= "\t" . '<optgroup label="' . $wp_registered_sidebars[ $sidebar ]['name'] . '">' . "\n" . $widgets_output;

        }

        // Close <select> element
        $select .= "</select>\n";

        // Output widget form
        ?>
        
        <!-- Title -->
        <p>  
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Override title:', 'widget-alias'); ?></label>  
            <input type="text" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo !empty( $instance['title'] ) ? $instance['title'] : ''; ?>" class="widefat" />
        </p>

        <?php if ( !empty( $select ) ) : ?>
        <!-- Widget drop-down select -->
        <p>  
            <label for="<?php echo $this->get_field_id( 'alias-widget-id' ); ?>"><?php _e('Widget to alias:', 'widget-alias'); ?></label>  
            <?php echo $select; ?>
        </p>
        
        <?php
        endif;

    }
    
    /**
     * Sanitize and update widget form values
     *
     * @package Widget Alias
     * @since   1.0
     *
     * @param   array $new_instance the old widget settings
     * @param   array $old_instance the new widget settings
     */
    public function update( $new_instance, $old_instance ) {
        
        // Sanitize title
        $new_instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

        return $new_instance;
    }
    
    /**
     * Output the widget contents
     *
     * @package Widget Alias
     * @since   1.0
     *
     * @param   array $args widget display arguments including before_title, after_title, before_widget, and after_widget
     * @param   array $instance the widget settings
     *
     * @return  true if successful, false if not
     */
    public function widget( $args, $instance ) {

        global $wp_registered_sidebars, $wp_registered_widgets;

        // Get ID of this widget
        $widget_id = $args['widget_id'];

        // Get ID of widget to alias
        $alias_widget_id = isset( $instance['alias-widget-id'] ) ? $instance['alias-widget-id'] : 'none';

        // Do nothing if set to 'none'
        if ( 'none' == $alias_widget_id )
            return false;

        // Do nothing if the aliased widget is no longer active
        if ( empty( $wp_registered_widgets[ $alias_widget_id ] ) )
            return false;

        // Get alias widget object
        $alias_widget = $wp_registered_widgets[ $alias_widget_id ];

        // Get sidebar in which the alias widget lives
        $alias_widget_sidebar = '';
        $sidebar_widgets = get_option( 'sidebars_widgets' );
        
        // Remove 'wp_inactive_widgets' and 'array_version' key/value pairs
        unset( $sidebar_widgets['wp_inactive_widgets'] );
        unset( $sidebar_widgets['array_version'] );
        
        foreach ( $sidebar_widgets as $sidebar => $widgets ) {
            foreach( $widgets as $widget ) {
                if ( $alias_widget_id == $widget) {
                    $alias_widget_sidebar = $sidebar;
                }
            }
        }

        // Get alias widget's callback
        $callback = $alias_widget[ 'callback' ];
        
        // Don't ouput anything if the widget isn't assigned to a sidebar (e.g. Inactive Widgets sidebar)
        if ( empty( $alias_widget_sidebar ) )
            return false;

        // Get sidebar params
        $sidebar = $wp_registered_sidebars[ $alias_widget_sidebar ];

        $params = array_merge(
            array( array_merge( $sidebar, array('widget_id' => $alias_widget_id, 'widget_name' => $wp_registered_widgets[ $alias_widget_id ]['name'] ) ) ),
            (array) $wp_registered_widgets[ $alias_widget_id ]['params']
        );
        
        // Add appropriate class name
        $classname_ = '';
        foreach ( (array) $wp_registered_widgets[ $alias_widget_id ]['classname'] as $cn ) {
            if ( is_string($cn) )
                $classname_ .= '_' . $cn;
            elseif ( is_object($cn) )
                $classname_ .= '_' . get_class($cn);
        }
        $classname_ = ltrim($classname_, '_') . ' widget-alias';
        $params[0]['before_widget'] = sprintf( $params[0]['before_widget'], $alias_widget_id, $classname_ );

        // Add widget alias identifier for use in later title filter
        $params[0]['class'] = 'widget-alias ' . $params[0]['class'];

        // Apply filters
        $params = apply_filters( 'dynamic_sidebar_params', $params );

        // Run actions
        do_action( 'dynamic_sidebar', $alias_widget );
                
        // Modify title if override title is set
        if ( !empty( $instance['title'] ) ) {
            $this->override_title = $instance['title'];
            $this->alias_id = $alias_widget_id;
            add_filter( 'widget_display_callback', array( $this, 'output_override_title'), 99, 3 );
        }

        // Do actual widget
        if ( is_callable( $callback ) )
            call_user_func_array( $callback, $params );

        return true;

    }

    /**
     * Override the aliased widgets 
     *
     * @package Widget Alias
     * @since   1.0
     *
     * @param   array $instance settings for this particular instance of the widget
     * @param   array $widget the widget object
     * @param   array $args widget container args
     *
     * @return  $instance
     */
    function output_override_title( $instance, $widget, $args ) {
              
        // Test if widget has specail 'widget-alias' class $arg and if the widget's id matches the alias id                
        if( false !== strpos( $args['class'], 'widget') && $this->alias_id == $widget->id )
            $instance['title'] = $this->override_title;

        return $instance;

    }

    /**
     * Output widget based on shortcode
     *
     * Example usage: [wa id="widget-id" title="Override Title"]
     *
     * @package Widget Alias
     * @since   1.0
     *
     * @param   array $atts shortcode attributes
     */
    function wa_shortcode( $atts ) {

        extract( shortcode_atts( array(
          'id' => '',
          'title' => '',
        ), $atts ) );

        // Output widget with shortcode atts
        if ( !empty( $id ) ) {
            $instance = array(
                'alias-widget-id' => $id,
                'title' => $title,
            );

            // Capture and return widget output
            ob_start();
            $this->widget( '', $instance );
            return ob_get_clean();
        }

    }

}