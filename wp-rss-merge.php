<?php
/*
Plugin Name: RSS Merge
Plugin URI: https://github.com/stephenyeargin/wp-rss-merge
Description: Parses mutliple RSS feeds into a single widget
Author: Stephen Yeargin
Version: 1.0
Author URI: http://stephenyeargin.com/
*/

/**
 * RSSMerge Class
 */
class RSSMerge {

  public $config;
  public $data;
  
  /**
   * Constructor
   */
  function __construct($urls, $count=1) {
    $this->config = array();
    $this->config['urls'] = (string) $urls;
    $this->config['count'] = (int) $count;

    $this->processUrlsToArray();

    // Go ahead and make query
    $this->getFeedData();
    
    uksort($this->data, 'self::sortFeedByDate');
    
    $this->data = array_slice($this->data, 0, $count);
    
  }

  /**
   * Process URLs to Array
   */
  private function processUrlsToArray() {
    $urls = explode("\n", $this->config['urls']);
    $this->config['urls'] = array();
    foreach ($urls as $url):
        $this->config['urls'][] = trim($url);
    endforeach;
  }

  /**
   * Get Feed Data
   */
  private function getFeedData() {

    // Needs library to work reliably
    include_once(ABSPATH.WPINC.'/rss.php');

    $urls = $this->config['urls'];
    
    // If empty URL list, don't bother
    if (empty($urls))
      return false;

    // Loop retrieval and cache using built-in methods
    $data = array();
    foreach ($urls as $url):
      $url = trim($url);
      $feed = fetch_rss($url);
      
      if (!is_object($feed))
        continue;
            
      // Grab channel data from response
      foreach ($feed->items as $k => $item):
        $metadata[$k] = (array) $feed->channel;
      endforeach;
      
      // Setting up list of items
      $items = $feed->items;
      
      // Merge meta data with items
      foreach ($items as $k=>$row):
        $items[$k]['channel'] = $metadata[$k];    
      endforeach;
      
      // Merging feed items together
      $data = array_merge($data, $items);
    endforeach;

    $this->data = $data;
    return;
  }


  /**
   * Show a time since for a given date
   *
   * @param string  Timestamp as a string
   * @return  string  Calculated time since
   * @url   http://www.php.net/manual/en/function.time.php#89415
   */
  private function formatTime($date) {
      if (empty($date)) {
          return "No date provided";
      }
      $periods         = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
      $lengths         = array("60","60","24","7","4.35","12","10");
      $now             = time();
      $unix_date       = strtotime($date);
      // check validity of date
      if(empty($unix_date)) {
          return "Bad date";
      }
      // is it future date or past date
      if($now > $unix_date) {
          $difference     = $now - $unix_date;
          $tense         = "ago";
      } else {
          $difference     = $unix_date - $now;
          $tense         = "from now";
      }
      for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
          $difference /= $lengths[$j];
      }
      $difference = round($difference);
      if($difference != 1) {
          $periods[$j].= "s";
      }
      return "$difference $periods[$j] {$tense}";
  }
  
  /**
   * Sort Feed By Date
   *
   * @param string $b
   * @param string $a
   */
  private function sortFeedByDate($b, $a) {
    $array = $this->data;
    return strcmp( strtotime($array[$a]['pubdate']), strtotime($array[$b]['pubdate']));
  }

}

/**
 * RSSMerge Widget Class
 */
class RSSMergeWidget extends WP_Widget {

  function __construct() {

    /* Widget settings. */
    $widget_ops = array( 'classname'=>'rssmerge-widget', 'description'=>__('Multi-feed RSS') );

    /* Widget control settings. */
    $control_ops = array( 'id_base'=>'rssmerge-widget', 'width'=>300  );

    parent::__construct( 'rssmerge-widget', __('Multi-Feed Widget', 'rssmerge'), $widget_ops, $control_ops );
  }

  function widget( $args, $instance ) {

    // Get configurable stuff
    extract($args);

    $urls  = $instance['urls'];
    $count = (int) $instance['count'];

    $feeditems = new RSSMerge($urls, $count);

    /* Begin display for widget */

    print $before_widget;

    if (!empty($instance['title']))
      print $before_title . $instance['title'] . $after_title;

    print '<ul>';
    foreach ($feeditems->data as $item):
      printf('<li><a href="%s">%s: %s</a></li>' . "\n", __($item['link']), __($item['channel']['title']), __($item['title']) );
    endforeach;
    print '</ul>';

    print $after_widget;
  }

  function update( $new_instance, $old_instance ) {
    $instance = $old_instance;
    $instance['title'] = $new_instance['title'];
    $instance['urls'] = strip_tags($new_instance['urls']);
    $instance['count'] = (int) $new_instance['count'];
    return $instance;
  }

  function form( $instance ) {

    $defaults = array( 'urls' => 'http://wordpress.org/news/feed/'."\n".'http://stephenyeargin.com/feed/', 'count' => 10 );
    $instance = wp_parse_args( (array) $instance, $defaults );

    printf('<p><label>Title <input type="text" name="%s" id="%s" value="%s" /></label></p>',
      $this->get_field_name('title'), $this->get_field_id('title'), htmlentities($instance['title']));
    printf('<p><label>Feed URLs <textarea name="%s" id="%s" style="width:300px;">%s</textarea></label></p>',
      $this->get_field_name('urls'), $this->get_field_id('urls'), __($instance['urls']));
    printf('<p><label>Tweet Count <input type="text" name="%s" id="%s" value="%d" /></label></p>',
      $this->get_field_name('count'), $this->get_field_id('count'), $instance['count']);
  }
}

function rssmerge_register_widgets() {
  register_widget( 'RSSMergeWidget' );
}

add_action( 'widgets_init', 'rssmerge_register_widgets' );


/**
 * Example Non-widget Usage
 *
 * @param string $username
 * @param int $count
 */
function theme_rssmerge($urls, $count=1) {

  if (!class_exists('RSSMerge'))
    return false;

  // Get feed items
  $feeditems = new RSSMerge($urls, $count);

  print '<ul>';
  foreach ($feeditems->data as $item):
    printf('<li><a href="%s">%s: %s</a></li>' . "\n", __($item['link']), __($item['channel']['title']), __($item['title']) );
  endforeach;
  print '</ul>';
    
}

