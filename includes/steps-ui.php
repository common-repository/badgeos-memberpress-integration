<?php
/**
 * Custom Achievement Steps UI.
 *
 * @package BadgeOS Memberpress Integration
 * @author Mohammad Karrar
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 */

/**
 * Update badgeos_get_step_requirements to include our custom requirements.
 *
 * @param $requirements
 * @param $step_id
 * @return mixed
 */
function badgeos_mepr_step_requirements( $requirements, $step_id ) {

	/**
     * Add our new requirements to the list
     */
	$requirements[ 'mepr_trigger' ] = get_post_meta( $step_id, '_badgeos_subtrigger_value', true );

	return $requirements;
}
add_filter( 'badgeos_get_deduct_step_requirements', 'badgeos_mepr_step_requirements', 10, 2 );
add_filter( 'badgeos_get_rank_req_step_requirements', 'badgeos_mepr_step_requirements', 10, 2 );
add_filter( 'badgeos_get_award_step_requirements', 'badgeos_mepr_step_requirements', 10, 2 );
add_filter( 'badgeos_get_step_requirements', 'badgeos_mepr_step_requirements', 10, 2 );


/**
 * Filter the BadgeOS Triggers selector with our own options.
 *
 * @param $triggers
 * @return mixed
 */
function badgeos_mepr_activity_triggers( $triggers ) {
    
    $args = array('post_type' => 'memberpressproduct', 'post_status' => 'publish');
    $memberships = get_posts($args);

    $paid_memberships_titles = $free_memberships_titles = $all_membership_titles = array();
    $paid_memberships_titles[0] = $free_memberships_titles[0] = $all_membership_titles[0] = 'Any Membership';
  
    foreach ($memberships as $membership) {

        // all memberships
        $all_membership_titles[$membership->ID] = $membership->post_title;

        // memberpress membership product obj
        $mepr_product = new MeprProduct($membership->ID);
        
        // check for paid/free memberships and put accordingly to its desired array
        if ( $mepr_product->price > 0 ) {
            $paid_memberships_titles[$membership->ID] = $membership->post_title;
        } else {
            $free_memberships_titles[$membership->ID] = $membership->post_title;
        }
    }

    // trigger fields
    $all_memberships = [ 
        ['label'=> 'memberships', "id"=>"badgeos_mepr_membership_id", 'type' => 'select', 'options'=> $all_membership_titles, 'fields' => [] ],
    ];

    $free_memberships = [ 
        ['label'=> 'memberships', "id"=>"badgeos_mepr_membership_id", 'type' => 'select', 'options'=> $free_memberships_titles, 'fields' => [] ],
    ];

    $paid_memberships = [ 
        ['label'=> 'memberships', "id"=>"badgeos_mepr_membership_id", 'type' => 'select', 'options'=> $paid_memberships_titles, 'fields' => [] ],
    ];

    // memberpress triggers
    $triggers[ 'mepr_triggers' ]	= array( 
        'label'=> __( 'Memberpress Activity', BOSMEPR_LANG ),
        'sub_triggers'=> [
            [   'trigger' => 'badgeos_mepr_subscribed_any_membership',
                'label' => __( 'Subscribe Any Membership', BOSMEPR_LANG ),
                'fields' => $all_memberships
            ],
            [   'trigger' => 'badgeos_mepr_subscribed_free_membership',
                'label' => __( 'Subscribe Any Free Membership', BOSMEPR_LANG ),
                'fields' => $free_memberships
            ],
            [   'trigger' => 'badgeos_mepr_subscribed_paid_membership',
                'label' => __( 'Subscribe Any Paid Membership', BOSMEPR_LANG ),
                'fields' => $paid_memberships
            ],
        ]
    );
    
	return $triggers;
} 
add_filter( 'badgeos_activity_triggers_for_all', 'badgeos_mepr_activity_triggers');