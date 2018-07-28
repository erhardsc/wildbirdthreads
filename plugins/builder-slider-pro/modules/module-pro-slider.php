<?php
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

/**
 * Module Name: Slider Pro
 */

class TB_Pro_Slider_Module extends Themify_Builder_Component_Module {

    public function __construct() {
        parent::__construct(array(
            'name' => __('Slider Pro', 'builder-slider-pro'),
            'slug' => 'pro-slider'
        ));
    }

    public function get_assets() {
        $instance = Builder_Pro_Slider::get_instance();
        return array(
            'selector' => '.module-pro-slider',
            'css' => themify_enque($instance->url . 'assets/style.css'),
            'js' => themify_enque($instance->url . 'assets/scripts.js'),
            'ver' => $instance->version,
            'external' => Themify_Builder_Model::localize_js('builderSliderPro', apply_filters('builder_slider_pro_script_vars', array(
                'height_ratio' => '1.9',
                'url' => $instance->url . 'assets/'
            )))
        );
    }

    public function get_options() {
        return array(
            array(
                'id' => 'mod_title_slider',
                'type' => 'text',
                'label' => __('Module Title', 'builder-slider-pro'),
                'class' => 'large',
                'render_callback' => array(
                    'binding' => 'live'
                )
            ),
            array(
                'id' => 'builder_slider_pro_slides',
                'type' => 'builder',
                'options' => array(
                    array(
                        'id' => 'builder_ps_layout',
                        'type' => 'layout',
                        'mode'=>'sprite',
                        'label' => __('Slide Layout', 'builder-slider-pro'),
                        'options' => array(
                            array('img' => 'image-left', 'value' => 'bsp-slide-content-left', 'label' => __('Left Aligned', 'builder-slider-pro')),
                            array('img' => 'image-center', 'value' => 'bsp-slide-content-center', 'label' => __('Center Aligned', 'builder-slider-pro')),
                            array('img' => 'image-right', 'value' => 'bsp-slide-content-right', 'label' => __('Right Aligned', 'builder-slider-pro')),
                        ),
                        'default' => 'bsp-slide-content-center',
                        'render_callback' => array(
                            'binding' => 'live',
                            'repeater' => 'builder_slider_pro_slides'
                        )
                    ),
                    array(
                        'id' => 'builder_ps_slide_type',
                        'type' => 'select',
                        'label' => __('Slide Background Type', 'builder-slider-pro'),
                        'options' => array(
                            'Image' => __('Image', 'builder-slider-pro'),
                            'Video' => __('Video', 'builder-slider-pro'),
                        ),
                        'default' => 'Image',
                        'render_callback' => array(
                            'binding' => 'live',
                            'repeater' => 'builder_slider_pro_slides'
                        ),
                        'option_js' => true,
                        'binding' => array(
                            'Image' => array(
                                'show' => array('tb-group-element-Image'),
                                'hide' => array('tb-group-element-Video')),
                            'Video' => array(
                                'show' => array('tb-group-element-Video'),
                                'hide' => array('tb-group-element-Image')),
                        ),
                    ),
                    array(
                        'id' => 'builder_ps_bg_option',
                        'type' => 'multi',
                        'label' => __('Slide Image Background', 'builder-slider-pro'),
                        'options' => array(
                            array(
                                'id' => 'builder-ps-bg-color',
                                'type' => 'text',
                                'colorpicker' => true,
                                'label' => false,
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder-ps-bg-image',
                                'type' => 'image',
                                'label' => false,
                                'class' => 'large',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                ),
                                'wrap_with_class' => 'tb-group-element-Image'
                            ),
                        )
                    ),
                    array(
                        'id' => 'builder_ps_vbg_option',
                        'type' => 'text',
                        'label' => __('Video URL', 'builder-slider-pro'),
                        'class' => 'fullwidth',
                        'help' => array('text' => __('YouTube, Vimeo, etc. video <a href="https://themify.me/docs/video-embeds" target="_blank">embed link</a>', 'builder-slider-pro')),
                        'render_callback' => array(
                            'binding' => 'live',
                            'repeater' => 'builder_slider_pro_slides'
                        ),
                        'wrap_with_class' => 'tb-group-element-Video'
                    ),
                    array(
                        'id' => 'builder-ps-slide-image',
                        'type' => 'image',
                        'label' => __('Slide Content Image', 'builder-slider-pro'),
                        'class' => 'large',
                        'help' => array('text' => __('Image will appear on the right/left side (depending of slide layout) Slider container')),
                        'render_callback' => array(
                            'binding' => 'live',
                            'repeater' => 'builder_slider_pro_slides'
                        )
                    ),
                    array(
                        'id' => 'builder_ps_heading',
                        'type' => 'text',
                        'label' => __('Slide Heading', 'builder-slider-pro'),
                        'class' => 'fullwidth',
                        'render_callback' => array(
                            'binding' => 'live',
                            'repeater' => 'builder_slider_pro_slides'
                        )
                    ),
                    array(
                        'id' => 'builder_ps_text',
                        'type' => 'wp_editor',
                        'label' => false,
                        'class' => 'fullwidth',
                        'rows' => 1,
                        'render_callback' => array(
                            'binding' => 'live',
                            'repeater' => 'builder_slider_pro_slides'
                        )
                    ),
                    array(
                        'id' => 'builder_ps_text_option',
                        'type' => 'multi',
                        'label' => __('Slide Text', 'builder-slider-pro'),
                        'options' => array(
                            array(
                                'id' => 'builder_ps_text_color',
                                'type' => 'text',
                                'colorpicker' => true,
                                'label' => __('Color', 'builder-slider-pro'),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_text_link_color',
                                'type' => 'text',
                                'colorpicker' => true,
                                'label' => __('Link Color', 'builder-slider-pro'),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            )
                        )
                    ),
                    array(
                        'id' => 'builder-ps-button-option',
                        'type' => 'multi',
                        'label' => __('Action Button', 'builder-slider-pro'),
                        'options' => array(
                            array(
                                'id' => 'builder_ps_button_text',
                                'type' => 'text',
                                'label' => 'Text',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_button_link',
                                'type' => 'text',
                                'label' => 'Link',
                                'class' => '',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                        ),
                        'separated' => 'top'
                    ),
                    array(
                        'id' => 'builder-ps-button-option2',
                        'type' => 'multi',
                        'label' => __('Button', 'builder-slider-pro'),
                        'options' => array(
                            array(
                                'id' => 'builder_ps_button_icon',
                                'type' => 'text',
                                'iconpicker' => true,
                                'label' => __('Icon', 'builder-slider-pro'),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_button_color',
                                'type' => 'text',
                                'label' => 'Color',
                                'colorpicker' => true,
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_button_bg',
                                'type' => 'text',
                                'label' => 'Background',
                                'colorpicker' => true,
                                'class' => '',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                        )
                    ),
                    array(
                        'separated' => 'top',
                        'id' => 'builder-ps-button-option2',
                        'type' => 'multi',
                        'label' => __('Slide Transition', 'builder-slider-pro'),
                        'options' => array(
                            array(
                                'id' => 'builder_ps_tranzition',
                                'type' => 'select',
                                'label' => __('Select', 'builder-slider-pro'),
                                'options' => array(
                                    'slideTop' => __('Slide to Top', 'builder-slider-pro'),
                                    'slideBottom' => __('Slide to Bottom', 'builder-slider-pro'),
                                    'slideLeft' => __('Slide to Left', 'builder-slider-pro'),
                                    'slideRight' => __('Slide to Right', 'builder-slider-pro'),
                                    'slideTopFade' => __('Fade and Slide from Top', 'builder-slider-pro'),
                                    'slideBottomFade' => __('Fade and Slide from Bottom', 'builder-slider-pro'),
                                    'slideLeftFade' => __('Fade and Slide from Left', 'builder-slider-pro'),
                                    'slideRightFade' => __('Fade and Slide from Right', 'builder-slider-pro'),
                                    'fade' => __('Fade', 'builder-slider-pro'),
                                    'zoomOut' => __('Zoom Out', 'builder-slider-pro'),
                                    'zoomTop' => __('Zoom Out and slide from Top', 'builder-slider-pro'),
                                    'zoomBottom' => __('Zoom Out and slide from Bottom', 'builder-slider-pro'),
                                    'zoomLeft' => __('Zoom Out and slide from Left', 'builder-slider-pro'),
                                    'zoomRight' => __('Zoom Out and slide from Right', 'builder-slider-pro'),
                                ),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_tranzition_duration',
                                'type' => 'select',
                                'label' => __('Speed', 'builder-slider-pro'),
                                'options' => array(
                                    'fast' => __('Fast', 'builder-slider-pro'),
                                    'normal' => __('Normal', 'builder-slider-pro'),
                                    'slow' => __('Slow', 'builder-slider-pro')
                                ),
                                'default' => 'normal',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                ),
                            )
                        )
                    ),
                    array(
                        'separated' => 'top',
                        'id' => 'builder_ps_animation',
                        'type' => 'multi',
                        'label' => __('Slide Title', 'builder-slider-pro'),
                        'options' => array(
                            array(
                                'id' => 'builder_ps_h3s_timer',
                                'type' => 'select',
                                'label' => __('Start Animation', 'builder-slider-pro'),
                                'pushed' => 'pushed',
                                'options' => array(
                                    // 'fade' => __('Fade', 'builder-slider-pro'),
                                    'shortTop' => __('Short from Top', 'builder-slider-pro'),
                                    'shortBottom' => __('Short from Bottom', 'builder-slider-pro'),
                                    'shortLeft' => __('Short from Left', 'builder-slider-pro'),
                                    'shortRight' => __('Short from Right', 'builder-slider-pro'),
                                ),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_h3s_tranzition_duration',
                                'type' => 'select',
                                'label' => __('Speed', 'builder-slider-pro'),
                                'options' => array(
                                    'fast' => __('Fast', 'builder-slider-pro'),
                                    'normal' => __('Normal', 'builder-slider-pro'),
                                    'slow' => __('Slow', 'builder-slider-pro')
                                ),
                                'default' => 'normal',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                ),
                            ),
                            array(
                                'id' => 'builder_ps_h3e_timer',
                                'type' => 'select',
                                'label' => __('End Animation', 'builder-slider-pro'),
                                'pushed' => 'pushed',
                                'options' => array(
                                    // 'fadeOut' => __('Fade Out', 'builder-slider-pro'),
                                    'shortTopOut' => __('Short to Top', 'builder-slider-pro'),
                                    'shortBottomOut' => __('Short to Bottom', 'builder-slider-pro'),
                                    'shortLeftOut' => __('Short to Left', 'builder-slider-pro'),
                                    'shortRightOut' => __('Short to Right', 'builder-slider-pro'),
                                ),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_h3e_tranzition_duration',
                                'type' => 'select',
                                'label' => __('Speed', 'builder-slider-pro'),
                                'options' => array(
                                    'fast' => __('Fast', 'builder-slider-pro'),
                                    'normal' => __('Normal', 'builder-slider-pro'),
                                    'slow' => __('Slow', 'builder-slider-pro')
                                ),
                                'default' => 'normal',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                ),
                            ),
                        )
                    ),
                    array(
                        'id' => 'builder_ps_animation',
                        'type' => 'multi',
                        'label' => __('Slide Text', 'builder-slider-pro'),
                        'options' => array(
                            array(
                                'id' => 'builder_ps_ps_timer',
                                'type' => 'select',
                                'label' => '',
                                'pushed' => 'pushed',
                                'options' => array(
                                    // 'fade' => __('Fade', 'builder-slider-pro'),
                                    'shortTop' => __('Short from Top', 'builder-slider-pro'),
                                    'shortBottom' => __('Short from Bottom', 'builder-slider-pro'),
                                    'shortLeft' => __('Short from Left', 'builder-slider-pro'),
                                    'shortRight' => __('Short from Right', 'builder-slider-pro'),
                                ),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_ps_tranzition_duration',
                                'type' => 'select',
                                'label' => false,
                                'options' => array(
                                    'fast' => __('Fast', 'builder-slider-pro'),
                                    'normal' => __('Normal', 'builder-slider-pro'),
                                    'slow' => __('Slow', 'builder-slider-pro')
                                ),
                                'default' => 'normal',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                ),
                            ),
                            array(
                                'id' => 'builder_ps_pe_timer',
                                'type' => 'select',
                                'label' => '',
                                'pushed' => 'pushed',
                                'options' => array(
                                    // 'fadeOut' => __('Fade Out', 'builder-slider-pro'),
                                    'shortTopOut' => __('Short to Top', 'builder-slider-pro'),
                                    'shortBottomOut' => __('Short to Bottom', 'builder-slider-pro'),
                                    'shortLeftOut' => __('Short to Left', 'builder-slider-pro'),
                                    'shortRightOut' => __('Short to Right', 'builder-slider-pro'),
                                ),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_pe_tranzition_duration',
                                'type' => 'select',
                                'label' => false,
                                'options' => array(
                                    'fast' => __('Fast', 'builder-slider-pro'),
                                    'normal' => __('Normal', 'builder-slider-pro'),
                                    'slow' => __('Slow', 'builder-slider-pro')
                                ),
                                'default' => 'normal',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                ),
                            ),
                        )
                    ),
                    array(
                        'id' => 'builder_ps_animation',
                        'type' => 'multi',
                        'label' => __('Slide Action Button', 'builder-slider-pro'),
                        'options' => array(
                            array(
                                'id' => 'builder_ps_as_timer',
                                'type' => 'select',
                                'label' => '',
                                'pushed' => 'pushed',
                                'options' => array(
                                    // 'fade' => __('Fade', 'builder-slider-pro'),
                                    'shortTop' => __('Short from Top', 'builder-slider-pro'),
                                    'shortBottom' => __('Short from Bottom', 'builder-slider-pro'),
                                    'shortLeft' => __('Short from Left', 'builder-slider-pro'),
                                    'shortRight' => __('Short from Right', 'builder-slider-pro'),
                                ),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_as_tranzition_duration',
                                'type' => 'select',
                                'label' => false,
                                'options' => array(
                                    'fast' => __('Fast', 'builder-slider-pro'),
                                    'normal' => __('Normal', 'builder-slider-pro'),
                                    'slow' => __('Slow', 'builder-slider-pro')
                                ),
                                'default' => 'normal',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                ),
                            ),
                            array(
                                'id' => 'builder_ps_ae_timer',
                                'type' => 'select',
                                'label' => '',
                                'pushed' => 'pushed',
                                'options' => array(
                                    // 'fadeOut' => __('Fade Out', 'builder-slider-pro'),
                                    'shortTopOut' => __('Short to Top', 'builder-slider-pro'),
                                    'shortBottomOut' => __('Short to Bottom', 'builder-slider-pro'),
                                    'shortLeftOut' => __('Short to Left', 'builder-slider-pro'),
                                    'shortRightOut' => __('Short to Right', 'builder-slider-pro'),
                                ),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_ae_tranzition_duration',
                                'type' => 'select',
                                'label' => false,
                                'options' => array(
                                    'fast' => __('Fast', 'builder-slider-pro'),
                                    'normal' => __('Normal', 'builder-slider-pro'),
                                    'slow' => __('Slow', 'builder-slider-pro')
                                ),
                                'default' => 'normal',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                ),
                            ),
                        )
                    ),
                    array(
                        'id' => 'builder_ps_animation',
                        'type' => 'multi',
                        'label' => __('Slide Content Image', 'builder-slider-pro'),
                        'options' => array(
                            array(
                                'id' => 'builder_ps_imgs_timer',
                                'type' => 'select',
                                'label' => '',
                                'pushed' => 'pushed',
                                'options' => array(
                                    // 'fade' => __('Fade', 'builder-slider-pro'),
                                    'shortTop' => __('Short from Top', 'builder-slider-pro'),
                                    'shortBottom' => __('Short from Bottom', 'builder-slider-pro'),
                                    'shortLeft' => __('Short from Left', 'builder-slider-pro'),
                                    'shortRight' => __('Short from Right', 'builder-slider-pro'),
                                ),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_imgs_tranzition_duration',
                                'type' => 'select',
                                'label' => false,
                                'options' => array(
                                    'fast' => __('Fast', 'builder-slider-pro'),
                                    'normal' => __('Normal', 'builder-slider-pro'),
                                    'slow' => __('Slow', 'builder-slider-pro')
                                ),
                                'default' => 'normal',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                ),
                            ),
                            array(
                                'id' => 'builder_ps_imge_timer',
                                'type' => 'select',
                                'label' => '',
                                'pushed' => 'pushed',
                                'options' => array(
                                    // 'fadeOut' => __('Fade Out', 'builder-slider-pro'),
                                    'shortTopOut' => __('Short to Top', 'builder-slider-pro'),
                                    'shortBottomOut' => __('Short to Bottom', 'builder-slider-pro'),
                                    'shortLeftOut' => __('Short to Left', 'builder-slider-pro'),
                                    'shortRightOut' => __('Short to Right', 'builder-slider-pro'),
                                ),
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                )
                            ),
                            array(
                                'id' => 'builder_ps_imge_tranzition_duration',
                                'type' => 'select',
                                'label' => false,
                                'options' => array(
                                    'fast' => __('Fast', 'builder-slider-pro'),
                                    'normal' => __('Normal', 'builder-slider-pro'),
                                    'slow' => __('Slow', 'builder-slider-pro')
                                ),
                                'default' => 'normal',
                                'render_callback' => array(
                                    'binding' => 'live',
                                    'repeater' => 'builder_slider_pro_slides'
                                ),
                            ),
                        )
                    ),
                ),
                'render_callback' => array(
                    'binding' => 'live',
                    'control_type' => 'repeater'
                )
            ),
            array(
                'type' => 'separator',
                'meta' => array('html' => '<hr/>')
            ),
            array(
                'id' => 'builder_ps_triggers_position',
                'type' => 'radio',
                'label' => __('Slider Pager', 'builder-slider-pro'),
                'options' => array(
                    'standard' => __('Default (overlap)', 'builder-slider-pro'),
                    'below' => __('Below', 'builder-slider-pro'),
                    'none' => __('No pager', 'builder-slider-pro'),
                ),
                'default' => 'standard',
                'render_callback' => array(
                    'binding' => 'live'
                )
            ),
            array(
                'id' => 'builder_ps_triggers_type',
                'type' => 'radio',
                'label' => __('Pager design', 'builder-slider-pro'),
                'options' => array(
                    'circle' => __('Circle', 'builder-slider-pro'),
                    'thumb' => __('Photo Thumb', 'builder-slider-pro'),
                    'square' => __('Square', 'builder-slider-pro'),
                ),
                'default' => 'circle',
                'option_js' => true,
                'render_callback' => array(
                    'binding' => 'live'
                )
            ),
            array(
                'id' => 'builder_ps_aa',
                'type' => 'select',
                'label' => __('Auto advance to next slide', 'builder-slider-pro'),
                'options' => array(
                    'off' => __('Off', 'builder-slider-pro'),
                    '2000' => __('2 Seconds', 'builder-slider-pro'),
                    '3000' => __('3 Seconds', 'builder-slider-pro'),
                    '4000' => __('4 Seconds', 'builder-slider-pro'),
                    '5000' => __('5 Seconds', 'builder-slider-pro'),
                    '6000' => __('6 Seconds', 'builder-slider-pro'),
                    '7000' => __('7 Seconds', 'builder-slider-pro'),
                    '8000' => __('8 Seconds', 'builder-slider-pro'),
                    '9000' => __('9 Seconds', 'builder-slider-pro'),
                    '10000' => __('10 Seconds', 'builder-slider-pro'),
                ),
                'separated' => 'top',
                'render_callback' => array(
                    'binding' => 'live'
                )
            ),
            array(
                'id' => 'builder_ps_hover_pause',
                'type' => 'select',
                'label' => __('Pause Hover', 'builder-slider-pro'),
                'options' => array(
                    'none' => __('Continue the autoplay', 'builder-slider-pro'),
                    'pause' => __('Pause the autoplay', 'builder-slider-pro'),
                    'stop' => __('Stop the autoplay', 'builder-slider-pro'),
                ),
                'render_callback' => array(
                    'binding' => 'live'
                )
            ),
            array(
                'id' => 'builder_ps_timer',
                'type' => 'checkbox',
                'label' => false,
                'pushed' => 'pushed',
                'options' => array(
                    array('name' => 'yes', 'value' => __('Show timer bar', 'builder-slider-pro')),
                ),
                'render_callback' => array(
                    'binding' => 'live'
                )
            ),
            array(
                'id' => 'builder_ps_size',
                'type' => 'multi',
                'label' => __('Slider Size', 'builder-slider-pro'),
                'wrap_with_class' => 'tb-checkbox-element tb-checkbox-element-fullscreen',
                'fields' => array(
                    array(
                        'id' => 'builder_ps_width',
                        'type' => 'text',
                        'label' => __('Slider Width', 'builder-slider-pro'),
                        'help' => __('px', 'builder-slider-pro'),
                        'class' => 'medium',
                        'render_callback' => array(
                            'binding' => 'live'
                        )
                    ),
                    array(
                        'id' => 'builder_ps_height',
                        'type' => 'text',
                        'label' => __('Slider Height', 'builder-slider-pro'),
                        'help' => __('px', 'builder-slider-pro'),
                        'class' => 'medium',
                        'render_callback' => array(
                            'binding' => 'live'
                        )
                    ),
                )
            ),
            array(
                'id' => 'builder_ps_size_help',
                'type' => 'help',
                'label' => false,
                'wrap_with_class' => 'tb-checkbox-element tb-checkbox-element-fullscreen',
                'pushed' => 'pushed',
                'help' => __('Default slider is auto fullwidth, so it displays the slider fullwidth and scales it proportionally. To achieve custom dimension, enter the slider width and height (e.g. enter width=1160px and height=600px).', 'builder-slider-pro')
            ),
	        array(
		        'id' => 'builder_ps_fullscreen',
		        'type' => 'checkbox',
		        'label' => false,
		        'pushed' => 'pushed',
		        'options' => array(
			        array('name' => 'fullscreen', 'value' => __('Enable fullscreen slider (100% width & height)', 'builder-slider-pro')),
		        ),
		        'option_js' => true,
		        'reverse' => true,
		        'render_callback' => array(
			        'binding' => 'live'
		        )
	        ),
            array(
                'id' => 'builder_ps_thumb_size',
                'type' => 'multi',
                'label' => __('Thumbnail Size', 'builder-slider-pro'),
                'fields' => array(
                    array(
                        'id' => 'builder_ps_thumb_width',
                        'type' => 'text',
                        'label' => __('Thumbnail Width', 'builder-slider-pro'),
                        'help' => __('px', 'builder-slider-pro'),
                        'class' => 'medium',
                        'render_callback' => array(
                            'binding' => 'live'
                        )
                    ),
                    array(
                        'id' => 'builder_ps_thumb_height',
                        'type' => 'text',
                        'label' => __('Thumbnail Height', 'builder-slider-pro'),
                        'help' => __('px', 'builder-slider-pro'),
                        'class' => 'medium',
                        'render_callback' => array(
                            'binding' => 'live'
                        )
                    ),
                ),
                'wrap_with_class' => 'tb-group-element tb-group-element-thumb'
            ),
            // Additional CSS
            array(
                'type' => 'separator',
                'meta' => array('html' => '<hr/>')
            ),
            array(
                'id' => 'css_slider_pro',
                'type' => 'text',
                'label' => __('Additional CSS Class', 'builder-slider-pro'),
                'class' => 'large exclude-from-reset-field',
                'help' => sprintf('<br/><small>%s</small>', __('Add additional CSS class(es) for custom styling', 'builder-slider-pro')),
                'render_callback' => array(
                    'binding' => 'live'
                )
            )
        );
    }

    public function get_default_settings() {
        return array(
            'builder_ps_triggers_position' => 'standard',
            'builder_ps_triggers_type' => 'circle',
            'builder_ps_aa' => 'off',
            'builder_ps_hover_pause' => 'pause',
            'builder_ps_timer' => 'no',
            'builder_ps_thumb_width' => 30,
            'builder_ps_thumb_height' => 30,
            'builder_slider_pro_slides' => array(array(
                    'builder_ps_layout' => 'bsp-slide-content-right',
                    'builder_ps_slide_type' => 'Image',
                    'builder_ps_text_color' => 'ffffff_1',
                    'builder-ps-bg-image' => 'https://themify.me/demo/themes/themes/wp-content/uploads/addon-samples/slider-pro-bg-image.jpg',
                    'builder-ps-slide-image' => 'https://themify.me/demo/themes/themes/wp-content/uploads/addon-samples/slider-pro-content-image.png',
                    'builder_ps_heading' => esc_html__('Slide Heading', 'builder-slider-pro'),
                    'builder_ps_text' => esc_html__('Slide content', 'builder-slider-pro'),
                    'builder_ps_tranzition' => 'slideTop',
                    'builder_ps_tranzition_duration' => 'normal',
                    'builder_ps_h3s_timer' => 'shortTop',
                    'builder_ps_h3s_tranzition_duration' => 'normal',
                    'builder_ps_h3e_timer' => 'shortTop',
                    'builder_ps_h3e_tranzition_duration' => 'normal',
                    'builder_ps_ps_timer' => 'shortTop',
                    'builder_ps_ps_tranzition_duration' => 'normal',
                    'builder_ps_pe_timer' => 'shortTop',
                    'builder_ps_pe_tranzition_duration' => 'normal',
                    'builder_ps_as_timer' => 'shortTop',
                    'builder_ps_as_tranzition_duration' => 'normal',
                    'builder_ps_ae_timer' => 'shortTop',
                    'builder_ps_ae_tranzition_duration' => 'normal',
                    'builder_ps_imgs_timer' => 'shortTop',
                    'builder_ps_imgs_tranzition_duration' => 'normal',
                    'builder_ps_imge_timer' => 'shortTop',
                    'builder_ps_imge_tranzition_duration' => 'normal',
                ))
        );
    }

    public function get_styling() {
        return array(
            //bacground
            self::get_seperator('image_bacground', __('Background', 'themify'), false),
            self::get_color('.module-pro-slider', 'background_color', __('Background Color', 'themify'), 'background-color'),
            //Title 
            self::get_seperator('title', __('Title', 'builder-slider-pro'), false),
            self::get_font_family('.module-pro-slider .bsp-slide-post-title', 'title_font_family'),
            self::get_font_size('.module-pro-slider .bsp-slide-post-title', 'font_size_title'),
            self::get_line_height('.module-pro-slider .bsp-slide-post-title', 'title_line_height'),
            //Text 
            self::get_seperator('text', __('Text', 'builder-slider-pro')),
            self::get_font_family(array('.module-pro-slider .bsp-slide-excerpt', '.module-pro-slider .bsp-slide-excerpt p'), 'text_font_family'),
            self::get_font_size(array('.module-pro-slider .bsp-slide-excerpt', '.module-pro-slider .bsp-slide-excerpt p'), 'text_font_size'),
            self::get_line_height(array('.module-pro-slider .bsp-slide-excerpt', '.module-pro-slider .bsp-slide-excerpt p'), 'text_line_height'),
            //Action Button 
            self::get_seperator('action', __('Action Button', 'builder-slider-pro')),
            self::get_font_family('.module-pro-slider .bsp-slide-button', 'button_font_family'),
            self::get_font_size('.module-pro-slider .bsp-slide-button', 'font_size_button'),
            self::get_line_height('.module-pro-slider .bsp-slide-button', 'line_height_button'),
            //Timer Bar
            self::get_seperator('btn', __('Timer Bar', 'builder-slider-pro'), false),
            self::get_color('.module-pro-slider .bsp-timer-bar', 'timer_bar_background_color', null, 'background-color'),
            //Arrow
            self::get_seperator('text', __('Arrow', 'builder-slider-pro')),
			self::get_color('.module-pro-slider .sp-arrow', 'color', __('Color', 'themify'), 'color'),
            //Pagination
            self::get_seperator('text', __('Pagination', 'builder-slider-pro')),
			self::get_color('.module-pro-slider .sp-button', 'pagination_color', __('Color', 'themify'), 'color'),
			self::get_color('.module-pro-slider .sp-selected-button', 'pagination_active_color', __('Active Color', 'themify'), 'color'),
            // Padding
            self::get_seperator('padding', __('Padding', 'themify')),
            self::get_padding('.module-pro-slider'),
            // Margin
            self::get_seperator('margin', __('Margin', 'themify')),
            self::get_margin('.module-pro-slider'),
            // Border
            self::get_seperator('border', __('Border', 'themify')),
            self::get_border('.module-pro-slider')
        );
    }

    public function get_animation() {
        return array();
    }

    protected function _visual_template() {
        $module_args = self::get_module_args();
        ?>
        <#
        var timeTranslation = {
            shortTop: 'up',
            shortTopOut: 'up',
            longTop: 'up',
            longTopOut: 'up',
            shortLeft: 'left',
            shortLeftOut: 'left',
            longLeft: 'left',
            longLeftOut: 'left',
            skewShortLeft: 'left',
            skewShortLeftOut: 'left',
            skewLongLeft: 'left',
            skewLongLeftOut: 'left',
            shortBottom: 'down',
            shortBottomOut: 'down',
            longBottom: 'down',
            longBottomOut: 'down',
            shortRight: 'right',
            shortRightOut: 'right',
            longRight: 'right',
            longRightOut: 'right',
            skewShortRight: 'right',
            skewShortRightOut: 'right',
            skewLongRight: 'right',
            skewLongRightOut: 'right',
            fade: 'up',
            fadeOut: 'up'
        },
        styles = [];

        function getSpeed( type ) {
            var speed = { 'slow': 4, 'fast': '.5' };
            return speed[type] || 1;
        }
        #>

        <div class="module module-<?php echo $this->slug; ?> pager-{{data.builder_ps_triggers_position}} pager-type-{{data.builder_ps_triggers_type}} {{data.css_slider_pro}}" data-thumbnail-width="{{ data.builder_ps_thumb_width }}" data-thumbnail-height="{{ data.builder_ps_thumb_height }}" data-autoplay="{{ data.builder_ps_aa }}" data-hover-pause="{{ data.builder_ps_hover_pause }}" data-timer-bar="{{ data.builder_ps_timer }}" data-slider-width="{{ data.builder_ps_fullscreen && data.builder_ps_fullscreen == 'fullscreen' ? '100%' : data.builder_ps_width }}" data-slider-height="{{ data.builder_ps_fullscreen && data.builder_ps_fullscreen == 'fullscreen' ? '100vh' : data.builder_ps_height }}">

            <# if( data.mod_title_slider ) { #>
            <?php echo $module_args['before_title']; ?>
            {{{ data.mod_title_slider }}}
            <?php echo $module_args['after_title']; ?>
            <# } #>

            <?php do_action('themify_builder_before_template_content_render'); ?>

            <div class="themify_builder_slider_loader"></div>

            <div class="slider-pro"  style="visibility: hidden;">
                <div class="sp-slides">
                    <# if( data.builder_slider_pro_slides ) {
                    _.each( data.builder_slider_pro_slides, function( slide, index ) {
                    var isEmptySlide = ( '' == slide.builder_ps_slide_type || ( slide.builder_ps_slide_type === 'Image' && '' == slide['builder-ps-bg-image'] ) || ( slide.builder_ps_slide_type === 'Video' && '' == slide.builder_ps_vbg_option ) );
                    var slideBg = '';

                    if( !isEmptySlide && slide.builder_ps_slide_type === 'Image' && slide['builder-ps-bg-image'] ) {
                    slideBg = 'style="background-image: url( ' + slide['builder-ps-bg-image'] + ' )"';
                    }

                    if( slide['builder-ps-bg-color'] ) {
                    styles.push( '.sp-slide-' + index + ':before { background-color: ' + themifybuilderapp.Utils.toRGBA( slide['builder-ps-bg-color'] ) + '; }' );
                    }

                    if( slide.builder_ps_text_color ) {
                    styles.push( '.module-pro-slider .sp-slide-' + index + ' .bsp-slide-excerpt, .sp-slide-' + index + ' .bsp-slide-excerpt p, .module-pro-slider .sp-slide-' + index + ' .bsp-slide-post-title{ color: ' + themifybuilderapp.Utils.toRGBA( slide.builder_ps_text_color ) + '; }' );
                    }

                    if( slide.builder_ps_text_link_color ) {
                    styles.push( '.sp-slide-' + index + ' .bsp-slide-excerpt a, .sp-slide-' + index + ' .bsp-slide-excerpt p a{ color: ' + themifybuilderapp.Utils.toRGBA( slide.builder_ps_text_link_color ) + '; }' );
                    }

                    if( slide.builder_ps_button_color ) {
                    styles.push( '.sp-slide-' + index + ' a.bsp-slide-button{ color: ' + themifybuilderapp.Utils.toRGBA( slide.builder_ps_button_color ) + '; }' );
                    }

                    if( slide.builder_ps_button_bg ) {
                    styles.push( '.sp-slide-' + index + ' a.bsp-slide-button{ background-color: ' + themifybuilderapp.Utils.toRGBA( slide.builder_ps_button_bg ) + '; }' );
                    }

                    #>
                    <div class="sp-slide sp-slide-{{ index }} sp-slide-type-{{ slide.builder_ps_slide_type }} {{ slide.builder_ps_layout }} <# isEmptySlide && print( 'bsp-no-background' ) #>" data-transition="{{ slide.builder_ps_tranzition }}" data-duration="{{ getSpeed( slide.builder_ps_tranzition_duration ) }}" {{{ slideBg }}}>

                         <# if( ! isEmptySlide ) {
                         if( data.builder_ps_triggers_type === 'thumb' ) { #>
                         <img class="sp-thumbnail" src="{{ slide['builder-ps-bg-image'] }}" width="{{ data.builder_ps_thumb_width }}" height="{{ data.builder_ps_thumb_height }}">
                        <# }

                        if( slide.builder_ps_slide_type === 'Video' ) { #>
                        <div class="bsp-video" data-src="{{ slide.builder_ps_vbg_option }}"></div>
                        <iframe class="bsp-video-iframe" src="" width="100%" height="100%" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
                        <div class="post-embed"></div>
                        <# }

                        var img = '',
                        text = '';

                        if( slide['builder-ps-slide-image'] ) {
                        img = '<div class="sp-layer sp-slide-image" data-show-transition="' + timeTranslation[ slide.builder_ps_imgs_timer ] + '" data-show-duration="' + slide.builder_ps_imgs_tranzition_duration * 1000 + '" data-hide-transition="' + timeTranslation[ slide.builder_ps_imge_timer ] + '" data-hide-duration="' + slide.builder_ps_imge_tranzition_duration * 1000 + '"><img class="bsp-content-img" src="' + slide['builder-ps-slide-image'] + '" alt="" /></div>';
                        }

                        text = '<div class="sp-slide-text">';
                            if( slide.builder_ps_heading ) {
                            text += '<h3 class="sp-layer bsp-slide-post-title" data-show-transition="' + timeTranslation[ slide.builder_ps_h3s_timer ] + '" data-show-duration="' + slide.builder_ps_h3s_tranzition_duration * 1000 + '" data-hide-transition="' + timeTranslation[ slide.builder_ps_h3e_timer ] + '" data-hide-duration="' + slide.builder_ps_h3e_tranzition_duration * 1000 + '">' + slide.builder_ps_heading + '</h3>';
                            }

                            if( slide.builder_ps_text ) {
                            text += '<div class="sp-layer bsp-slide-excerpt" data-show-transition="' + timeTranslation[ slide.builder_ps_ps_timer ] + '" data-show-duration="' + slide.builder_ps_ps_tranzition_duration * 1000 + '" data-hide-transition="' + timeTranslation[ slide.builder_ps_pe_timer ] + '" data-hide-duration="' + slide.builder_ps_pe_tranzition_duration * 1000 + '">' + slide.builder_ps_text + '</div>';
                            }

                            if( slide.builder_ps_button_text && slide.builder_ps_button_link ) {
                            text += '<a class="sp-layer bsp-slide-button" href="' + slide.builder_ps_button_link + '" data-show-transition="' + timeTranslation[ slide.builder_ps_as_timer ] + '" data-show-duration="' + slide.builder_ps_as_tranzition_duration * 1000 + '" data-hide-transition="' + timeTranslation[ slide.builder_ps_ae_timer ] + '" data-hide-duration="' + slide.builder_ps_ae_tranzition_duration * 1000 + '">';

                                if( slide.builder_ps_button_icon ) {
                                text += '<i class="fa ' + slide.builder_ps_button_icon +'"></i>';
                                }

                                text += slide.builder_ps_button_text + '</a>';
                            }

                            text += '</div>';

                        text = img + text;

                        if( text ) { #>
                        <div class="bsp-layers-overlay">
                            <div class="sp-slide-wrap">
                                {{{ text }}}
                            </div><!-- .sp-slide-wrap -->
                        </div><!-- .bsp-layers-overlay -->
                        <# }
                        } #>

                    </div><!-- .sp-slide -->
                    <#

                    } );
                    } #>
                </div><!-- .sp-slides -->
            </div><!-- .slider-pro -->

            <?php do_action('themify_builder_after_template_content_render'); ?>
            <style type="text/css">{{{ styles.join('') }}}</style>
        </div>
        <?php
    }

}

Themify_Builder_Model::register_module('TB_Pro_Slider_Module');
