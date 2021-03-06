<?php


function get_RSS($url) {
  require_once("vendor/dg/rss-php/src/Feed.php");
  $rss = Feed::loadRss($url);
  return $rss;
}

function unify_resources($resources, $start, $stop) {
  $podcastArray = array();
  if ($resources == null) {
    return false;
  }
  foreach ($resources as $source) {
    if ($source instanceof Source) {
      try {
        $rss = get_RSS($source->src);
      } catch (Exception $e){
        display_err_msg("Cannot get a correct Feed XML object from the given Source</br>Save (file Feeds.php) is corrupted, manual correction required</br></br>".$e);
      }
      $i = 0;
      foreach ($rss->item as $item) {
        if ($i >= $start && $i <= $stop) {
          $podcast = feed_class_podcast($item, $source);
          if ($podcast instanceof Podcast) {
            array_push($podcastArray, $podcast);
          }
          else {
            display_err_msg($podcast."</br>The following feed does not satisfy our requirements : ".$source->src." (".$source->name.")</br>In order to get back to normal state you may want to delete it");
            return;
          }
        }
        $i++;
      }
    }
    else {
      display_err_msg("Resources contain an element that is not an object Source</br>Save (file Feeds.php) is corrupted, manual correction required");
      return;
    }
  }
  usort($podcastArray, "compare_podcast_date");
  return $podcastArray;
}

function feed_class_podcast($item, $source) {
  if (isset($item->title)) {
    if (isset($item->pubDate)) {
      $date = new DateTime($item->pubDate);
      if ($date instanceof DateTime) {
        if (isset($item->description)) {
          if (preg_match("#durée : [0-9][0-9]:[0-9][0-9]:[0-9][0-9]#", $item->description)) {
            $description = substr($item->description, 20);
          }
          else {
            $description = $item->description;
          }
        }
        else if (isset($item->children()->{'itunes:summary'})) {
          $description = $item->children()->{'itunes:summary'};
        }
        if (isset($item->enclosure->attributes()['url'])) {
          if (preg_match("#durée : [0-9][0-9]:[0-9][0-9]:[0-9][0-9]#", $description[0])) {
            $duration = substr($item->description, 8, 8);
          }
          else if (isset($item->children()->{'itunes:duration'})) {
            if (preg_match("#[0-9][0-9]:[0-9][0-9]:[0-9][0-9]#", $item->children()->{'itunes:duration'}))
              $duration = $item->children()->{'itunes:duration'};
            else if (preg_match("#[0-9][0-9]:[0-9][0-9]#", $item->children()->{'itunes:duration'})){
              $duration = parse_duration_two_digits_less($item->children()->{'itunes:duration'});
            }
            else {
              $duration = parse_duration_seconds($item->children()->{'itunes:duration'});
            }
          }
          else {
            $error = "Description or Duration field does not correspond to what is expected, or duration format not found";
            return $error;
          }
          $podcast = new Podcast($item->title, $date, $description, $item->link, $item->enclosure->attributes()['url'], $duration, $source);
          return $podcast;
        }
        else $error = "Audio resource link not found in the provided object";
      }
      else $error = "Publication date did not fit the DateTime class requirements";
    }
    else $error = "Publication date not found in the provided object";
  }
  else $error = "Title not found in the provided object";

  return $error;
}

function display_row($resources, $start, $stop) {
  $data = unify_resources($resources, $start, $stop);
  if ($data == false) {
    display_welcome_message("Row");
    return false;
  }
  $i = 0;

  echo "<table id='row-table' cellspacing='0' cellpadding='0'>
      <tr>
          <th class='row-th'>
            Source
          </th>
          <th class='row-th'>
            Publication Date
          </th>
          <th class='row-th'>
            Title
          </th>
          <th class='row-th'>
            MP3 file
          </th>
          <th class='row-th'>
            Duration
          </th>
          <th class='row-th'>
            Download
          </th>
        </tr>";

  $week = date("W");
  $year = date("Y");
  $day = date("d");
  $month = date("F");
  $first = true;
  foreach ($data as $podcast) {
    if ($i >= $start && $i <= $stop) {

      if ($year > $podcast->date->format("Y")) {
        $year = $podcast->date->format("Y");
        $week = $podcast->date->format("W");
      }

      if ($month != $podcast->date->format("F")) {
        $month = $podcast->date->format("F");
      }

      if ($podcast->date->format("W") < $week || $first) {
        echo $podcast->to_string_table_row_week_number();
        $week = $podcast->date->format("W");
      }

      if ($day != $podcast->date->format("d") || $first) {
        echo $podcast->to_string_table_row_day();
        $day = $podcast->date->format("d");
        $first = false;
      }

      echo $podcast->to_string_table_row();
    }
    $i++;
  }
  echo "</table>";
}

function display_compact($resources, $start, $stop) {
  $data = unify_resources($resources, $start, $stop);
  if ($data == false) {
    display_welcome_message("Compact");
    return false;
  }
  $i = 0;

  $weeks = array();
  $week = new Week(last_day_of_the_week(new DateTime));
  foreach ($data as $podcast) {
    if ($i >= $start && $i <= $stop) {
      if (check_one_week_gap($week->lastDay, $podcast->date)) {
        $gap = check_gap($week->lastDay, $podcast->date);
        for ($j = 0; $j < $gap; $j += 7){
          array_push($weeks, $week);
          $week = new Week($week->lastDay->modify("-7 day"));
        }
      }
      $week->insert_event($podcast);
    }
    $i++;
  }
  array_push($weeks, $week);

  echo "<table id='compact-table' cellspacing='0' cellpadding='0'>
      <tr>
          <th class='compact-th'>
            Monday
          </th>
          <th class='compact-th'>
            Tuesday
          </th>
          <th class='compact-th'>
            Wednesday
          </th>
          <th class='compact-th'>
            Thursday
          </th>
          <th class='compact-th'>
            Friday
          </th>
          <th class='compact-th'>
            Saturday
          </th>
          <th class='compact-th'>
            Sunday
          </th>
        </tr>";

  foreach($weeks as $week) {
    echo $week->to_string();
  }
  echo "</table>";
}

function evaluate_feed($name, $src, $color, $twitter) {
  if ($name == "" && $src == "" && $color == "" && $twitter == "") return "Empty";
  $source = new Source($name, $src, $color, $twitter);
  foreach ($_SESSION["rss"] as $key => $value) {
    if ($source->is_same_name($value)) {
      modificate_feed($source, $key);
      return;
    }
    if ($source->is_same_feed($value)) {
      delete_feed($key);
      return;
    }
  }
  add_new_feed(new Source($name, $src, $color, $twitter));
}

function add_new_feed($source) {
  if ($source->is_correct()) {
    try {
      $testRSS = get_RSS($source->src);
    } catch (Exception $e) {
      display_err_msg("The inputed URL is incorrect</br></br>".$e);
      return;
    }
    array_push($_SESSION["rss"], $source);
    write_source_file($_SESSION["rss"]);
    display_message("\"".$source->name."\" has been added");
  }
  else display_message("The inputted data is incorrect</br>Nothing done");
}

function delete_feed($key) {
  $name = $_SESSION["rss"][$key]->name;
  unset($_SESSION["rss"][$key]);
  write_source_file($_SESSION['rss']);
  display_message("\"".$name."\" has been DELETED");
}

function modificate_feed($source, $key) {
  $string = "";
  if ($source->is_correct()) {
    $_SESSION["rss"][$key]->src = $source->src;
    $string = "URL &#8594 ".$_SESSION["rss"][$key]->src;
  }
  if ($source->has_color()) {
    $_SESSION["rss"][$key]->color = $source->color;
    if ($string != "") $string = $string."</br>& COLOR &#8594 ".$_SESSION['rss'][$key]->color;
    else $string = $string."COLOR &#8594 ".$_SESSION['rss'][$key]->color;
  }
  if ($source->has_twitter()) {
    $_SESSION["rss"][$key]->twitter = $source->twitter;
    if ($string != "") $string = $string."</br>& TWITTER LINK &#8594 ".$_SESSION["rss"][$key]->twitter;
    else $string = $string."TWITTER LINK &#8594 ".$_SESSION["rss"][$key]->twitter;
  }
  write_source_file($_SESSION["rss"]);
  if ($string == "") display_message("No modification done");
  else display_message($string."</br>of \"".$_SESSION["rss"][$key]->name."\" UPDATED");
}


function write_source_file($data) {
  try {
    file_put_contents($_SESSION["saveLocation"]."feeds.php", '<?php $_SESSION["rss"] = array(');
    $last = count($data);
    $current = 1;
    foreach($data as $source) {
      file_put_contents($_SESSION["saveLocation"]."feeds.php", "new Source(".$source->to_string().")", FILE_APPEND);
      if ($current != $last) file_put_contents($_SESSION["saveLocation"]."feeds.php", ",", FILE_APPEND);
      $current++;
    }
    file_put_contents($_SESSION["saveLocation"]."feeds.php", '); ?>', FILE_APPEND);
  } catch (Exception $e) {
    display_err_msg("Access to the destination missing</br></br>".$e);
  }
}

function get_twitter($url) {
  if($code = file_get_contents($url)) {
    $code = htmlentities($code);
    $found = preg_match('/twitter-timeline.*href=["&].*https:\/\/twitter.com\/.*["&]/', $code, $match, PREG_OFFSET_CAPTURE);
    if ($found > 0) {
      $found = preg_match('/https:\/\/twitter.com\/.*["&]/', $match[0][0], $match, PREG_OFFSET_CAPTURE);
      if ($found > 0){
        $i = 0;
        $string = "";
        while ($i < 40 && strcmp($match[0][0][$i], " ") != 0) {
          $string = $string."".$match[0][0][$i];
          $i++;
        }
        $string = substr($string, 0, -6);
        return $string;
      }
    }
  }
  return false;
}

function parse_duration_seconds($seconds) {
  $hours = 0;
  $minutes = 0;
  while ($seconds >= 60) {
    $seconds -= 60;
    $minutes++;
  }
  while ($minutes >= 60) {
    $minutes -= 60;
    $hours++;
  }
  if ($seconds < 10) {
    $seconds = "0".$seconds;
  }
  if ($minutes < 10) {
    $minutes = "0".$minutes;
  }
  if ($hours < 10) {
    $hours = "0".$hours;
  }
  return $hours.":".$minutes.":".$seconds;
}

function parse_duration_two_digits_less($duration) {
  return "00:".$duration;
}

function compare_podcast_date($a, $b) {
  if ($a->date > $b->date) {
    return 0;
  }
  else return 1;
}

/*
function move_in_the_table($prevDate, $date, $TDcounter) {
  if ($date->format("d F Y") != $prevDate->format("d F Y")) {
    $TDcounter++;
    echo "</td>";
    if ($TDcounter == 7) {
      echo "</tr><tr>";
      $TDcounter = 1;
    }
    $diff = $prevDate->format("U") - $date->format("U");
    $TDcounter = create_empty_cell(floor($diff / (24 * 3600)), $TDcounter);
    echo "<td>";
  }
  return $TDcounter;
}

function create_empty_cell($amount, $TDcounter) {
  for ($i = 0; $i < $amount; $i++) {
    $TDcounter++;
    if ($TDcounter == 7) {
      echo "</tr><tr>";
      $TDcounter = 0;
    }
    echo "<td></td>";
  }
  return $TDcounter;
}

function start_right_day($day) {
  $TDcounter = 0;
  if ($day == "Tue") {
    $TDcounter = create_empty_cell(1, 0);
  }
  if ($day == "Wed") {
    $TDcounter = create_empty_cell(2, 0);
  }
  if ($day == "Thu") {
    $TDcounter = create_empty_cell(3, 0);
  }
  if ($day == "Fri") {
    $TDcounter = create_empty_cell(4, 0);
  }
  if ($day == "Sat") {
    $TDcounter = create_empty_cell(5, 0);
  }
  if ($day == "Sun") {
    $TDcounter = create_empty_cell(6, 0);
  }
  return $TDcounter;
}
*/

function last_day_of_the_week($date) {
  $day = $date->format("D");
  if ($day == "Mon") {
    return $date->modify('+6 day');
  }
  if ($day == "Tue") {
    return $date->modify('+5 day');
  }
  if ($day == "Wed") {
    return $date->modify('+4 day');
  }
  if ($day == "Thu") {
    return $date->modify('+3 day');
  }
  if ($day == "Fri") {
    return $date->modify('+2 day');
  }
  if ($day == "Sat") {
    return $date->modify('+1 day');
  }
  if ($day == "Sun") {
    return $date;
  }
}

function check_one_week_gap($date1, $date2) {
  $diff = ($date1->format("U") - $date2->format("U"))/(24*3600);
  if ($diff >= 7 || $diff <= -7 || ($diff >= 6 && $date1->format("D") == $date2->format("D")) || ($diff <= -6 && $date1->format("D") == $date2->format("D"))) return true;
  else return false;
}

function check_gap($date1, $date2) {
  $diff = ($date1->format("U") - $date2->format("U"))/(24*3600);
  return floor($diff);
}

function display_feeds() {
  echo "<table id='herald-table' cellspacing='0' cellpadding='0' id='herald-table'>
          <tr>
            <th>
              Name
            </th>
            <th>
              URL
            </th>
            <th>
              Color
            </th>
            <th>
              Twitter
            </th>
          </tr>";
  foreach($_SESSION["rss"] as $source) {
    $string = "<tr>
                <td>
                  ".$source->name."
                </td>
                <td>
                  ".$source->src."
                </td>
                <td style='background-color:".$source->color."'>
                  ".$source->color."
                </td>
                <td>";
    if ($source->twitter == null || $source->twitter == "") {
      $string = $string."
                  <img src='".$_SESSION["resourcesLocation"]."twitter.png' alt='twitter icon' width='60px' class='no-twitter'>
                </td>";
    }
    else {
      $string = $string."
                  <a class='twitter' href='".$source->twitter."' target='_blank'>
                    <img src='".$_SESSION["resourcesLocation"]."twitter.png' alt='twitter icon' width='60px'>
                  </a>
                </td>";
    }

    $string = $string."</tr>";
    echo $string;
  }
  echo "</table>";
}

function display_welcome_message($side) {
  if ($_SESSION['start'] > $_SESSION['stop']) {
    $string = "</br>(which means you will not display anything)";
  }
  else {
    if ($_SESSION['start'] < 0) $string = $_SESSION['stop'] + 1;
    else $string = $_SESSION['stop'] - $_SESSION['start'] + 1;
    $string = "</br>(so you will display ".$string." podcasts)";
  }
  echo "<div class='welc-message'> <h1>Welcome to this Podcast Aggregator ! &#128515</h1>
  But as you can see there is nothing to display for now &#128543 </br>
  Feel free to input new sources thanks to the form in the bottom left hand corner</br>
  You can also directly enter an array of source (do not use this functionality if you do not know what you are doing)</br>
  <form action='' method='post'>
    <textarea id='welc-textarea' name='loadArray' placeholder='Your Sources separated by coma\nex: new Source(\"Name\", \"URL\", \"(Twitter link)\",  \"(Color)\"), [...]'></textarea></br>
    <input id='welc-textarea-button' type='submit' value='SEND' class='my_buttons'>
  </form>
  Here is a little tutorial of the functionning of the interface:
    <ul id='welc-list'>
      <li>Enter a brand new name and a new url to create a new feed</li>
      <li>If you do not pick a color, there will not have any. In order to keep a clear display you should pick one</li>
      <li>The Twitter field is optional</li>
      <li>If you enter an url that is already used, you will delete the corresponding feed</li>
      <li>If you enter a name that is already used, you will update the corresponding feed</li>
      <li>Modification has priority over deletion</li>
      <li>The arrow on the left brings the list of all the registered feeds</li>
      <li>The form on the top left hand corner allow you to limit your research</br>(from the left input to the right one, ex: 5-20 &#8594; the 16 podcasts from 5th to 20th)</li>
      <li>If you move your cursor on the right side you get two buttons:</li>
      <ul>
        <li>At the top (90% of the height) you can switch from row display mode to compact display mode</li>
        <li>At the bottom you have a button to go back to the top of the page</li>
      </ul>
    </ul>
    <h2>You can try the buttons and see the result below:</h2>
    Your are currenlty on the ".$side." Display ! </br>
    You chose to display podcasts from ".$_SESSION['start']." to ".$_SESSION['stop']."".$string."</br>
    &#x26A0;&#xFE0F; But you will not be able to see this message any longer as soon as you enter a correct source &#x26A0;&#xFE0F; </br></br>
    ---------------------------
    <h3>&#x26A0;&#xFE0F; Can only read RSS designed for podcasts &#x26A0;&#xFE0F; </br>
        &#x26A0;&#xFE0F; May not be able to read every RSS files (see displayed errors) &#x26A0;&#xFE0F;</br>
        &#x26A0;&#xFE0F; Some RSS files may not give a great display &#x26A0;&#xFE0F;</h3>
    </div>";
}

function display_message($text) {
  echo "<div class='message' onclick='hide_message()'>
          ".$text."
          </br>CLICK to close
        </div>";
}

function display_err_msg($text) {
  echo "<div class='error-msg' onclick='hide_err_msg()'>
            <div class='err-upper'>FATAL ERROR</div>
            <div class='err-mid'>".$text."</div>
            <div class='err-lower'>CLICK twice in a row to ignore</div>
            <div class='err-stop' onclick='stop_err_animation()'>STOP THIS ANIMATION</div>
          </div>";
}

?>
