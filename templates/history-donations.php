<?php
/**
 * This template is used to display the donation history of the current user.
 */

$donation_history_args = Give()->session->get( 'give_donation_history_args' );

// User's Donations.
if ( is_user_logged_in() ) {
	$donations = give_get_users_donations( get_current_user_id(), 20, true, 'any' );
} elseif ( Give()->email_access->token_exists ) {
	// Email Access Token?
	$donations = give_get_users_donations( 0, 20, true, 'any' );
} elseif ( Give()->session->get_session_expiration() !== false ) {
	// Session active?
	$email     = Give()->session->get( 'give_email' );
	$donations = give_get_users_donations( $email, 20, true, 'any' );
}

if ( $donations ) : ?>
	<table id="give_user_history" class="give-table">
		<thead>
		<tr class="give-donation-row">
			<?php
			/**
			 * Fires in current user donation history table, before the header row start.
			 *
			 * Allows you to add new <th> elements to the header, before other headers in the row.
			 *
			 * @since 1.7
			 */
			do_action( 'give_donation_history_header_before' );

			if ( filter_var( $donation_history_args['id'], FILTER_VALIDATE_BOOLEAN ) ) :
				?>
				<th scope="col" class="give-donation-id"><?php _e( 'ID', 'give' ); ?></th>
				<?php
			endif;
			if ( filter_var( $donation_history_args['date'], FILTER_VALIDATE_BOOLEAN ) ) :
				?>
				<th scope="col" class="give-donation-date"><?php _e( 'Date', 'give' ); ?></th>
				<?php
			endif;
			if ( filter_var( $donation_history_args['donor'], FILTER_VALIDATE_BOOLEAN ) ) :
				?>
				<th scope="col" class="give-donation-donor"><?php _e( 'Donor', 'give' ); ?></th>
				<?php
			endif;
			if ( filter_var( $donation_history_args['amount'], FILTER_VALIDATE_BOOLEAN ) ) :
				?>
				<th scope="col" class="give-donation-amount"><?php _e( 'Amount', 'give' ); ?></th>
				<?php
			endif;
			if ( filter_var( $donation_history_args['status'], FILTER_VALIDATE_BOOLEAN ) ) :
				?>
				<th scope="col" class="give-donation-status"><?php _e( 'Status', 'give' ); ?></th>
				<?php
			endif;
			if ( filter_var( $donation_history_args['payment_method'], FILTER_VALIDATE_BOOLEAN ) ) :
				?>
				<th scope="col" class="give-donation-payment-method"><?php _e( 'Payment Method', 'give' ); ?></th>
			<?php endif; ?>
			<th scope="col" class="give-donation-details"><?php _e( 'Details', 'give' ); ?></th>
			<?php
			/**
			 * Fires in current user donation history table, after the header row ends.
			 *
			 * Allows you to add new <th> elements to the header, after other headers in the row.
			 *
			 * @since 1.7
			 */
			do_action( 'give_donation_history_header_after' );
			?>
		</tr>
		</thead>
		<?php foreach ( $donations as $post ) :
			setup_postdata( $post );
			$donation_data = give_get_payment_meta( $post->ID ); ?>
			<tr class="give-donation-row">
				<?php
				/**
				 * Fires in current user donation history table, before the row statrs.
				 *
				 * Allows you to add new <td> elements to the row, before other elements in the row.
				 *
				 * @since 1.7
				 *
				 * @param int   $post_id       The ID of the post.
				 * @param mixed $donation_data Payment meta data.
				 */
				do_action( 'give_donation_history_row_start', $post->ID, $donation_data );

				if ( filter_var( $donation_history_args['id'], FILTER_VALIDATE_BOOLEAN ) ) :
					?>
					<td class="give-donation-id">#<?php echo give_get_payment_number( $post->ID ); ?></td>
					<?php
				endif;
				if ( filter_var( $donation_history_args['date'], FILTER_VALIDATE_BOOLEAN ) ) :
					?>
					<td class="give-donation-date"><?php echo date_i18n( give_date_format(), strtotime( get_post_field( 'post_date', $post->ID ) ) ); ?></td>
					<?php
				endif;
				if ( filter_var( $donation_history_args['donor'], FILTER_VALIDATE_BOOLEAN ) ) :
					?>
					<td class="give-donation-donor"><?php echo give_get_donor_name_by( $post->ID ); ?></td>
					<?php
				endif;
				if ( filter_var( $donation_history_args['amount'], FILTER_VALIDATE_BOOLEAN ) ) :
					?>
					<td class="give-donation-amount">
					<span class="give-donation-amount">
					<?php
					$currency_code = give_get_payment_currency_code( $post->ID );
					$donation_amount = give_currency_filter(
						give_format_amount( give_get_payment_amount( $post->ID ), array(
							'sanitize' => false,
							'currency' => $currency_code
						) ),
						$currency_code
					);

					/**
					 * Filters the donation amount on Donation History Page.
					 *
					 * @param int $donation_amount Donation Amount.
					 * @param int $post_id         Donation ID.
					 *
					 * @since 1.8.13
					 *
					 * @return int
					 */
					echo apply_filters( 'give_donation_history_row_amount', $donation_amount, $post->ID );
					?>
					</span>
					</td>
					<?php
				endif;
				if ( filter_var( $donation_history_args['status'], FILTER_VALIDATE_BOOLEAN ) ) :
					?>
					<td class="give-donation-status"><?php echo give_get_payment_status( $post, true ); ?></td>
					<?php
				endif;
				if ( filter_var( $donation_history_args['payment_method'], FILTER_VALIDATE_BOOLEAN ) ) :
					?>
					<td class="give-donation-payment-method"><?php echo give_get_gateway_checkout_label( give_get_payment_gateway( $post->ID ) ); ?></td>
				<?php endif; ?>
				<td class="give-donation-details">
					<?php
					// Display View Receipt or.
					if ( 'publish' !== $post->post_status
					     && 'subscription' !== $post->post_status
					) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'payment_key', give_get_payment_key( $post->ID ), give_get_history_page_uri() ) ); ?>"><span
									class="give-donation-status <?php echo $post->post_status; ?>"><?php echo __( 'View', 'give' ) . ' ' . give_get_payment_status( $post, true ) . ' &raquo;'; ?></span></a>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( 'payment_key', give_get_payment_key( $post->ID ), give_get_history_page_uri() ) ); ?>"><?php _e( 'View Receipt &raquo;', 'give' ); ?></a>
					<?php endif; ?>
				</td>
				<?php
				/**
				 * Fires in current user donation history table, after the row ends.
				 *
				 * Allows you to add new <td> elements to the row, after other elements in the row.
				 *
				 * @since 1.7
				 *
				 * @param int   $post_id       The ID of the post.
				 * @param mixed $donation_data Payment meta data.
				 */
				do_action( 'give_donation_history_row_end', $post->ID, $donation_data );
				?>
			</tr>
		<?php endforeach; ?>
	</table>
	<div id="give-donation-history-pagination" class="give_pagination navigation">
		<?php
		$big = 999999;
		echo paginate_links( array(
			'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
			'format'  => '?paged=%#%',
			'current' => max( 1, get_query_var( 'paged' ) ),
			'total'   => ceil( give_count_donations_of_donor() / 20 ) // 20 items per page
		) );
		?>
	</div>
	<?php wp_reset_postdata(); ?>
<?php else : ?>
	<?php Give()->notices->print_frontend_notice( __( 'It looks like you haven\'t made any donations.', 'give' ), true, 'success' ); ?>
<?php endif;
