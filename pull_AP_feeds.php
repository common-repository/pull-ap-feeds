<?php
/*
Plugin Name: Pull AP Feeds
Plugin URI: http://thomashallock.com/
Description: This plugin pulls AP feeds from apexchange.com
Version: 0.0004
Author: Thomas Hallock
Author URI: http://thomashallock.com/
*/ 

require_once(dirname(__FILE__)."/lib/build_url.php");
require_once(dirname(__FILE__)."/lib/simple_xml_extended.php");

register_activation_hook(__FILE__, 'ap_feeds_activation');
add_action('pull_ap_feeds', 'pull_ap_feeds_function');

$update_period = 'every_20_min';

function more_cron() {
    $cron_sched['every_20_min'] = array(
                    'interval' => 60*20,
                    'display' => __('Every 20 minutes')
    );
    $cron_sched['every_5_min'] = array(
                    'interval' => 60*5,
                    'display' => __('Every 5 minutes')
    );
    $cron_sched['every_1_min'] = array(
                    'interval' => 60,
                    'display' => __('Every 1 minute')
    );
    $cron_sched['every_30_secs'] = array(
                    'interval' => 30,
                    'display' => __('Every 30 seconds')
    );
    $cron_sched['every_1_sec'] = array(
                    'interval' => 1,
                    'display' => __('Every 1 second')
    );

    return $cron_sched;

}
add_filter('cron_schedules', 'more_cron');

function ap_feeds_activation() {
        wp_schedule_event(time(), '$update_period', 'pull_ap_feeds');
}

register_deactivation_hook(__FILE__, 'ap_feeds_deactivation');

function ap_feeds_deactivation() {
	wp_clear_scheduled_hook('pull_ap_feeds');
}



function curl_load($url, $username = null, $password = null) {
    $ch = curl_init();
    update_option("last_ap_url_fetch", $url);
    curl_setopt($ch, CURLOPT_URL, $url);

    // Set your login and password for authentication
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

    // You can use CURLAUTH_BASIC, CURLAUTH_DIGEST, CURLAUTH_GSSNEGOTIATE,
    // CURLAUTH_NTLM, CURLAUTH_ANY, and CURLAUTH_ANYSAFE
    //
    // You can use the bitwise | (or) operator to combine more than one method.
    // If you do this, CURL will poll the server to see what methods it supports and pick the best one.
    //
    // CURLAUTH_ANY is an alias for CURLAUTH_BASIC | CURLAUTH_DIGEST |
    // CURLAUTH_GSSNEGOTIATE | CURLAUTH_NTLM
    //
    // CURLAUTH_ANYSAFE is an alias for CURLAUTH_DIGEST | CURLAUTH_GSSNEGOTIATE |
    // CURLAUTH_NTLM
    //
    // Personally I prefer CURLAUTH_ANY as it covers all bases
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);

    // This is occassionally required to stop CURL from verifying the peer's certificate.
    // CURLOPT_SSL_VERIFYHOST may also need to be TRUE or FALSE if
    // CURLOPT_SSL_VERIFYPEER is disabled (it defaults to 2 - check the existence of a
    // common name and also verify that it matches the hostname provided)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Optional: Return the result instead of printing it
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // The usual - get the data and close the session
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function pull_ap_feeds_function() {
    global $wpdb;

    $url_params = array(
        'idList' => get_option('ap_saved_search_id'),
        'idListType' => 'savedsearches',
        'maxItems' => 100,
        'minDateTime' => '',
        'maxDateTime' => '',
        'fullContent' => 'true',
        'showInlineLinks' => 'true',
//        'compression' => 'true' // anybody know what php function / library can be used to decompress this?
    );

    $ap_sequence_number = get_option('ap_sequence_number');
    $ap_min_date_time = get_option('ap_min_date_time');

    if($ap_sequence_number)
        $url_params['sequenceNumber'] = $ap_sequence_number;
    if($ap_min_date_time)
        $url_params['minDateTime'] = $ap_min_date_time;


    $base_url = "http://syndication.ap.org/AP.Distro.Feed/GetFeed.aspx".build_url($url_params);

    $data = curl_load($base_url, get_option("ap_username"), get_option("ap_password"));


    file_put_contents("/tmp/ap_webfeeds_output", $data);
    $time = time();
    update_option("last_ap_fetch_time", $time);


    $sequence_number_regex = <<<REGEXP
    /Property Name="sequenceNumber" Id="([0-9]*)"/
REGEXP;

    $min_date_time_regex = <<<REGEXP
    /Property Name="minDateTime" Value="(.*)"/
REGEXP;

    $matches = array();
    preg_match ( $sequence_number_regex , $data, $matches );
    $new_ap_sequence_number = $matches[1];
    update_option('ap_sequence_number', $matches[1]);
    preg_match ( $min_date_time_regex , $data, $matches );
    update_option('ap_min_date_time', $matches[1]);


    $xml_parsed = simplexml_load_string($data);
    $num_fetched = 0;
    $num_duplicates = 0;
    foreach($xml_parsed->entry as $article) {
        $body_content = "body.content";
        $body_head = "body.head";
        if(isset($article->content->nitf->body->$body_content->block)) {
            $the_id = simple_xml_object_attribute(value_or_false($article->content->nitf->body->$body_content->block), 'id');

            if(0==strcmp(strtolower($the_id),  'caption' )) // if it's a caption, make it a draft
                    $post_status = "draft";
            else
                    $post_status = "publish";
            if(isset($article->content->nitf->body->$body_content->block->p)) {
                $article_content = "";
                foreach($article->content->nitf->body->$body_content->block->p as $the_line) {
                    $article_content .= "<p>".$the_line."</p>\n";
                }
                $title = $article->content->nitf->body->$body_head->hedline->hl1;

                        // add posts to db here
                        // wp_insert_post();
                // http://codex.wordpress.org/Function_Reference/wp_insert_post


                // if the title of the story matches another story that is within three days of this one, then update the story.
                if(!$wpdb->get_results($wpdb->prepare("select ID from {$wpdb->posts} where post_title = %s and abs(datediff(post_date, %s)) < 3 ", $title, $article->published ))) {
                $dup_check_query = $wpdb->last_query;

                    $post = array(
                    //  'ID' => [ <post id> ], //Are you updating an existing post?
                    //  'menu_order' => [ <order> ], //If new post is a page, sets the order should it appear in the tabs.
                      'comment_status' => 'closed', // [ 'closed' | 'open' ] // 'closed' means no comments.
                      'ping_status' => 'open', //[ 'closed' | 'open' ] // 'closed' means pingbacks or trackbacks turned off
                    //  'pinged' => [ ? ], //?
                      'post_author' => get_option('ap_default_author'), //[ <user ID> ] //The user ID number of the author.
                      'post_category' => array(get_option('ap_feed_category_id')), // [ array(<category id>, <...>) ] //Add some categories.
                      'post_content' => $article_content, //[ <the text of the post> ] //The full text of the post.
                      'post_date' => date( 'Y-m-d H:i:s', strtotime($article->published)), // ] //The time post was made.
                    //  'post_date_gmt' => [ Y-m-d H:i:s ] //The time post was made, in GMT.
                    //  'post_excerpt' => [ <an excerpt> ] //For all your post excerpt needs.
                    //  'post_name' => [ <the name> ] // The name (slug) for your post
                    //  'post_parent' => [ <post ID> ] //Sets the parent of the new post.
                    //  'post_password' => [ ? ] //password for post?
                      'post_status' => $post_status, // [ 'draft' | 'publish' | 'pending' ] //Set the status of the new post.
                      'post_title' => ucwords($title), //[ <the title> ] //The title of your post.
                      'post_type' => 'post', //[ 'post' | 'page' ] //Sometimes you want to post a page.
                      // 'tags_input' => array('associated_press'), //[ '<tag>, <tag>, <...>' ] //For tags.
                    //  'to_ping' => [ ? ] //?
                    );

                    $post_id = wp_insert_post($post);
                    add_post_meta($post_id, "duplicate_check_query", $dup_check_query , false);
                    add_post_meta($post_id, "from_ap_query", $base_url , false);
                    add_post_meta($post_id, "ap_content_type", $the_id, false);
                    ++$num_fetched;
                } else {
                    ++$num_duplicates;
                }
            }
        }
    }

    if($num_fetched) {
        update_option("last_ap_url_fetch_successful", $base_url);
        update_option("ap_last_successful_fetch_count", $num_fetched);
        update_option("ap_last_fetch_count", $num_fetched);
        update_option("last_ap_timestamp", strtotime($xml_parsed->updated));
    }
    update_option("ap_last_fetch_count", $num_fetched);
    update_option("ap_last_fetch_num_duplicates", $num_duplicates);

}
// see: 
// http://codex.wordpress.org/Function_Reference/wp_schedule_event
// http://codex.wordpress.org/Function_Reference/wp_cron
// http://codex.wordpress.org/Category:WP-Cron_Functions


add_action('admin_menu', 'ap_feeds_menu');

function ap_feeds_menu() {
  add_options_page('Manage your AP Feeds', 'AP feed settings', 'edit_posts', __FILE__, 'ap_feeds_options');
}

function ap_feeds_options() {
    global $update_period;
    $updated_something = false;
    $updatable_options = array(
        'ap_feed_category_id',
        'ap_saved_search_id',
        'ap_username',
        'ap_password',
        'ap_default_author',


        'update_feed', // make sure that this one is always at the bottom
    );

    if($_POST) {
        if($_POST['update_feed']) {
            pull_ap_feeds_function();
        }
        if($_POST['do_update']) {
            wp_clear_scheduled_hook('pull_ap_feeds'); // whatever happens, clear the event; we'll re-set it later if we need to
            if($_REQUEST['enable_cron_updates']) {
                wp_schedule_event(time(), $_REQUEST['form_update_period'], 'pull_ap_feeds'); // we need to re-set the event
            }
    //                    update_option($option_name, $_REQUEST[$option_name]);
            foreach($updatable_options as $option_name) {
                if(isset($_REQUEST[$option_name])) {
                    switch($option_name) {
                        case 'update_feed':

                            break;
                        case 'ap_saved_search_id':
                            if($_REQUEST['ap_saved_search_id'] != get_option('ap_saved_search_id')) {
                                delete_option('ap_sequence_number');
                                delete_option('ap_min_date_time');
                            }
                        default:
                            update_option($option_name, $_REQUEST[$option_name]);
                    }
                }
            }
            wp_safe_redirect("http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']); // redirect so page reloads will be idempotent
        }
    }
    echo "<form method = 'post' action = '{$_SERVER['REQUEST_URI']}' >";
    echo "<input type = 'hidden' name = 'do_update' value = 'true' />";
    echo "<table>";


//    "BLACK_webfeeds", "ap116");
    echo "<tr><td>AP username:</td><td><input type = 'text' name = 'ap_username' value = '".get_option("ap_username")."' /></td></tr>";
    echo "<tr><td>AP password:</td><td><input type = 'text' name = 'ap_password' value = '".get_option("ap_password")."' /></td></tr>";
    echo "<tr><td>AP saved search ID:</td><td><input type = 'text' name = 'ap_saved_search_id' value = '".get_option('ap_saved_search_id')."' \></td></tr>";
    echo "<tr><td>Insert new articles into category with ID:</td><td><input type = 'text' name = 'ap_feed_category_id' value = '".get_option('ap_feed_category_id')."'></td></tr>";
    echo "<tr><td>New articles will default to author with ID:</td><td><input type = 'text' name = 'ap_default_author' value = '".get_option('ap_default_author')."'></td></tr>";
    $schedules = wp_get_schedules();
    $current_update_period = wp_get_schedule('pull_ap_feeds');

    if($current_update_period) {
        $form_update_period = $current_update_period;
    } else {
        $form_update_period = $update_period;
    }

    $schedule_label = strtolower($schedules[$form_update_period]['display']);

    echo "<tr><td colspan = 2><label><input type = 'checkbox' name = 'enable_cron_updates' value = '1' ".($current_update_period ? 'checked' : '')." /> Automatically get new articles ".$schedule_label."</label></td></tr>";



    echo "</table>";
    echo "<input type = 'hidden' name = 'form_update_period' value = '$form_update_period' />";
    echo "<input type = 'submit' value = 'update settings' />";
    echo "</form>";

    $last_ap_timestamp = get_option('last_ap_timestamp');
    $last_ap_fetch_time = get_option('last_ap_fetch_time');

    if($last_ap_timestamp)
        $last_ap_timestamp = date('r', $last_ap_timestamp);
    else
        $last_ap_timestamp = "never";

    if($last_ap_fetch_time)
        $last_ap_fetch_time = date('r', $last_ap_fetch_time);
    else
        $last_ap_fetch_time = "never";



    echo "<h2>Information about the last AP request / response:</h2>";
    echo "<ul>";
    echo "<li>Last AP timestamp: ".$last_ap_timestamp."</li>";
    echo "<li>Last AP fetch attempt: ".$last_ap_fetch_time."</li>";
    echo "<li>Number of duplicated articles ignored from last fetch: ".get_option("ap_last_fetch_num_duplicates")."</li>";
    echo "<li>Number of articles fetched on last update where articles were fetched: ".get_option("ap_last_successful_fetch_count")."</li>";
    echo "<li>Last AP URL queried where articles were fetched: <tt>".htmlentities(get_option("last_ap_url_fetch_successful"))."</tt></li>";
    echo "<li>Last AP URL queried: <tt>".htmlentities(get_option("last_ap_url_fetch"))."</tt></li>";
    echo "<li>Number of articles fetched on last update: ".get_option("ap_last_fetch_count")."</li>";
    echo "<li>Min Date Time: ".get_option('ap_min_date_time')."</li>";
    echo "<li>Sequence number: ".get_option('ap_sequence_number')."</li>";
    echo "</ul>";
    echo "<form method = 'post' action = '{$_SERVER['REQUEST_URI']}' >";
    echo "<input type = 'hidden' name = 'update_feed' value = '1' />";
    echo "<input type = 'submit' value = 'get new articles' />";
    echo "</form>";


//echo "<pre>";
//$xml_parsed = simplexml_load_string(file_get_contents("/tmp/ap_webfeeds_output"));
//echo print_r($xml_parsed);
//echo "</pre>";

}


?>
