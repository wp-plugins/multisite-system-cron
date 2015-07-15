<?php

/**
 * MultiSite System Cron Admin class
 *
 * @package MultiSite System Cron
 * @subpackage MultiSite System Cron Admin
 */
class BE_MSSC_Admin {



	// Initialization
	// ---------------------------------------------------------------------------------------------------



	/**
	 * Start network admin
	 */
	public static function init() {
		add_action('network_admin_menu', array(__CLASS__, 'network_admin_menu'));		
	}



	/**
	 * Check settings at activation
	 */
	public static function activation() {
		
		// Check elapsed time
		$timeout = (int) get_site_option('be_mssc_timeout');
		if (empty($timeout))
			update_site_option('be_mssc_timeout', 300);
		
		// Check sleep between cron calls
		$sleep = (int) get_site_option('be_mssc_sleep');
		if (empty($sleep))
			update_site_option('be_mssc_sleep', 10);
		
		// Set default values
		update_site_option('be_mssc_status', 0);
		update_site_option('be_mssc_log', 0);
	}



	/**
	 *  Load translation file
	 */
	private static function load_plugin_textdomain($lang_dir = 'languages') {
		
		// Check load
		static $loaded;
		if (isset($loaded))
			return;
		$loaded = true;
		
		// Check if this plugin is placed in wp-content/mu-plugins directory or subdirectory
		if (('mu-plugins' == basename(dirname(__FILE__)) || 'mu-plugins' == basename(dirname(dirname(__FILE__)))) && function_exists('load_muplugin_textdomain')) {
			load_muplugin_textdomain('msscron', ('mu-plugins' == basename(dirname(__FILE__))? '' : basename(dirname(__FILE__)).'/').$lang_dir);
		
		// Usual wp-content/plugins directory location
		} else {
			load_plugin_textdomain('msscron', false, basename(dirname(__FILE__)).'/'.$lang_dir);
		}
	}



	// Admin Page
	// ---------------------------------------------------------------------------------------------------



	/**
	 * Network admin menu hook
	 */
	public static function network_admin_menu() {
		add_submenu_page('settings.php', 'MultiSite System CRON', 'MultiSite System CRON', 'manage_network', 'mssc-admin', array(__CLASS__, 'network_admin_page'));
	}



	/**
	 * Network admin page
	 */
	public static function network_admin_page() {
		
		// Load translations
		self::load_plugin_textdomain();
		
		?><div class="wrap">
		
			<?php
			
			// Check constant
			$disable_wp_cron = (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON);
			if (!$disable_wp_cron)
				echo '<div class="error"><p>'.__('Constant <code>DISABLE_WP_CRON</code> not initialized. The WordPress cron is still active.', 'msscron').'</p></div>'."\n";
				
			// Check static keys
			$key_main = get_site_option('be_mssc_key_main');
			$key_exec = get_site_option('be_mssc_key_exec');
			if (empty($key_main) || empty($key_exec))
				self::regenerate_keys();
			
			// Number of blogs
			global $wpdb, $site_id;
			$blogs_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->blogs} WHERE site_id = %d AND public = 1", $site_id));
			if (empty($blogs_count))
				echo '<div class="error"><p>'.sprintf(__('Unable to find the number of public blogs for the site_id = %d', 'msscron'), (int) $site_id).'.</p></div>'."\n";
				
			// Check submit
			if (isset($_POST['nonce'])) {
				
				// Check nonce
				if (!wp_verify_nonce($_POST['nonce'], __FILE__))
					return;
				
				// Update values
				update_site_option('be_mssc_status',  (int) $_POST['sl-bemssc-status']);
				update_site_option('be_mssc_dms', 	  (int) $_POST['sl-bemssc-dms']);
				update_site_option('be_mssc_timeout', (int) $_POST['tx-bemssc-timeout']);
				update_site_option('be_mssc_sleep',   (int) $_POST['tx-bemssc-sleep']);
				update_site_option('be_mssc_log', 	  (int) $_POST['sl-bemssc-log']);
				
				// Key regeneration
				if (!empty($_POST['ck-bemssc-keys']))
					self::regenerate_keys();
				
				// Update message
				echo '<div class="updated fade"><p>'.__('Options saved properly.', 'msscron').'</p></div>'."\n";

			}
			
			// Retrieve values
			$status  = (int) get_site_option('be_mssc_status');
			$dms 	 = (int) get_site_option('be_mssc_dms');
			$timeout = (int) get_site_option('be_mssc_timeout');
			$sleep   = (int) get_site_option('be_mssc_sleep');
			$log 	 = (int) get_site_option('be_mssc_log');
			
			// Get keys
			$key_main = get_site_option('be_mssc_key_main');
			$key_exec = get_site_option('be_mssc_key_exec');
			
			// Status
			if (!$status && $disable_wp_cron)
				echo '<div class="error"><p>'.__('Linux cron execution through this plugin is <strong>inactive</strong> (also WP-Cron).', 'msscron').'</p></div>'."\n";

			// Check values
			$warnings = array();
				
			// Timeout
			if (!$timeout)
				$warnings[] = __('You must specify the duration of the main cron in seconds.', 'msscron');

			// Sleep
			if (!$sleep)
				$warnings[] = __('You must specify the seconds between calls to each blog.', 'msscron');
			
			// Check warning
			if (!empty($warnings))
				echo '<div class="error"><p>'.implode('</p><p>', $warnings).'</p></div>'."\n";
			
			// Last period info
			$last_period_message = '';
			$last_period = get_site_option('be_mssc_last_period');
			if (!empty($last_period)) {
				$last_period = explode('-', $last_period);
				if (2 == count($last_period)) {
					$last_started = $last_period[0];
					$last_duration = (int )$last_period[1] - (int) $last_period[0];
					$last_period_message = sprintf(__('The last full period cron started on %s lasted <strong>%d seconds</strong>.', 'msscron'), gmdate('Y-m-d H:i', $last_started + (get_option('gmt_offset') * HOUR_IN_SECONDS)) ,$last_duration);
				}
			}
			
			// Current started
			$current_started_message = '';
			$current_started = (int) get_site_option('be_mssc_started');
			if (!empty($current_started) && time() > $current_started && (empty($last_started) || (!empty($last_started) && $last_started < $current_started)))
				$current_started_message = sprintf(__('The last cron execution started on %s', 'msscron'), gmdate('Y-m-d H:i', $current_started + (get_option('gmt_offset') * HOUR_IN_SECONDS)));
			
			// Current and last execution info
			if (!empty($last_period_message) || !empty($current_started_message))
				echo '<div class="notice"><p><strong>'.__('Execution', 'msscron').'</strong><br />'.$current_started_message.(empty($current_started_message)? '' : '<br />').$last_period_message.'</p></div>'."\n";
			
			// Prepare frecuency data
			if ($disable_wp_cron && !empty($status) && !empty($timeout) && !empty($sleep) && !empty($blogs_count)) {
				$blogs_time = $blogs_count * $sleep;
				$remaining = $timeout - $blogs_time;
				if (0 == $remaining) { $message = '<br />'.__('The cron calls will be processed on schedule.', 'msscron'); }
				elseif ($remaining > 0) { $message = sprintf('<br />'.__('There will be a spare time of <strong>%d seconds</strong>.', 'msscron'), $remaining); }
				elseif ($remaining < 0) { $message = sprintf('<br />'.__('Needed additional <strong style="color: red;">%d seconds</strong> to complete all the blogs cron calls and avoid overlapping processes.', 'msscron'), -1 * $remaining); }
				echo '<div class="'.(($remaining < 0)? 'error' : 'notice').'"><p><strong>'.__('Expected', 'msscron').'</strong><br />'.sprintf(__('The estimated processing time of <strong>%d blogs</strong> will be <strong>%d seconds</strong> out of %d seconds entered.', 'msscron'), $blogs_count, $blogs_time, $timeout).$message.'</p></div>'."\n";
			}
			
			?>
			
			<h2>MultiSite System CRON</h2>
			
			<div id="poststuff">
				
				<div class="postbox">
				
					<h3 class="hndle"><span><?php _e('Settings', 'msscron'); ?></span></h3>
					
					<div class="inside">
						
						<div id="postcustomstuff">
		
							<form method="post" action="<?php echo network_admin_url('settings.php?page=mssc-admin'); ?>">

								<input type="hidden" name="nonce" value="<?php echo wp_create_nonce(__FILE__); ?>" />
								
								<p style="margin-bottom: 25px;"><label for="sl-bemssc-status" style="float: left; width: 225px; padding-top: 4px;"><?php _e('CRON status:', 'msscron'); ?></label>
								<select id="sl-bemssc-status" name="sl-bemssc-status" style="width: 85px;"><option <?php if (empty($status)) echo 'selected'; ?> value="0"><?php _e('Disabled', 'msscron'); ?></option><option <?php if (!empty($status)) echo 'selected'; ?> value="1"><?php _e('ENABLED', 'msscron'); ?></option></select></p>
								
								<p style="margin-bottom: 25px;"><label for="tx-bemssc-timeout" style="float: left; width: 225px;"><?php _e('Time of main script (cron trigger):', 'msscron'); ?></label>
								<input type="text" id ="tx-bemssc-timeout" name="tx-bemssc-timeout" value="<?php echo $timeout; ?>" maxlength="7" style="width: 85px;" /> &nbsp; <small><?php _e('seconds', 'msscron'); ?></small> &nbsp; 
								<?php _e('During this time there will be no overlapping of the main script calls.', 'msscron'); ?></p>
								
								<p style="margin-bottom: 25px;"><label for="tx-bemssc-sleep" style="float: left; width: 225px;"><?php _e('Time between calls to each individual cron blog:', 'msscron'); ?></label>
								<input type="text" id ="tx-bemssc-sleep" name="tx-bemssc-sleep" value="<?php echo $sleep; ?>" maxlength="4" style="width: 85px;" /> &nbsp; <small><?php _e('seconds', 'msscron'); ?></small></p>
								
								<p style="margin-bottom: 25px;"><label for="sl-bemssc-dms" style="float: left; width: 225px; padding-top: 4px;"><?php _e('<strong>Domain Mapping</strong> plugin support:', 'msscron'); ?></label>
								<select id="sl-bemssc-dms" name="sl-bemssc-dms"><option <?php if (empty($dms)) echo 'selected'; ?> value="0"><?php _e('No', 'msscron'); ?></option><option <?php if (!empty($dms)) echo 'selected'; ?> value="1"><?php _e('Yes', 'msscron'); ?></option></select></p>
								
								<p style="margin-bottom: 25px;"><label style="float: left; width: 225px;"><?php _e('Required in wp-config.php', 'msscron'); ?></label>
								<code>define('DISABLE_WP_CRON', true);</code> &nbsp; <strong><?php if ($disable_wp_cron) : ?><span style="color: green;"><?php _e('OK', 'msscron'); ?></span><?php else : ?><span style="color: red;"><?php _e('MISSING', 'msscron'); ?></span><?php endif; ?></strong></p>
								
								<p style="margin-bottom: 25px;"><label style="float: left; width: 225px;"><?php _e('Linux crontab line:', 'msscron'); ?></label>
								<code>* * * * * wget -q -O - <?php echo home_url('wp-cron.php?mssc='.$key_main); ?> &gt;/dev/null 2&gt;&1</code></p>
								
								<p style="margin-bottom: 25px;"><label style="float: left; width: 225px; height: 50px;"><?php _e('Real execution example:', 'msscron'); ?></label>
								<code><?php echo home_url('wp-cron.php?mssce='.$key_exec); ?></code><br /><br /><input type="checkbox" id="ck-bemssc-keys" name="ck-bemssc-keys" value="1" /><label for="ck-bemssc-keys"><?php _e('Regenerate propagation and execution keys <strong>(do not share these URLs with keys)</strong>', 'msscron'); ?></label></p>
								
								<p style="margin-bottom: 25px;"><label for="sl-bemssc-log" style="float: left; width: 225px; padding-top: 4px;"><?php _e('Debug with <i>error_log</i>:', 'msscron'); ?></label>
								<select id="sl-bemssc-log" name="sl-bemssc-log"><option <?php if (empty($log)) echo 'selected'; ?> value="0"><?php _e('No', 'msscron'); ?></option><option <?php if (!empty($log)) echo 'selected'; ?> value="1"><?php _e('Yes', 'msscron'); ?></option></select> &nbsp; <?php _e('Log entries begin with <i>MSSC log:</i>', 'msscron'); ?></p>
								
								<p><input type="submit" value="<?php _e('Save changes', 'msscron'); ?>" class="button-primary" /></p>

							</form>
						
						</div>
					
					</div>
				
				</div>
				
			</div>
			
		</div><?php
	}



	/**
	 * Regenerate main and execution keys
	 */
	function regenerate_keys() {
		update_site_option('be_mssc_key_main', strtolower(wp_generate_password(12, false, false)));
		update_site_option('be_mssc_key_exec', strtolower(wp_generate_password(12, false, false)));
	}



}