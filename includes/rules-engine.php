<?php
/**
 * Custom Achievement Rules
 *
 * @package BadgeOS Memberpress Integration
 * @author Mohammad Karrar
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 */

/**
 * Load up our Memberpress triggers so we can add actions to them
 */
function badgeos_mepr_load_triggers() {

	/**
     * Grab our Memberpress triggers
     */

	$mepr_triggers = $GLOBALS[ 'BadgeOS_Memberpress_Integration' ]->triggers;

	if ( !empty( $mepr_triggers ) ) {
		foreach ( $mepr_triggers as $trigger => $trigger_label ) {
			add_action( $trigger, 'badgeos_mepr_trigger_event', 10, 20 );
			add_action( $trigger, 'badgeos_mepr_trigger_award_points_event', 10, 20 );
			add_action( $trigger, 'badgeos_mepr_trigger_deduct_points_event', 10, 20 );
			add_action( $trigger, 'badgeos_mepr_trigger_ranks_event', 10, 20 );
		}
	}
}

add_action( 'init', 'badgeos_mepr_load_triggers', 0 );

/**
 * Check if user deserves a Memberpress trigger step
 *
 * @param $return
 * @param $user_id
 * @param $step_id
 * @param string $this_trigger
 * @param int $site_id
 * @param array $args
 * @return bool
 */
function badgeos_mepr_user_deserves_mepr_step( $return, $user_id, $step_id, $this_trigger, $site_id, $args ) {
	
	$badgeos_settings = ( $exists = get_option( 'badgeos_settings' ) ) ? $exists : array();
	if ( trim( $badgeos_settings['achievement_step_post_type'] ) != get_post_type( $step_id ) )
		return $return;

	if ( empty( $args ) ) return false;

	$requirements = badgeos_get_step_requirements( $step_id );
		
	if ( 'mepr_triggers' == $requirements[ 'trigger_type' ] ) {

		$return = false;

	
		/**
         * memberpress requirements not met yet
         */
		$mepr_triggered = false;

		/**
         * Set our main vars
         */
		$mepr_trigger 	= $requirements['mepr_trigger'];
		$step_params  	= get_post_meta( $step_id, '_badgeos_fields_data', true );
		$step_params  	= badgeos_extract_array_from_query_params( $step_params );
		$object_id 		= $step_params['badgeos_mepr_membership_id'];


		/**
         * Object-specific triggers
         */
		/**
         * Object-specific triggers
         */
		$mepr_new_memberships_triggers = array(
			'badgeos_mepr_subscribed_any_membership',
			'badgeos_mepr_subscribed_free_membership',
			'badgeos_mepr_subscribed_paid_membership'
		);

		$mepr_cancelled_memberships_triggers = array(
			'badgeos_mepr_cancelled_membership'
		);

		$mepr_expired_memberships_triggers = array(
			'badgeos_mepr_expired_membership'
		);

		$mepr_renewed_memberships_triggers = array(
			'badgeos_mepr_renewed_membership'
		);

		/** 
         * Get subscription level and ID
         */
		$triggered_object_id 	= 0;

		if( in_array($mepr_trigger, $mepr_new_memberships_triggers) ) {
			$triggered_object_id = $args[0];
		}
		
		if( in_array($mepr_trigger, array( 'badgeos_mepr_expired_membership', 'badgeos_mepr_cancelled_membership' )) ) {
			$triggered_object_id = $args[0];
		}

		if( $mepr_trigger == 'badgeos_mepr_renewed_membership' ) {
			$triggered_object_id = $args[0];
		}

		if( in_array( $mepr_trigger, $mepr_new_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_cancelled_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_expired_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_renewed_memberships_triggers ) 
		) {

			if( $object_id == 0 ) {
				$mepr_triggered = true;
			} else if( $object_id > 0 ) {
				if( $object_id == $triggered_object_id ) {
					$mepr_triggered = true;
				}
			}

		}
		if ( $mepr_triggered && in_array( $mepr_trigger, $mepr_new_memberships_triggers ) ) {
			$product_id = new MeprProduct($triggered_object_id);
			$product_price = absint( $product_id->price );
		    /**
             * Check for fail
             */
			if ( 'badgeos_mepr_subscribed_any_membership' == $mepr_trigger ) {
				$mepr_triggered = true;
			} elseif ( 'badgeos_mepr_subscribed_free_membership' == $mepr_trigger ) {

				if( $product_price != 0 ) {
					$mepr_triggered = false;
				} 
			} elseif( 'badgeos_mepr_subscribed_paid_membership' == $mepr_trigger ){
				
				if( $product_price > 0 ) {
					$mepr_triggered = true;
				} 
			}
		}

		if ( $mepr_triggered ) {
			// Grab the requirements for this step
			$step_requirements = badgeos_get_step_requirements( $step_id );
			
			if ( ! empty( $step_requirements["trigger_type"] ) && trim( $step_requirements["trigger_type"] )=='mepr_triggers' ) {

				$parent_achievement = badgeos_get_parent_of_achievement( $step_id );
				$parent_id = $parent_achievement->ID;
					
				$user_crossed_max_allowed_earnings = badgeos_achievement_user_exceeded_max_earnings( $user_id, $parent_id );
				if ( ! $user_crossed_max_allowed_earnings ) {
					$minimum_activity_count = absint( get_post_meta( $step_id, '_badgeos_count', true ) );
					$count_step_trigger = $step_requirements["mepr_trigger"];
					$activities = badgeos_get_user_trigger_count( $user_id, $count_step_trigger );
					$relevant_count = absint( $activities );
					$achievements = badgeos_get_user_achievements(
						array(
							'user_id' => absint( $user_id ),
							'achievement_id' => $step_id
						)
					);

					$total_achievments = count( $achievements );
					$used_points = intval( $minimum_activity_count ) * intval( $total_achievments );
					$remainder = intval( $relevant_count ) - $used_points;
					update_post_meta( $parent_achievement->ID, 'mepr_achievement_product_id', $args[0]);

					$return  = 0;
					if ( absint( $remainder ) >= $minimum_activity_count )
						$return  = $remainder;

					return true;
				} else {
					return 0;
				}
			}
		}
	}		

	return $return;
	
}
add_filter( 'user_deserves_achievement', 'badgeos_mepr_user_deserves_mepr_step', 17, 6 );


/**
 * Check if user deserves a Memberpress trigger step
 *
 * @param $return
 * @param $user_id
 * @param $step_id
 * @param string $this_trigger
 * @param int $site_id
 * @param array $args
 * @return bool
 */
function badgeos_mepr_user_deserves_rank_step( $return, $step_id, $rank_id, $user_id, $this_trigger, $site_id, $args ) {
	
	
	// If we're not dealing with a step, bail here
	$settings = get_option( 'badgeos_settings' );
	if ( trim( $settings['ranks_step_post_type'] ) != get_post_type( $step_id ) ) {
		return $return;
	}

	$requirements = badgeos_get_rank_req_step_requirements( $step_id );
	
	if ( 'mepr_triggers' == $requirements[ 'trigger_type' ] ) {

		$return = false;

	
		/**
         * memberpress requirements not met yet
         */
		$mepr_triggered = false;

		/**
         * Set our main vars
         */
		$mepr_trigger 	= get_post_meta( $step_id, '_badgeos_rank_subtrigger_value', true);
		$step_params  	= get_post_meta( $step_id, '_badgeos_fields_data', true );
		$step_params  	= badgeos_extract_array_from_query_params( $step_params );
		$object_id 		= $step_params['badgeos_mepr_membership_id'];


		/**
         * Object-specific triggers
         */
		/**
         * Object-specific triggers
         */
		$mepr_new_memberships_triggers = array(
			'badgeos_mepr_subscribed_any_membership',
			'badgeos_mepr_subscribed_free_membership',
			'badgeos_mepr_subscribed_paid_membership'
		);

		$mepr_cancelled_memberships_triggers = array(
			'badgeos_mepr_cancelled_membership'
		);

		$mepr_expired_memberships_triggers = array(
			'badgeos_mepr_expired_membership'
		);

		$mepr_renewed_memberships_triggers = array(
			'badgeos_mepr_renewed_membership'
		);

		/** 
         * Get subscription level and ID
         */
		$triggered_object_id 	= 0;

		if( in_array($mepr_trigger, $mepr_new_memberships_triggers) ) {
			$triggered_object_id = $args[0];
			
		}
		
		if( in_array($mepr_trigger, array( 'badgeos_mepr_expired_membership', 'badgeos_mepr_cancelled_membership' )) ) {
			$triggered_object_id = $args[0];
		}

		if( $mepr_trigger == 'badgeos_mepr_renewed_membership' ) {
			$triggered_object_id = $args[0];
		}

		if( in_array( $mepr_trigger, $mepr_new_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_cancelled_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_expired_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_renewed_memberships_triggers ) 
		) {

			if( $object_id == 0 ) {
				$mepr_triggered = true;
			} else if( $object_id > 0 ) {
				if( $object_id == $triggered_object_id ) {
					$mepr_triggered = true;
				}
			}

		}

		if ( $mepr_triggered && in_array( $mepr_trigger, $mepr_new_memberships_triggers ) ) {
			$product_id = new MeprProduct($triggered_object_id);
		    /**
             * Check for fail
             */
			if ( 'badgeos_mepr_subscribed_any_membership' == $mepr_trigger ) {
				$mepr_triggered = true;
			} elseif ( 'badgeos_mepr_subscribed_free_membership' == $mepr_trigger ) {

				if( absint( $product_id->price ) > 0 ) {
					$mepr_triggered = false;
				} else {
					$mepr_triggered = true;
				}

			} elseif( 'badgeos_mepr_subscribed_paid_membership' == $mepr_trigger ){
				
				if( absint( $product_id->price ) > 0 ) {
					$mepr_triggered = true;
				} else {
					$mepr_triggered = false;
				}

			}
		}

		if ( $mepr_triggered ) {
			// Grab the trigger count
			$trigger_count = ranks_get_user_trigger_count( $step_id, $user_id, $this_trigger, $site_id, 'Award', $args );

			// If we meet or exceed the required number of checkins, they deserve the step
			if ( 1 == $requirements[ 'count' ] || $requirements[ 'count' ] <= $trigger_count ) {
				// OK, you can pass go now
				$parent_id = badgeos_get_parent_id( $step_id );
				update_post_meta( $parent_id, 'mepr_rank_product_id', $args[0]);
				$return = true;
			}
		}
		
	}		

	return $return;
	
}
add_filter( 'badgeos_user_deserves_rank_step', 'badgeos_mepr_user_deserves_rank_step', 15, 7 );



/**
 * Check if user deserves a Memberpress trigger point
 *
 * @param $return
 * @param $user_id
 * @param $step_id
 * @param string $this_trigger
 * @param int $site_id
 * @param array $args
 * @return bool
 */
function badgeos_mepr_user_deserves_point_award(  $return, $credit_step_id, $credit_parent_id, $user_id, $this_trigger, $site_id, $args ) {
	
	
	// Grab our step requirements
	$requirements    = badgeos_get_award_step_requirements( $credit_step_id );
	
	// If we're not dealing with a step, bail here
	$settings = get_option( 'badgeos_settings' );
	if ( trim( $settings['points_award_post_type'] ) != get_post_type( $credit_step_id ) ) {
		return $return;
	}

	if ( 'mepr_triggers' == $requirements[ 'trigger_type' ] ) {

		$return = false;

	
		/**
         * memberpress requirements not met yet
         */
		$mepr_triggered = false;

		/**
         * Set our main vars
         */
		$mepr_trigger 	= get_post_meta( $step_id, '_badgeos_paward_subtrigger_value', true);
		$step_params  	= get_post_meta( $step_id, '_badgeos_fields_data', true );
		$step_params  	= badgeos_extract_array_from_query_params( $step_params );
		$object_id 		= $step_params['badgeos_mepr_membership_id'];


		/**
         * Object-specific triggers
         */
		/**
         * Object-specific triggers
         */
		$mepr_new_memberships_triggers = array(
			'badgeos_mepr_subscribed_any_membership',
			'badgeos_mepr_subscribed_free_membership',
			'badgeos_mepr_subscribed_paid_membership'
		);

		$mepr_cancelled_memberships_triggers = array(
			'badgeos_mepr_cancelled_membership'
		);

		$mepr_expired_memberships_triggers = array(
			'badgeos_mepr_expired_membership'
		);

		$mepr_renewed_memberships_triggers = array(
			'badgeos_mepr_renewed_membership'
		);

		/** 
         * Get subscription level and ID
         */
		$triggered_object_id 	= 0;

		if( in_array($mepr_trigger, $mepr_new_memberships_triggers) ) {
			$triggered_object_id = $args[0];
			
		}
		
		if( in_array($mepr_trigger, array( 'badgeos_mepr_expired_membership', 'badgeos_mepr_cancelled_membership' )) ) {
			$triggered_object_id = $args[0];
		}

		if( $mepr_trigger == 'badgeos_mepr_renewed_membership' ) {
			$triggered_object_id = $args[0];
		}

		if( in_array( $mepr_trigger, $mepr_new_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_cancelled_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_expired_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_renewed_memberships_triggers ) 
		) {

			if( $object_id == 0 ) {
				$mepr_triggered = true;
			} else if( $object_id > 0 ) {
				if( $object_id == $triggered_object_id ) {
					$mepr_triggered = true;
				}
			}

		}

		if ( $mepr_triggered && in_array( $mepr_trigger, $mepr_new_memberships_triggers ) ) {
			$product_id = new MeprProduct($triggered_object_id);
		    /**
             * Check for fail
             */
			if ( 'badgeos_mepr_subscribed_any_membership' == $mepr_trigger ) {
				$mepr_triggered = true;
			} elseif ( 'badgeos_mepr_subscribed_free_membership' == $mepr_trigger ) {

				if( absint( $product_id->price ) > 0 ) {
					$mepr_triggered = false;
				} else {
					$mepr_triggered = true;
				}

			} elseif( 'badgeos_mepr_subscribed_paid_membership' == $mepr_trigger ){
				
				if( absint( $product_id->price ) > 0 ) {
					$mepr_triggered = true;
				} else {
					$mepr_triggered = false;
				}
			}
		}

		if ( $mepr_triggered ) {
			// Grab the trigger count
			$trigger_count = points_get_user_trigger_count( $credit_step_id, $user_id, $this_trigger, $site_id, 'Award', $args );

			// If we meet or exceed the required number of checkins, they deserve the step
			if ( 1 == $requirements[ 'count' ] || $requirements[ 'count' ] <= $trigger_count ) {
				// OK, you can pass go now
				$return = true;
			}
		}
		
	}		

	return $return;
	
}
add_filter( 'badgeos_user_deserves_credit_award', 'badgeos_mepr_user_deserves_point_award', 15, 7 );

/**
 * Check if user deserves a Memberpress trigger point
 *
 * @param $return
 * @param $user_id
 * @param $step_id
 * @param string $this_trigger
 * @param int $site_id
 * @param array $args
 * @return bool
 */
function badgeos_mepr_user_deserves_point_deduct(  $return, $credit_step_id, $credit_parent_id, $user_id, $this_trigger, $site_id, $args ) {
	
	
	// Grab our step requirements
	$requirements    = badgeos_get_deduct_step_requirements( $credit_step_id );
	
	// If we're not dealing with a step, bail here
	$settings = get_option( 'badgeos_settings' );
	if ( trim( $settings['points_deduct_post_type'] ) != get_post_type( $credit_step_id ) ) {
		return $return;
	}

	if ( 'mepr_triggers' == $requirements[ 'trigger_type' ] ) {

		$return = false;
	
		/**
         * memberpress requirements not met yet
         */
		$mepr_triggered = false;

		/**
         * Set our main vars
         */
		$mepr_trigger 	= get_post_meta( $credit_step_id, '_badgeos_pdeduct_subtrigger_value', true);
		$step_params  	= get_post_meta( $credit_step_id, '_badgeos_fields_data', true );
		$step_params  	= badgeos_extract_array_from_query_params( $step_params );
		$object_id 		= $step_params['badgeos_mepr_membership_id'];


		/**
         * Object-specific triggers
         */
		/**
         * Object-specific triggers
         */
		$mepr_new_memberships_triggers = array(
			'badgeos_mepr_subscribed_any_membership',
			'badgeos_mepr_subscribed_free_membership',
			'badgeos_mepr_subscribed_paid_membership'
		);

		$mepr_cancelled_memberships_triggers = array(
			'badgeos_mepr_cancelled_membership'
		);

		$mepr_expired_memberships_triggers = array(
			'badgeos_mepr_expired_membership'
		);

		$mepr_renewed_memberships_triggers = array(
			'badgeos_mepr_renewed_membership'
		);

		/** 
         * Get subscription level and ID
         */
		$triggered_object_id 	= 0;

		if( in_array($mepr_trigger, $mepr_new_memberships_triggers) ) {
			$triggered_object_id = $args[0];
			
		}
		
		if( in_array($mepr_trigger, array( 'badgeos_mepr_expired_membership', 'badgeos_mepr_cancelled_membership' )) ) {
			$triggered_object_id = $args[0];
		}

		if( $mepr_trigger == 'badgeos_mepr_renewed_membership' ) {
			$triggered_object_id = $args[0];
		}

		if( in_array( $mepr_trigger, $mepr_new_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_cancelled_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_expired_memberships_triggers ) 
			|| in_array( $mepr_trigger, $mepr_renewed_memberships_triggers ) 
		) {

			if( $object_id == 0 ) {
				$mepr_triggered = true;
			} else if( $object_id > 0 ) {
				if( $object_id == $triggered_object_id ) {
					$mepr_triggered = true;
				}
			}

		}

		if ( $mepr_triggered && in_array( $mepr_trigger, $mepr_new_memberships_triggers ) ) {
			$product_id = new MeprProduct($triggered_object_id);
		    /**
             * Check for fail
             */
			if ( 'badgeos_mepr_subscribed_any_membership' == $mepr_trigger ) {
				$mepr_triggered = true;
			} elseif ( 'badgeos_mepr_subscribed_free_membership' == $mepr_trigger ) {

				if( absint( $product_id->price ) > 0 ) {
					$mepr_triggered = false;
				} else {
					$mepr_triggered = true;
				}

			} elseif( 'badgeos_mepr_subscribed_paid_membership' == $mepr_trigger ){
				
				if( absint( $product_id->price ) > 0 ) {
					$mepr_triggered = true;
				} else {
					$mepr_triggered = false;
				}
			}
		}

		if ( $mepr_triggered ) {
			// Grab the trigger count
			$trigger_count = points_get_user_trigger_count( $credit_step_id, $user_id, $this_trigger, $site_id, 'Deduct', $args );

			// If we meet or exceed the required number of checkins, they deserve the step
			if ( 1 == $requirements[ 'count' ] || $requirements[ 'count' ] <= $trigger_count ) {
				// OK, you can pass go now
				$return = true;
			}
		}
		
	}		

	return $return;
	
}
add_filter( 'badgeos_user_deserves_credit_deduct', 'badgeos_mepr_user_deserves_point_deduct', 15, 7 );


/**
 * Handle memberpress triggers for award points
 */
function badgeos_mepr_trigger_award_points_event() {
	
	/**
     * Setup all our globals
     */
	global $user_ID, $blog_id, $wpdb;

	$site_id = $blog_id;

	$args = func_get_args();
	
	/**
     * Grab our current trigger
     */
	$this_trigger = current_filter();
	
	/**
     * Grab the user ID
     */
	$user_id = badgeos_trigger_get_user_id( $this_trigger, $args );
	$user_data = get_user_by( 'id', $user_id );

	/**
     * Sanity check, if we don't have a user object, bail here
     */
	if ( ! is_object( $user_data ) )
		return $args[ 0 ];
	
	/**
     * If the user doesn't satisfy the trigger requirements, bail here\
     */
	if ( ! apply_filters( 'user_deserves_point_award_trigger', true, $user_id, $this_trigger, $site_id, $args ) ) {
        return $args[ 0 ];
    }
    
	/**
     * Now determine if any badges are earned based on this trigger event
     */
	$triggered_points = $wpdb->get_results( $wpdb->prepare("
			SELECT p.ID as post_id FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON 
			( p.ID = pm.post_id AND pm.meta_key = '_point_trigger_type' )INNER JOIN $wpdb->postmeta AS pmtrg 
			ON ( p.ID = pmtrg.post_id AND pmtrg.meta_key = '_badgeos_paward_subtrigger_value' ) 
			where p.post_status = 'publish' AND pmtrg.meta_value =  %s 
			",
			$this_trigger
		) );
	
	if( !empty( $triggered_points ) ) {
		foreach ( $triggered_points as $point ) { 

			$parent_point_id = badgeos_get_parent_id( $point->post_id );

			/**
			 * Update hook count for this user
			 */
			$new_count = badgeos_points_update_user_trigger_count( $point->post_id, $parent_point_id, $user_id, $this_trigger, $site_id, 'Award', $args );
			
			badgeos_maybe_award_points_to_user( $point->post_id, $parent_point_id , $user_id, $this_trigger, $site_id, $args );
		}
	}
}

function badgeos_mepr_trigger_deduct_points_event( $args='' ) {
	
	/**
     * Setup all our globals
     */
	global $user_ID, $blog_id, $wpdb;

	$site_id = $blog_id;

	$args = func_get_args();
	$args = $args[0];

	/**
     * Grab our current trigger
     */
	$this_trigger = current_filter();

	/**
     * Grab the user ID
     */
	$user_id = badgeos_trigger_get_user_id( $this_trigger, $args );
	$user_data = get_user_by( 'id', $user_id );

	/**
     * Sanity check, if we don't have a user object, bail here
     */
	if ( ! is_object( $user_data ) ) {
        return $args[ 0 ];
    }

	/**
     * If the user doesn't satisfy the trigger requirements, bail here
     */
	if ( ! apply_filters( 'user_deserves_point_deduct_trigger', true, $user_id, $this_trigger, $site_id, $args ) ) {
        return $args[ 0 ];
    }

	/**
     * Now determine if any Achievements are earned based on this trigger event
     */
	$triggered_deducts = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.ID as post_id FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON 
		( p.ID = pm.post_id AND pm.meta_key = '_deduct_trigger_type' )INNER JOIN $wpdb->postmeta AS pmtrg 
		ON ( p.ID = pmtrg.post_id AND pmtrg.meta_key = '_badgeos_pdeduct_subtrigger_value' ) 
		where p.post_status = 'publish' AND pmtrg.meta_value =  %s",
        $this_trigger
    ) );

	if( !empty( $triggered_deducts ) ) {
		foreach ( $triggered_deducts as $point ) { 
			
			$parent_point_id = badgeos_get_parent_id( $point->post_id );

			/**
             * Update hook count for this user
             */
			$new_count = badgeos_points_update_user_trigger_count( $point->post_id, $parent_point_id, $user_id, $this_trigger, $site_id, 'Deduct', $args );
			
			badgeos_maybe_deduct_points_to_user( $point->post_id, $parent_point_id , $user_id, $this_trigger, $site_id, $args );

		}
	}	
}

/**
 * Handle community triggers for ranks
 */
function badgeos_mepr_trigger_ranks_event() {
	/**
     * Setup all our globals
     */
	global $user_id, $blog_id, $wpdb;

	$site_id = $blog_id;

	$args = func_get_args();
	$args = $args[0];

	/**
     * Grab our current trigger
     */
	$this_trigger = current_filter();

	if ( 'badgeos_mepr_subscribed_any_membership' == current_filter() || 'badgeos_mepr_subscribed_free_membership' == current_filter() || 'badgeos_mepr_subscribed_paid_membership' == current_filter() ) {
		$user_id = absint( $args[1] );
	}
	/**
     * Grab the user ID
     */
	
	$user_data = get_user_by( 'id', $user_id );

	/**
     * Sanity check, if we don't have a user object, bail here
     */
	if ( ! is_object( $user_data ) )
		return $args[ 0 ];

	/**
     * If the user doesn't satisfy the trigger requirements, bail here
     */
	if ( ! apply_filters( 'badgeos_user_rank_deserves_trigger', true, $user_id, $this_trigger, $site_id, $args ) )
		return $args[ 0 ];

	/**
     * Now determine if any Achievements are earned based on this trigger event
     */
	$triggered_ranks = $wpdb->get_results( $wpdb->prepare(
							"SELECT p.ID as post_id FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON 
							( p.ID = pm.post_id AND pm.meta_key = '_rank_trigger_type' )INNER JOIN $wpdb->postmeta AS pmtrg 
							ON ( p.ID = pmtrg.post_id AND pmtrg.meta_key = '_badgeos_rank_subtrigger_value' ) 
							where p.post_status = 'publish' AND pmtrg.meta_value =  %s",
							$this_trigger
						) );
	
	if( !empty( $triggered_ranks ) ) {
		foreach ( $triggered_ranks as $rank ) { 
			$parent_id = badgeos_get_parent_id( $rank->post_id );
			if( absint($parent_id) > 0) { 

				$new_count = badgeos_ranks_update_user_trigger_count( $rank->post_id, $parent_id,$user_id, $this_trigger, $site_id, $args );
				badgeos_maybe_award_rank( $rank->post_id,$parent_id,$user_id, $this_trigger, $site_id, $args );
			} 
		}
	}
}

/**
 * Handle each of our community triggers
 *
 * @since 1.0.0
 */
function badgeos_mepr_trigger_event( $args='' ) {
	/**
	 * Setup all our important variables
	 */
	global $user_ID, $blog_id, $wpdb;
	
	$args = func_get_args();
	$args = $args[0];

	if ( 'badgeos_mepr_subscribed_any_membership' == current_filter() || 'badgeos_mepr_subscribed_free_membership' == current_filter() || 'badgeos_mepr_subscribed_paid_membership' == current_filter() ) {
		$user_ID = absint( $args[1] );
	}

	$user_data = get_user_by( 'id', $user_ID );

	/**
	 * Sanity check, if we don't have a user object, bail here
	 */
	if ( ! is_object( $user_data ) ) {
		return $args[0];
	}

	/**
	 * Grab the current trigger
	 */
	$this_trigger = current_filter();

	/**
	 * Now determine if any badges are earned based on this trigger event
	 */
	$triggered_achievements = $wpdb->get_results( 
		$wpdb->prepare( 
			"SELECT pm.post_id FROM $wpdb->postmeta as pm inner 
			join $wpdb->posts as p on( pm.post_id = p.ID ) WHERE p.post_status = 'publish' and 
			pm.meta_key = '_badgeos_subtrigger_value' AND pm.meta_value = %s", $this_trigger) 
		);

	if( count( $triggered_achievements ) > 0 ) {
		/**
		 * Update hook count for this user
		 */
		$new_count = badgeos_update_user_trigger_count( $userID, $this_trigger, $blog_id );

		/**
		 * Mark the count in the log entry
		 */
		badgeos_post_log_entry( null, $userID, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', 'bosmepr' ), $user_data->user_login, $this_trigger, $new_count ) );
		
		foreach ( $triggered_achievements as $achievement ) {
			$parents = badgeos_get_achievements( array( 'parent_of' => $achievement->post_id ) );
			if( count( $parents ) > 0 ) {
				if( $parents[0]->post_status == 'publish' ) {
					$awarded = badgeos_maybe_award_achievement_to_user( $achievement->post_id, $userID, $this_trigger, $blog_id, $args );
				}
			}
		}
	}
	
}

/*
* Revoke Acheivement/Ranks on cancelling subscription
*/
function mepr_subscription_status_cancelled_cb( $obj ){
	
	$user_id 	= $obj->user_id;
	$product_id = $obj->product_id;
	badgeos_mepr_revoke_rank_function( $user_id, $product_id );
	badgeos_mepr_revoke_achievement_function( $user_id, $product_id );
	badgeos_mepr_deduct_points_from_user_account( $user_id, $product_id );	
	do_action('badgeos_mepr_cancelled_membership', array($product_id, $user_id) );
}
add_action('mepr_subscription_status_cancelled', 'mepr_subscription_status_cancelled_cb', 10, 1);

function badgeos_mepr_deduct_points_from_user_account( $user_id, $product_id ){
	global $wpdb;

	$results = $wpdb->get_results( 
				$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}badgeos_points WHERE user_id=%d", $user_id )
			);

	
	$total_points = get_user_meta( $user_id, '_badgeos_points', true );
	foreach( $results as $res ) {
		$p_id = get_post_meta( $res->achievement_id, 'mepr_achievement_product_id', true );	
		
		if ($p_id == $product_id )
			$total_points -= floatval( $res->credit );
	}

	update_user_meta( $user_id, '_badgeos_points', $total_points );
}	


/*
* Function to Revoke Ranks
*/
function badgeos_mepr_revoke_rank_function( $user_id, $product_id ) {
	
	global $wpdb;
	
	$settings = get_option( 'badgeos_settings' );
	$step = trim( $settings['ranks_step_post_type'] ) ;

	$ranks = $wpdb->get_results( 
			"SELECT * FROM {$wpdb->prefix}badgeos_ranks WHERE
			user_id = {$user_id} AND rank_type = '{$step}'" 
		);
	foreach ($ranks as $rank) {
		$step_params = get_post_meta( $rank->rank_id, '_badgeos_fields_data', true );
		$step_params = badgeos_extract_array_from_query_params( $step_params );
		$post_to_revoke_id = $step_params['badgeos_mepr_membership_id'];
		
		if ( ! empty ( $post_to_revoke_id ) && $product_id ==  $post_to_revoke_id ) {
			badgeos_revoke_rank_from_user_account( $user_id, badgeos_get_parent_id( $rank->rank_id ) );
		} else if ( $product_id == get_post_meta( badgeos_get_parent_id( $rank->rank_id ), 'mepr_rank_product_id', true ) ) {
			badgeos_revoke_rank_from_user_account( $user_id, badgeos_get_parent_id( $rank->rank_id ) );
		}
	}
}

/*
* Function to Revoke Achievement
*/
function badgeos_mepr_revoke_achievement_function( $user_id, $product_id ) {

	global $wpdb;

	$query = "DELETE FROM {$wpdb->prefix}badgeos_achievements WHERE ID=%d ORDER BY entry_id DESC LIMIT 1;";
	
	$settings = get_option( 'badgeos_settings' );
	$step = trim( $settings['achievement_step_post_type'] ) ;

	$achievements = $wpdb->get_results( 
			"SELECT * FROM {$wpdb->prefix}badgeos_achievements WHERE
			user_id = {$user_id} AND post_type = '{$step}'" 
		);

	foreach ($achievements as $achievement) {
		$step_params = get_post_meta( $achievement->ID, '_badgeos_fields_data', true );
		$step_params = badgeos_extract_array_from_query_params( $step_params );
		$post_to_revoke_id = $step_params['badgeos_mepr_membership_id'];
        $parents = badgeos_get_achievements( array( 'parent_of' => $achievement->ID ) );
		

		if ( ! empty( $post_to_revoke_id ) || $product_id ==  $post_to_revoke_id ) {
			// drop child of achievement
			$wpdb->query($wpdb->prepare($query, $achievement->ID));

			badgeos_decrement_user_trigger_count( $user_id, $achievement->ID, badgeos_get_parent_id( $parents[0]->ID ) ) ;	
			// drop achievement itself
			$wpdb->query($wpdb->prepare( $query, $parents[0]->ID ) );
		} else if ( $product_id == get_post_meta( $parents[0]->ID, 'mepr_achievement_product_id', true ) ) {
			// drop child of achievement
			$wpdb->query($wpdb->prepare($query, $achievement->ID));

			badgeos_decrement_user_trigger_count( $user_id, $achievement->ID, badgeos_get_parent_id( $parents[0]->ID ) ) ;	

			// drop achievement itself
			$wpdb->query($wpdb->prepare( $query, $parents[0]->ID ) );
		}
	}

	//Grab the user's earned achievements
	$user_earned_achievements = badgeos_get_user_achievements( array( 'user_id' => $user_id ) );

	// Update user's earned achievements
	badgeos_update_user_achievements( array(
		'user_id'          => $user_id,
		'all_achievements' => array_values($user_earned_achievements)
	));
}

/*
* Award Achievement/Ranks for Recurring Subscription
*/
function mepr_subscription_status_active_cb( $obj ){
	
	$user_id 	= $obj->user_id;
	$product_id = $obj->product_id;
	do_action('badgeos_mepr_subscribed_paid_membership', array($product_id, $user_id) );
	do_action('badgeos_mepr_subscribed_any_membership',  array($product_id, $user_id) );
}
add_action('mepr_subscription_status_active','mepr_subscription_status_active_cb');

/*
* Award Achievement/Ranks for Non-Recurring Subscription
*/
function mepr_capture_new_one_time_subscription_cb($event) {
  
  	$obj = $event->get_data();
  	$user_id 	= $obj->user_id;
	$product_id = $obj->product_id;
	$amount 	= absint( $obj->amount );
	

	if ( $amount > 0 ) {
		do_action('badgeos_mepr_subscribed_paid_membership', array($product_id, $user_id) );
		do_action('badgeos_mepr_subscribed_any_membership',  array($product_id, $user_id) );
	} 

	if ( $amount == 0){
		do_action('badgeos_mepr_subscribed_free_membership', array($product_id, $user_id) );
		do_action('badgeos_mepr_subscribed_any_membership',  array($product_id, $user_id) );
	}

}
add_action('mepr-event-non-recurring-transaction-completed', 'mepr_capture_new_one_time_subscription_cb', 10, 1);


function badgeos_is_achievement_cb($return, $post_type){
	
	$badgeos_settings = ( $exists = get_option( 'badgeos_settings' ) ) ? $exists : array();
	if ( $badgeos_settings['achievement_step_post_type'] ==  get_post_type( $post_type ))
		return true;

	if ( in_array( get_post_type( $post_type ), badgeos_get_achievement_types_slugs() ) ) {
		return true;
	}

}
add_filter('badgeos_is_achievement' , 'badgeos_is_achievement_cb', 15, 2);

/**
 * Helper function to configure cron jobs
 */
function badgeos_mepr_subscription_expire_cron_job() {
    
    if ( ! wp_next_scheduled ( 'cron_badgeos_mepr_subscription_expire' ) ) {
        wp_schedule_single_event( strtotime( '+1 day' ), 'cron_badgeos_mepr_subscription_expire' );
    }
}
add_action( 'init', 'badgeos_mepr_subscription_expire_cron_job' );


/*
* Get all memberships [recurring/non-recurring]
* Check for expire
* Check active/in-active status
* Revoke Achievements/Ranks
*/
function cron_badgeos_mepr_subscription_expire_cb() {

    global $wpdb;

	// Get all transaction
	$transactions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mepr_transactions");

	foreach ($transactions as $transaction) {
		
		$subscription_id = $transaction->subscription_id;
		$product_id = $transaction->product_id;
		$expires_at = $transaction->expires_at;
		$user_id = $transaction->user_id;

		// [ subscription_id == 0 ] means that subscription is non-recurring
		if ( $subscription_id == 0 ) {
			if ( strtotime('now') > strtotime($expires_at) ) {
				badgeos_mepr_revoke_rank_function( $user_id, $product_id );
				badgeos_mepr_revoke_achievement_function( $user_id, $product_id );
			}
		}
		
		// [ subscription_id > 0 ] means that subscription is recurring
		if ( $subscription_id > 0 ) {
			$user = new MeprUser($user_id);
			$is_subscribe = $user->is_already_subscribed_to($product_id);
			
			$subscription = $wpdb->get_results("SELECT status FROM {$wpdb->prefix}mepr_subscriptions where id = {$subscription_id}");
			$subscription_status = $subscription[0]->status;
			
			if ( ! $is_subscribe ) {
				if ( strtotime('now') > strtotime($expires_at) ) {
					badgeos_mepr_revoke_rank_function( $user_id, $product_id );
					badgeos_mepr_revoke_achievement_function( $user_id, $product_id );
				}
			}
		}
	}	
}
add_action( 'cron_badgeos_mepr_subscription_expire', 'cron_badgeos_mepr_subscription_expire_cb' );
