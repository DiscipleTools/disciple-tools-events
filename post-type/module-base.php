<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.

class DT_Training_Base extends DT_Module_Base {
    public $post_type = 'trainings';
    public $module = 'trainings_base';
    public $single_name = 'Training';
    public $plural_name = 'Trainings';
    public static function post_type(){
        return 'trainings';
    }


    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        parent::__construct();
        if ( !self::check_enabled_and_prerequisites() ){
            return;
        }

        $this->single_name = __( 'Training', 'disciple-tools-training' );
        $this->plural_name = __( 'Trainings', 'disciple-tools-training' );

        //setup post type
        add_action( 'after_setup_theme', [ $this, 'after_setup_theme' ], 100 );
        add_filter( 'dt_set_roles_and_permissions', [ $this, 'dt_set_roles_and_permissions' ], 20, 1 );

        //setup tiles and fields
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields_settings' ], 10, 2 );
        add_filter( 'dt_get_post_type_settings', [ $this, 'dt_get_post_type_settings' ], 20, 2 );
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 20, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );

        // hooks
        add_filter( 'dt_post_updated_custom_handled_meta', [ $this, 'dt_post_updated_custom_handled_meta' ], 10, 2 );
        add_filter( 'dt_post_update_fields', [ $this, 'dt_post_update_fields' ], 10, 4 );
        add_filter( 'dt_post_create_fields', [ $this, 'dt_post_create_fields' ], 10, 2 );
        add_action( 'dt_post_created', [ $this, 'dt_post_created' ], 10, 3 );
        add_action( 'dt_comment_created', [ $this, 'dt_comment_created' ], 10, 4 );
        add_action( 'post_connection_added', [ $this, 'post_connection_added' ], 10, 4 );
        add_filter( 'dt_format_activity_message', [ $this, 'dt_format_activity_message' ], 10, 2 );

        add_filter( 'dt_adjust_post_custom_fields', [ $this, 'dt_adjust_post_custom_fields' ], 10, 2 );
        add_action( 'dt_render_field_for_display_template', [ $this, 'dt_render_field_for_display_template' ], 20, 4 );

        //list
        add_filter( 'dt_user_list_filters', [ $this, 'dt_user_list_filters' ], 10, 2 );
        add_filter( 'dt_filter_access_permissions', [ $this, 'dt_filter_access_permissions' ], 20, 2 );
        add_filter( 'dt_list_posts_custom_fields', [ $this, 'dt_list_posts_custom_fields' ], 10, 2 );

    }

    // setup post type

    public function after_setup_theme(){
        if ( class_exists( 'Disciple_Tools_Post_Type_Template' ) ) {
            new Disciple_Tools_Post_Type_Template( $this->post_type, $this->single_name, $this->plural_name );
        }
    }

    public function dt_set_roles_and_permissions( $expected_roles ){

        $expected_roles['training_admin'] = [
            'label' => __( 'Trainings Admin', 'disciple-tools-training' ),
            'description' => __( 'Admin access to all trainings', 'disciple-tools-training' ),
            'permissions' => [ 'access_disciple_tools' => true ]
        ];
        if ( !isset( $expected_roles['multiplier'] ) ){
            $expected_roles['multiplier'] = [
                'label' => __( 'Multiplier', 'disciple-tools-training' ),
                'permissions' => []
            ];
        }

        if ( !isset( $expected_roles['dt_admin'] ) ){
            $expected_roles['dt_admin'] = [
                'label' => __( 'Disciple.Tools Admin', 'disciple-tools-training' ),
                'description' => 'All D.T permissions',
                'permissions' => []
            ];
        }
        if ( !isset( $expected_roles['administrator'] ) ){
            $expected_roles['administrator'] = [
                'label' => __( 'Administrator', 'disciple-tools-training' ),
                'description' => 'All D.T permissions plus the ability to manage plugins.',
                'permissions' => []
            ];
        }

        foreach ( $expected_roles as $role => $role_value ){
            if ( isset( $expected_roles[$role]['permissions']['access_contacts'] ) && $expected_roles[$role]['permissions']['access_contacts'] ){
                $expected_roles[$role]['permissions']['access_' . $this->post_type] = true;
                $expected_roles[$role]['permissions']['create_' . $this->post_type] = true;
                $expected_roles[$role]['permissions']['update_' . $this->post_type] = true;
            }
        }

        if ( isset( $expected_roles['training_admin'] ) ){
            $expected_roles['training_admin']['permissions']['view_any_'.$this->post_type ] = true;
            $expected_roles['training_admin']['permissions']['update_any_'.$this->post_type ] = true;
            $expected_roles['training_admin']['permissions'][ 'dt_all_admin_' . $this->post_type] = true;
        }
        if ( isset( $expected_roles['administrator'] ) ){
            $expected_roles['administrator']['permissions'][ 'view_any_'.$this->post_type ] = true;
            $expected_roles['administrator']['permissions'][ 'update_any_'.$this->post_type ] = true;
            $expected_roles['administrator']['permissions'][ 'dt_all_admin_' . $this->post_type ] = true;
        }
        if ( isset( $expected_roles['dt_admin'] ) ){
            $expected_roles['dt_admin']['permissions'][ 'view_any_'.$this->post_type ] = true;
            $expected_roles['dt_admin']['permissions'][ 'update_any_'.$this->post_type ] = true;
            $expected_roles['dt_admin']['permissions'][ 'dt_all_admin_' . $this->post_type ] = true;
        }

        return $expected_roles;
    }

    public function dt_custom_fields_settings( $fields, $post_type ){
        if ( $post_type === 'trainings' ){
            // framework fields
            $fields['tags'] = [
                'name'        => __( 'Tags', 'disciple-tools-training' ),
                'description' => _x( 'A useful way to group related items and can be used to filter the records', 'Optional Documentation', 'disciple-tools-training' ),
                'type'        => 'tags',
                'default'     => [],
                'tile'        => 'other',
                'icon' => get_template_directory_uri() . '/dt-assets/images/tag.svg',
            ];
            $fields['follow'] = [
                'name'        => __( 'Follow', 'disciple-tools-training' ),
                'type'        => 'multi_select',
                'default'     => [],
                'section'     => 'misc',
                'hidden'      => true
            ];
            $fields['unfollow'] = [
                'name'        => __( 'Un-Follow', 'disciple-tools-training' ),
                'type'        => 'multi_select',
                'default'     => [],
                'hidden'      => true
            ];
            $fields['tasks'] = [
                'name' => __( 'Tasks', 'disciple-tools-training' ),
                'type' => 'task',
            ];
            $fields['duplicate_data'] = [
                'name' => 'Duplicates', //system string does not need translation
                'type' => 'array',
                'default' => [],
            ];
            $fields['status'] = [
                'name' => 'Status',
                'type' => 'key_select',
                'tile' => 'status',
                'default' => [
                    'new'   => [
                        'label' => _x( 'New', 'Training Status label', 'disciple-tools-training' ),
                        'description' => _x( 'New training added to the system', 'Training Status field description', 'disciple-tools-training' ),
                        'color' => '#ff9800'
                    ],
                    'proposed'   => [
                        'label' => _x( 'Proposed', 'Training Status label', 'disciple-tools-training' ),
                        'description' => _x( 'This training has been proposed and is in initial conversations', 'Training Status field description', 'disciple-tools-training' ),
                        'color' => '#ff9800'
                    ],
                    'scheduled' => [
                        'label' => _x( 'Scheduled', 'Training Status label', 'disciple-tools-training' ),
                        'description' => _x( 'This training is confirmed, on the calendar.', 'Training Status field description', 'disciple-tools-training' ),
                        'color' => '#4CAF50'
                    ],
                    'in_progress' => [
                        'label' => _x( 'In Progress', 'Training Status label', 'disciple-tools-training' ),
                        'description' => _x( 'This training is confirmed, on the calendar, or currently active.', 'Training Status field description', 'disciple-tools-training' ),
                        'color' => '#4CAF50'
                    ],
                    'complete'     => [
                        'label' => _x( 'Complete', 'Training Status label', 'disciple-tools-training' ),
                        'description' => _x( 'This training has successfully completed', 'Training Status field description', 'disciple-tools-training' ),
                        'color' => '#4CAF50'
                    ],
                    'paused'       => [
                        'label' => _x( 'Paused', 'Training Status label', 'disciple-tools-training' ),
                        'description' => _x( 'This contact is currently on hold. It has potential of getting scheduled in the future.', 'Training Status field description', 'disciple-tools-training' ),
                        'color' => '#ff9800'
                    ],
                    'closed'       => [
                        'label' => _x( 'Closed', 'Training Status label', 'disciple-tools-training' ),
                        'description' => _x( 'This training is no longer going to happen.', 'Training Status field description', 'disciple-tools-training' ),
                        'color' => '#366184',
                    ],
                ],
                'default_color' => '#366184',
                'select_cannot_be_empty' => true
            ];
            $fields['assigned_to'] = [
                'name'        => __( 'Assigned To', 'disciple-tools-training' ),
                'description' => __( 'Select the main person who is responsible for reporting on this training.', 'disciple-tools-training' ),
                'type'        => 'user_select',
                'default'     => '',
                'tile' => 'status',
                'icon' => get_template_directory_uri() . '/dt-assets/images/assigned-to.svg',
            ];
            $fields['coaches'] = [
                'name' => __( 'Training Coach / Church Planter', 'disciple-tools-training' ),
                'description' => _x( 'The person who planted and/or is coaching this training. Only one person can be assigned to a training while multiple people can be coaches / church planters of this training.', 'Optional Documentation', 'disciple-tools-training' ),
                'type' => 'connection',
                'post_type' => 'contacts',
                'p2p_direction' => 'from',
                'p2p_key' => 'trainings_to_coaches',
                'tile' => 'status',
                'icon' => get_template_directory_uri() . '/dt-assets/images/coach.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-contact.svg',
            ];


            $fields['requires_update'] = [
                'name'        => __( 'Requires Update', 'disciple-tools-training' ),
                'description' => '',
                'type'        => 'boolean',
                'default'     => false,
            ];
            $fields['video_link'] = [
                'name'        => __( 'Video Link', 'disciple-tools-training' ),
                'description' => _x( 'Link to video chat service', 'Optional Documentation', 'disciple-tools-training' ),
                'type'        => 'text',
                'default'     => time(),
                'tile' => 'details',
                'icon' => plugin_dir_url( __DIR__ ) . '/assets/icons/video-image.svg',
            ];
            $fields['training_notes'] = [
                'name'        => __( 'Notes', 'disciple-tools-training' ),
                'description' => _x( 'Notes on when the trainings will happen', 'Optional Documentation', 'disciple-tools-training' ),
                'type'        => 'text',
                'default'     => time(),
                'tile' => 'details',
                'icon' => get_template_directory_uri() . '/dt-assets/images/edit.svg',
            ];


            $fields['meeting_times'] = [
                'name' => __( 'Meeting Times', 'disciple-tools-training' ),
                'icon' => get_template_directory_uri() . '/dt-assets/images/phone.svg',
                'type' => 'datetime_series',
                'tile' => 'meeting_times',
                'customizable' => true,
                'in_create_form' => true,
                'custom_display' => true
            ];

            // @todo recurring fields
//            $fields['repeat_start'] = [
//                'name'        => __( 'End Date', 'disciple-tools-training' ),
//                'description' => _x( 'The date this training stopped meeting (if applicable).', 'Optional Documentation', 'disciple-tools-training' ),
//                'type'        => 'datetime',
//                'default'     => '',
//                'icon' => get_template_directory_uri() . '/dt-assets/images/date-end.svg',
//            ];
//            $fields['repeat_year'] = [
//                'name'        => __( 'End Date', 'disciple-tools-training' ),
//                'description' => '',
//                'type'        => 'text',
//                'default'     => '',
//                'icon' => get_template_directory_uri() . '/dt-assets/images/date-end.svg',
//            ];
//            $fields['repeat_month'] = [
//                'name'        => __( 'End Date', 'disciple-tools-training' ),
//                'description' => '',
//                'type'        => 'text',
//                'default'     => '',
//                'icon' => get_template_directory_uri() . '/dt-assets/images/date-end.svg',
//            ];
//            $fields['repeat_week'] = [
//                'name'        => __( 'End Date', 'disciple-tools-training' ),
//                'description' => '',
//                'type'        => 'text',
//                'default'     => '',
//                'icon' => get_template_directory_uri() . '/dt-assets/images/date-end.svg',
//            ];
//            $fields['repeat_day'] = [
//                'name'        => __( 'End Date', 'disciple-tools-training' ),
//                'description' => '',
//                'type'        => 'text',
//                'default'     => '',
//                'icon' => get_template_directory_uri() . '/dt-assets/images/date-end.svg',
//            ];


            // location
            $fields['location_grid'] = [
                'name'        => __( 'Locations', 'disciple-tools-training' ),
                'description' => _x( 'The general location where this contact is located.', 'Optional Documentation', 'disciple-tools-training' ),
                'type'        => 'location',
                'mapbox'    => false,
                'in_create_form' => true,
                'tile' => 'details',
                'icon' => get_template_directory_uri() . '/dt-assets/images/location.svg',
            ];
            $fields['location_grid_meta'] = [
                'name'        => __( 'Locations', 'disciple-tools-training' ), //system string does not need translation
                'description' => _x( 'The general location where this contact is located.', 'Optional Documentation', 'disciple-tools-training' ),
                'type'        => 'location_meta',
                'tile'      => 'details',
                'mapbox'    => false,
                'hidden' => true,
                'icon' => get_template_directory_uri() . '/dt-assets/images/location.svg?v=2',
            ];
            $fields['contact_address'] = [
                'name' => __( 'Address', 'disciple-tools-training' ),
                'icon' => get_template_directory_uri() . '/dt-assets/images/house.svg',
                'type' => 'communication_channel',
                'tile' => 'details',
                'mapbox'    => false,
                'customizable' => false
            ];
            if ( DT_Mapbox_API::get_key() ){
                $fields['contact_address']['custom_display'] = true;
                $fields['contact_address']['mapbox'] = true;
                unset( $fields['contact_address']['tile'] );
                $fields['location_grid']['mapbox'] = true;
                $fields['location_grid_meta']['mapbox'] = true;
                $fields['location_grid']['hidden'] = true;
                $fields['location_grid_meta']['hidden'] = false;
            }


            // connection fields
            $fields['parent_trainings'] = [
                'name' => __( 'Parent Training', 'disciple-tools-training' ),
                'description' => _x( 'A training that launched this training.', 'Optional Documentation', 'disciple-tools-training' ),
                'type' => 'connection',
                'post_type' => 'trainings',
                'p2p_direction' => 'from',
                'p2p_key' => 'trainings_to_trainings',
                'tile' => 'other',
                'icon' => get_template_directory_uri() . '/dt-assets/images/group-parent.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-group.svg',
            ];
            $fields['peer_trainings'] = [
                'name' => __( 'Peer Training', 'disciple-tools-training' ),
                'description' => _x( "A related training that isn't a parent/child in relationship. It might indicate trainings that collaborate, are about to merge, recently split, etc.", 'Optional Documentation', 'disciple-tools-training' ),
                'type' => 'connection',
                'post_type' => 'trainings',
                'p2p_direction' => 'any',
                'p2p_key' => 'trainings_to_peers',
                'tile' => 'other',
                'icon' => get_template_directory_uri() . '/dt-assets/images/group-peer.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-group.svg',
            ];
            $fields['child_trainings'] = [
                'name' => __( 'Child Training', 'disciple-tools-training' ),
                'description' => _x( 'A training that has been birthed out of this training.', 'Optional Documentation', 'disciple-tools-training' ),
                'type' => 'connection',
                'post_type' => 'trainings',
                'p2p_direction' => 'to',
                'p2p_key' => 'trainings_to_trainings',
                'tile' => 'other',
                'icon' => get_template_directory_uri() . '/dt-assets/images/group-child.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-group.svg',
            ];
            $fields['members'] = [
                'name' => __( 'Member List', 'disciple-tools-training' ),
                'description' => _x( 'The contacts who are members of this training.', 'Optional Documentation', 'disciple-tools-training' ),
                'tile' => 'relationships',
                'type' => 'connection',
                'post_type' => 'contacts',
                'p2p_direction' => 'from',
                'p2p_key' => 'trainings_to_contacts',
                'custom_display' => true,
                'connection_count_field' => [ 'post_type' => 'trainings', 'field_key' => 'member_count', 'connection_field' => 'members' ]
            ];
            $fields['leaders'] = [
                'name' => __( 'Leaders', 'disciple-tools-training' ),
                'description' => '',
                'type' => 'connection',
                'post_type' => 'contacts',
                'p2p_direction' => 'from',
                'p2p_key' => 'trainings_to_leaders',
                'connection_count_field' => [ 'post_type' => 'trainings', 'field_key' => 'leader_count', 'connection_field' => 'leaders' ]
            ];

            $fields['peoplegroups'] = [
                'name' => __( 'People Groups', 'disciple-tools-training' ),
                'description' => _x( 'The people trainings represented by this training.', 'Optional Documentation', 'disciple-tools-training' ),
                'type' => 'connection',
                'tile' => 'details',
                'post_type' => 'peoplegroups',
                'p2p_direction' => 'from',
                'p2p_key' => 'trainings_to_peoplegroups'
            ];

            $fields['groups'] = [
                'name' => __( 'Groups', 'disciple-tools-training' ),
                'type' => 'connection',
                'post_type' => 'groups',
                'p2p_direction' => 'from',
                'p2p_key' => 'trainings_to_groups',
                'tile' => 'other',
                'icon' => get_template_directory_uri() . '/dt-assets/images/group-child.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-group.svg',
            ];

            // count fields
            $fields['member_count'] = [
                'name' => __( 'Member Count', 'disciple-tools-training' ),
                'description' => _x( 'The number of members in this training. It will automatically be updated when new members are added or removed in the member list. Change this number manually to included people who may not be in the system but are also members of the training.', 'Optional Documentation', 'disciple-tools-training' ),
                'type' => 'number',
                'default' => '',
                'tile' => 'relationships',
            ];
            $fields['leader_count'] = [
                'name' => __( 'Leader Count', 'disciple-tools-training' ),
                'description' => _x( 'The number of members in this training. It will automatically be updated when new members are added or removed in the member list. Change this number manually to included people who may not be in the system but are also members of the training.', 'Optional Documentation', 'disciple-tools-training' ),
                'type' => 'number',
                'default' => '',
                'tile' => 'relationships',
            ];
        }

        if ( $post_type === 'contacts' ){
            $fields['training_leader'] = [
                'name' => __( 'Training as Leader', 'disciple-tools-training' ),
                'description' => _x( 'Leader of a training', 'Optional Documentation', 'disciple-tools-training' ),
                'type' => 'connection',
                'post_type' => $this->post_type,
                'p2p_direction' => 'to',
                'p2p_key' => 'trainings_to_leaders',
                'tile' => 'other',
                'icon' => get_template_directory_uri() . '/dt-assets/images/socialmedia.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-group.svg',
                'connection_count_field' => [ 'post_type' => 'trainings', 'field_key' => 'leader_count', 'connection_field' => 'leaders' ]
            ];
            $fields['training_participant'] = [
                'name' => __( 'Training as Participant', 'disciple-tools-training' ),
                'description' => _x( 'Participant in a training.', 'Optional Documentation', 'disciple-tools-training' ),
                'type' => 'connection',
                'post_type' => $this->post_type,
                'p2p_direction' => 'to',
                'p2p_key' => 'trainings_to_contacts',
                'tile' => 'other',
                'icon' => get_template_directory_uri() . '/dt-assets/images/socialmedia.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-group.svg',
                'connection_count_field' => [ 'post_type' => 'trainings', 'field_key' => 'member_count', 'connection_field' => 'members' ]
            ];
            $fields['training_coach'] = [
                'name' => __( 'Coach of Training', 'disciple_tools' ),
                'type' => 'connection',
                'p2p_direction' => 'to',
                'p2p_key' => 'trainings_to_coaches',
                'post_type' => 'trainings',
                'tile' => 'no_tile',
                'icon' => get_template_directory_uri() . '/dt-assets/images/coach.svg?v=2',
            ];
        }
        if ( $post_type === 'groups' ){
            $fields[$this->post_type] = [
                'name' => __( 'Trainings', 'disciple-tools-training' ),
                'type' => 'connection',
                'post_type' => $this->post_type,
                'p2p_direction' => 'to',
                'p2p_key' => 'trainings_to_groups',
                'tile' => 'other',
                'icon' => get_template_directory_uri() . '/dt-assets/images/socialmedia.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-group.svg',
            ];
        }
        if ( $post_type === 'peoplegroups' ){
            $fields[$this->post_type] = [
                'name' => __( 'Trainings', 'disciple-tools-training' ),
                'type' => 'connection',
                'post_type' => $this->post_type,
                'p2p_direction' => 'to',
                'p2p_key' => 'trainings_to_peoplegroups',
                'tile' => 'connections',
                'icon' => get_template_directory_uri() . '/dt-assets/images/socialmedia.svg',
                'create-icon' => get_template_directory_uri() . '/dt-assets/images/add-group.svg',
            ];
        }

        return $fields;
    }

    /**
     * Set the singular and plural translations for this post types settings
     * The add_filter is set onto a higher priority than the one in Disciple_tools_Post_Type_Template
     * so as to enable localisation changes. Otherwise the system translation passed in to the custom post type
     * will prevail.
     */
    public function dt_get_post_type_settings( $settings, $post_type ){
        if ( $post_type === $this->post_type ){
            $settings['label_singular'] = __( 'Training', 'disciple-tools-training' );
            $settings['label_plural'] = __( 'Trainings', 'disciple-tools-training' );
            $settings['status_field'] = [
                'status_key' => 'status',
                'archived_key' => 'closed',
            ];
        }
        return $settings;
    }


    public function dt_details_additional_tiles( $tiles, $post_type = '' ){
        if ( $post_type === 'trainings' ){
            $tiles['relationships'] = [ 'label' => __( 'Member List', 'disciple-tools-training' ) ];
            $tiles['meeting_times'] = [ 'label' => __( 'Meeting Times', 'disciple-tools-training' ) ];
            $tiles['other'] = [ 'label' => __( 'Other', 'disciple-tools-training' ) ];
        }
        return $tiles;
    }

    public function dt_details_additional_section( $section, $post_type ){
        if ( $post_type === 'trainings' ){
            $training_fields = DT_Posts::get_post_field_settings( $post_type );
            $training = DT_Posts::get_post( $post_type, get_the_ID() );

            if ( isset( $training_fields['meeting_times']['tile'] ) && $training_fields['meeting_times']['tile'] === $section ) :
                /* list */
                $field_key = 'meeting_times';
                ?>
                <div class="section-subheader">
                    <?php if ( isset( $training_fields[$field_key]['icon'] ) ) : ?>
                      <img class="dt-icon" src="<?php echo esc_url( $training_fields[$field_key]['icon'] ) ?>">
                    <?php endif;
                    echo esc_html( $training_fields[$field_key]['name'] );
                    ?> <span id="<?php echo esc_html( $field_key ); ?>-spinner" class="loading-spinner"></span>
                    <button data-list-class="<?php echo esc_html( $field_key ); ?>" class="add-time-button" type="button">
                        <img src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/small-add.svg' ) ?>"/>
                    </button>
                </div>
                <div id="edit-<?php echo esc_html( $field_key ) ?>" ></div>
                <?php
                /* @todo add recurring times option */
            endif;

            if ( isset( $training_fields['members']['tile'] ) && $training_fields['members']['tile'] === $section ) : ?>
                <div class="section-subheader members-header" style="padding-top: 10px;">
                    <div style="padding-bottom: 5px; margin-right:10px; display: inline-block">
                        <?php esc_html_e( 'Member List', 'disciple-tools-training' ) ?>
                    </div>
                    <button type="button" class="create-new-record" data-connection-key="members" style="height: 36px;">
                        <?php echo esc_html__( 'Create', 'disciple-tools-training' )?>
                        <img style="height: 14px; width: 14px" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/small-add.svg' ) ?>"/>
                    </button>
                    <button type="button"
                            class="add-new-member">
                        <?php echo esc_html__( 'Select', 'disciple-tools-training' )?>
                        <img style="height: 16px; width: 16px" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/add-group.svg' ) ?>"/>
                    </button>
                </div>
                <div class="members-section" style="margin-bottom:10px">
                    <div id="empty-members-list-message"><?php esc_html_e( "To add new members, click on 'Create' or 'Select'.", 'disciple-tools-training' ) ?></div>
                    <div class="member-list">
                    </div>
                </div>
                <div class="reveal" id="add-new-group-member-modal" data-reveal style="min-height:500px">
                    <h3><?php echo esc_html_x( 'Add members from existing contacts', 'Add members modal', 'disciple-tools-training' )?></h3>
                    <p><?php echo esc_html_x( "In the 'Member List' field, type the name of an existing contact to add them to this training.", 'Add members modal', 'disciple-tools-training' )?></p>

                    <?php
                    $training_member_list = $training_fields;
                    $training_member_list['members']['custom_display'] = false;
                    render_field_for_display( 'members', $training_member_list, $training, false ); ?>

                    <div class="grid-x pin-to-bottom">
                        <div class="cell">
                            <hr>
                            <span style="float:right; bottom: 0;">
                    <button class="button" data-close aria-label="Close reveal" type="button">
                        <?php echo esc_html__( 'Close', 'disciple-tools-training' )?>
                    </button>
                </span>
                        </div>
                    </div>
                    <button class="close-button" data-close aria-label="Close modal" type="button">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif;
        }

    }

    public function scripts(){
        if ( is_singular( 'trainings' ) && get_the_ID() && DT_Posts::can_view( $this->post_type, get_the_ID() ) ){

            wp_enqueue_script( 'dt_trainings', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'trainings.js', [
                'jquery',
                'shared-functions',
                'details',
                'typeahead-jquery'
            ], filemtime( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'trainings.js' ), true );
        }
    }

    // hooks incoming

    /**
     * This filter catches the meeting_times field and keeps the up post update from processing the
     * field as meeting_time, and instead ignores the post submit so that it can be handed by the dt_post_update_fields
     * action later.
     *
     * @param array $field_types
     * @param string $post_type
     * @return array
     */
    public function dt_post_updated_custom_handled_meta( array $field_types, $post_type ) : array {
        if ( 'trainings' === $post_type ) {
            $field_types[] = 'datetime_series';
        }
        return $field_types;
    }

    public function dt_post_update_fields( $fields, $post_type, $post_id, $existing_post ){
        if ( 'trainings' === $post_type ){
dt_write_log( $fields );
//            @todo, share with user with post assigned to user.

            // meeting_times (datetime_series field type)
            if ( isset( $fields['meeting_times'] ) ) {

                foreach ( $fields['meeting_times'] ?? [] as $field ){
                    // update
                    if ( isset( $field['key'], $field['value'] ) ) {
                        update_post_meta( $post_id, $field['key'], $field['value'] );
                    }
                    // delete
                    else if ( isset( $field['key'], $field['delete'] ) ) {
                        delete_post_meta( $post_id, $field['key'] );
                    }
                    // new
                    else if ( isset( $field['value'] ) ) {
                        $new_meta_key = 'meeting_times_' . Disciple_tools_Posts::unique_hash();
                        update_post_meta( $post_id, $new_meta_key, $field['value'] );
                    }
                }
            }
        }
        return $fields;
    }

    public function dt_post_create_fields( $fields, $post_type ){
        if ( $post_type === 'trainings' ) {
            if ( !isset( $fields['status'] ) ) {
                $fields['status'] = 'new';
            }
        }
        return $fields;
    }

    public function dt_post_created( $post_type, $post_id, $initial_fields ){
        if ( $post_type === 'trainings' ){
            do_action( 'dt_training_created', $post_id, $initial_fields );
            $training = DT_Posts::get_post( 'trainings', $post_id, true, false );
            if ( isset( $training['assigned_to'] ) ) {
                if ( $training['assigned_to']['id'] ) {
                    DT_Posts::add_shared( 'trainings', $post_id, $training['assigned_to']['id'], null, false, false, false );
                }
            }
        }
    }

    public function dt_comment_created( $post_type, $post_id, $comment_id, $type ){
        if ( $post_type === 'trainings' ){
            if ( $type === 'comment' ){
                self::check_requires_update( $post_id );
            }
        }
    }

    public function post_connection_added( $post_type, $post_id, $field_key, $value ){
        if ( $post_type === 'trainings' ){
            if ( $field_key === 'members' ){
                // share the training with the owner of the contact when a member is added to a training
                $assigned_to = get_post_meta( $value, 'assigned_to', true );
                if ( $assigned_to && strpos( $assigned_to, '-' ) !== false ){
                    $user_id = explode( '-', $assigned_to )[1];
                    if ( $user_id ){
                        DT_Posts::add_shared( $post_type, $post_id, $user_id, null, false, false );
                    }
                }
            }
            if ( $field_key === 'coaches' ){
                // share the training with the coach when a coach is added.
                $user_id = get_post_meta( $value, 'corresponds_to_user', true );
                if ( $user_id ){
                    DT_Posts::add_shared( 'trainings', $post_id, $user_id, null, false, false, false );
                }
            }
        }
        if ( $post_type === 'contacts' && $field_key === 'trainings' ){
            // share the training with the owner of the contact.
            $assigned_to = get_post_meta( $post_id, 'assigned_to', true );
            if ( $assigned_to && strpos( $assigned_to, '-' ) !== false ){
                $user_id = explode( '-', $assigned_to )[1];
                if ( $user_id ){
                    DT_Posts::add_shared( 'trainings', $value, $user_id, null, false, false );
                }
            }
        }
    }

    public function dt_format_activity_message( $message, $activity ) {
        if ( $activity->action === 'field_update' && 'trainings' === $activity->object_type && 'meeting_times' === substr( $activity->meta_key, 0, 13 ) ) {

            $post_type_settings = DT_Posts::get_post_field_settings( 'trainings' );
            $fields = $post_type_settings;
            if ( isset( $fields['meeting_times'] ) ) {
                if ( $activity->meta_value === 'value_deleted' ){
                    $message = sprintf( __( 'Removed %1$s: {%2$s}', 'disciple-tools-training' ), $fields['meeting_times']['name'], $activity->old_value );
                }
                else if ( empty( $activity->old_value ) ) {
                    $message = sprintf( __( 'Added %1$s: {%2$s}', 'disciple-tools-training' ), $fields['meeting_times']['name'], $activity->meta_value );
                }
                else {
                    $message = sprintf( __( 'Updated %1$s: {%2$s} to {%3$s}', 'disciple-tools-training' ), $fields['meeting_times']['name'], $activity->old_value, $activity->meta_value );
                }
            }
        }

        return $message;
    }

    // hooks outgoing

    public function dt_adjust_post_custom_fields( $fields, $post_type ) {
        if ( $post_type === 'trainings' ){
            foreach ( $fields as $key => $value ){
                if ( 'meeting_times' === substr( $key, 0, 13 ) ){
                    if ( ! isset( $fields['meeting_times'] ) ) {
                        $fields['meeting_times'] = [];
                    }
                    $fields['meeting_times'][] = [
                        'key' => $key,
                        'timestamp' => is_numeric( $value ) ? $value : dt_format_date( $value, 'U' ),
                        'formatted' => dt_format_date( $value, 'long' ),
                    ];
                    unset( $fields[$key] );
                }
            }
        }
        return $fields;
    }

    public function dt_render_field_for_display_template( $post, $field_type, $field_key, $required_tag ){
        $post_type = $post['post_type'] ?? null;

        if ( $post_type === 'trainings' ){
            $training_fields = DT_Posts::get_post_field_settings( $post_type );

            // leave if custom display is true and hidden is false
            if ( !isset( $training_fields[$field_key] ) || empty( $training_fields[$field_key]['custom_display'] ) || !empty( $training_fields[$field_key]['hidden'] ) ){
                return;
            }



            if ( $field_key === 'datetime_series' ) {
                ?>
                <div class="<?php echo esc_html( $field_key ) ?> input-group">
                    <input id="<?php echo esc_html( $field_key ) ?>" class="input-group-field dt-datetime-series-picker" type="text" autocomplete="off" <?php echo esc_html( $required_tag ) ?>
                           value="<?php echo esc_html( $post[$field_key]['formatted'] ?? '' ) ?>" >
                    <div class="input-group-button">
                        <!--                    <button class="button alert input-height delete-button-style datetime-series-delete-button delete-button new-${window.lodash.escape( field )}" data-key="new" data-field="${window.lodash.escape( field )}">&times;</button>-->
                        <button id="<?php echo esc_html( $field_key ) ?>-clear-button" class="button alert clear-date-button" data-inputid="<?php echo esc_html( $field_key ) ?>" title="Delete Date" type="button">x</button>
                    </div>
                </div>
                <?php
            };
        }
    }

    // list

    public static function dt_user_list_filters( $filters, $post_type ){
        if ( $post_type === 'trainings' ){
            $counts = self::get_my_trainings_status_type();
            $fields = DT_Posts::get_post_field_settings( $post_type );
            $post_label_plural = DT_Posts::get_post_settings( $post_type )['label_plural'];
            /**
             * Setup my training filters
             */
            $active_counts = [];
            $update_needed = 0;
            $status_counts = [];
            $total_my = 0;
            foreach ( $counts as $count ){
                $total_my += $count['count'];
                dt_increment( $status_counts[$count['status']], $count['count'] );
                if ( $count['status'] === 'new' ){
                    if ( isset( $count['update_needed'] ) ) {
                        $update_needed += (int) $count['update_needed'];
                    }
                    dt_increment( $active_counts[$count['status']], $count['count'] );
                }
            }


            $filters['tabs'][] = [
                'key' => 'assigned_to_me',
                'label' => _x( 'Assigned to me', 'List Filters', 'disciple-tools-training' ),
                'count' => $total_my,
                'order' => 20
            ];
            // add assigned to me filters
            $filters['filters'][] = [
                'ID' => 'my_all',
                'tab' => 'assigned_to_me',
                'name' => _x( 'All', 'List Filters', 'disciple-tools-training' ),
                'query' => [
                    'assigned_to' => [ 'me' ],
                    'sort' => '-post_date',
                    'status' => [ '-closed' ]
                ],
                'count' => $total_my,
            ];

            foreach ( $fields['status']['default'] as $status_key => $status_value ) {
                if ( isset( $status_counts[$status_key] ) ){
                    $filters['filters'][] = [
                        'ID' => 'my_' . $status_key,
                        'tab' => 'assigned_to_me',
                        'name' => $status_value['label'],
                        'query' => [
                            'assigned_to' => [ 'me' ],
                            'status' => [ $status_key ],
                            'sort' => '-post_date'
                        ],
                        'count' => $status_counts[$status_key]
                    ];
                    if ( $status_key === 'new' ){
                        if ( $update_needed > 0 ){
                            $filters['filters'][] = [
                                'ID' => 'my_update_needed',
                                'tab' => 'assigned_to_me',
                                'name' => $fields['requires_update']['name'],
                                'query' => [
                                    'assigned_to' => [ 'me' ],
                                    'status' => [ 'new' ],
                                    'requires_update' => [ true ],
                                ],
                                'count' => $update_needed,
                                'subfilter' => true
                            ];
                        }
                    }
                }
            }

            if ( current_user_can( 'view_any_trainings' ) ) {
                $counts = self::get_all_trainings_status_type();
                $active_counts = [];
                $update_needed = 0;
                $status_counts = [];
                $total_all = 0;
                foreach ( $counts as $count ){
                    $total_all += $count['count'];
                    dt_increment( $status_counts[$count['status']], $count['count'] );
                    if ( $count['status'] === 'new' ){
                        if ( isset( $count['update_needed'] ) ) {
                            $update_needed += (int) $count['update_needed'];
                        }
                        dt_increment( $active_counts[$count['status']], $count['count'] );
                    }
                }
                $filters['tabs'][] = [
                    'key' => 'all',
                    'label' => _x( 'Default Filters', 'List Filters', 'disciple-tools-training' ),
                    'count' => $total_all,
                    'order' => 10
                ];
                // add assigned to me filters
                $filters['filters'][] = [
                    'ID' => 'all',
                    'tab' => 'all',
                    'name' => sprintf( _x( 'All %s', 'All records', 'disciple_tools' ), $post_label_plural ),
                    'query' => [
                        'sort' => '-post_date',
                        'status' => [ '-closed' ]
                    ],
                    'count' => $total_all
                ];
                $filters['filters'][] = [
                    'ID' => 'favorite',
                    'tab' => 'all',
                    'name' => sprintf( _x( 'Favorite %s', 'Favorite Contacts', 'disciple_tools' ), $post_label_plural ),
                    'query' => [
                        'fields' => [ 'favorite' => [ '1' ] ],
                        'sort' => 'name'
                    ],
                    'labels' => [
                        [ 'id' => '1', 'name' => __( 'Favorite', 'disciple_tools' ) ]
                    ]
                ];
                $filters['filters'][] = [
                    'ID' => 'recent',
                    'tab' => 'all',
                    'name' => __( 'My Recently Viewed', 'disciple_tools' ),
                    'query' => [
                        'dt_recent' => true
                    ],
                    'labels' => [
                        [ 'id' => 'recent', 'name' => __( 'Last 30 viewed', 'disciple_tools' ) ]
                    ]
                ];

                foreach ( $fields['status']['default'] as $status_key => $status_value ) {
                    if ( isset( $status_counts[$status_key] ) ){
                        $filters['filters'][] = [
                            'ID' => 'all_' . $status_key,
                            'tab' => 'all',
                            'name' => $status_value['label'],
                            'query' => [
                                'status' => [ $status_key ],
                                'sort' => '-post_date'
                            ],
                            'count' => $status_counts[$status_key]
                        ];
                        if ( $status_key === 'new' ){
                            if ( $update_needed > 0 ){
                                $filters['filters'][] = [
                                    'ID' => 'all_update_needed',
                                    'tab' => 'all',
                                    'name' => $fields['requires_update']['name'],
                                    'query' => [
                                        'status' => [ 'new' ],
                                        'requires_update' => [ true ],
                                    ],
                                    'count' => $update_needed,
                                    'subfilter' => true
                                ];
                            }
                        }
                    }
                }
            }
        }
        return $filters;
    }

    public static function dt_filter_access_permissions( $permissions, $post_type ){
        if ( $post_type === 'trainings' ){
            if ( DT_Posts::can_view_all( $post_type ) ){
                $permissions = [];
            }
        }
        return $permissions;
    }

    public static function dt_list_posts_custom_fields( $data, $post_type ) {
        $updated_data = [];
        if ( $post_type === 'trainings' ) {

            // Ensure custom fields, typically overlooked during main core list flow, are captured.
            $ids = [];
            $updated_posts = [];
            $posts = $data['posts'] ?? [];
            foreach ( $posts as $post ) {
                if ( isset( $post['ID'] ) ) {
                    //$ids[] = $post['ID'];
                    DT_Posts::adjust_post_custom_fields( $post_type, $post['ID'], $post, [], null, null );

                    //dt_write_log( $post );
                }
                $updated_posts[] = $post;
            }

            $data['posts'] = $updated_posts;
            if ( !empty( $ids ) ) {
                global $wpdb;

                /*$ids_sql = dt_array_to_sql( $ids );

                // phpcs:disable
                // WordPress.WP.PreparedSQL.NotPrepared
                $all_post_meta = $wpdb->get_results( "
                    SELECT *
                        FROM $wpdb->postmeta pm
                        WHERE pm.post_id IN ( $ids_sql )
                        AND pm.meta_key LIKE 'meeting_times_%'
                ", ARRAY_A );
                // phpcs:enable

                dt_write_log( $all_post_meta );
                // Include meeting times for corresponding posts.
                foreach ( $all_post_meta ?? [] as $post_meta ) {
                    if ( isset( $post_meta['post_id'] ) ) {
                        foreach ( $posts as $post ) {
                            if ( isset( $post['ID'] ) && ( $post['ID'] === $post_meta['post_id'] ) ) {

                            }
                        }
                    }
                }*/





                /*foreach ( $all_post_meta ?? [] as $post_meta ) {
                    if ( isset( $post_meta['post_id'], $post_meta['meta_value'] ) ) {
                        foreach ( $data['posts'] ?? [] as &$post ) {
                            if ( isset( $post['ID'] ) && ( $post['ID'] === $post_meta['post_id'] ) ) {

                                if ( !isset( $post['meeting_times'] ) || !is_array( $post['meeting_times'] ) ) {
                                    $post['meeting_times'] = [];
                                }

                                $post['meeting_times'][] = [
                                    'timestamp' => $post_meta['meta_value']
                                ];

                                dt_write_log( $post );
                            }
                        }
                    }
                }*/
            }
        }

        dt_write_log( $data );

        return $data;
    }

    private static function check_requires_update( $training_id ){
        if ( get_current_user_id() ){
            $requires_update = get_post_meta( $training_id, 'requires_update', true );
            if ( $requires_update == 'yes' || $requires_update == true || $requires_update == '1' ){
                //don't remove update needed if the user is a dispatcher (and not assigned to the trainings.)
                if ( DT_Posts::can_view_all( 'trainings' ) ){
                    if ( dt_get_user_id_from_assigned_to( get_post_meta( $training_id, 'assigned_to', true ) ) === get_current_user_id() ){
                        update_post_meta( $training_id, 'requires_update', false );
                    }
                } else {
                    update_post_meta( $training_id, 'requires_update', false );
                }
            }
        }
    }

    private static function get_my_trainings_status_type(){
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT status.meta_value as status, count(pm.post_id) as count, count(un.post_id) as update_needed
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->postmeta status ON( status.post_id = pm.post_id AND status.meta_key = 'status' )
            INNER JOIN $wpdb->posts a ON( a.ID = pm.post_id AND a.post_type = 'trainings' and a.post_status = 'publish' )
            INNER JOIN $wpdb->postmeta as assigned_to ON a.ID=assigned_to.post_id
              AND assigned_to.meta_key = 'assigned_to'
              AND assigned_to.meta_value = CONCAT( 'user-', %s )
            LEFT JOIN $wpdb->postmeta un ON ( un.post_id = pm.post_id AND un.meta_key = 'requires_update' AND un.meta_value = '1' )
            WHERE pm.meta_key = 'status'
            GROUP BY status.meta_value, pm.meta_value
        ", get_current_user_id() ), ARRAY_A);

        return $results;
    }

    private static function get_all_trainings_status_type(){
        global $wpdb;
        if ( current_user_can( 'view_any_trainings' ) ){
            $results = $wpdb->get_results("
                SELECT status.meta_value as status, count(pm.post_id) as count, count(un.post_id) as update_needed
                FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta status ON( status.post_id = pm.post_id AND status.meta_key = 'status' )
                INNER JOIN $wpdb->posts a ON( a.ID = pm.post_id AND a.post_type = 'trainings' and a.post_status = 'publish' )
                LEFT JOIN $wpdb->postmeta un ON ( un.post_id = pm.post_id AND un.meta_key = 'requires_update' AND un.meta_value = '1' )
                WHERE pm.meta_key = 'status'
                GROUP BY status.meta_value, pm.meta_value
            ", ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare("
                SELECT status.meta_value as status, count(pm.post_id) as count, count(un.post_id) as update_needed
                FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->postmeta status ON( status.post_id = pm.post_id AND status.meta_key = 'status' )
                INNER JOIN $wpdb->posts a ON( a.ID = pm.post_id AND a.post_type = 'trainings' and a.post_status = 'publish' )
                LEFT JOIN $wpdb->dt_share AS shares ON ( shares.post_id = a.ID AND shares.user_id = %s )
                LEFT JOIN $wpdb->postmeta assigned_to ON ( assigned_to.post_id = pm.post_id AND assigned_to.meta_key = 'assigned_to' && assigned_to.meta_value = %s )
                LEFT JOIN $wpdb->postmeta un ON ( un.post_id = pm.post_id AND un.meta_key = 'requires_update' AND un.meta_value = '1' )
                WHERE pm.meta_key = 'status' AND
                      ( shares.user_id IS NOT NULL OR assigned_to.meta_value IS NOT NULL )
                GROUP BY status.meta_value, pm.meta_value
            ", get_current_user_id(), 'user-' . get_current_user_id() ), ARRAY_A);
        }

        return $results;
    }

}
