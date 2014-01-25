<?php
$plugin['name'] = 'arc_redirect';

$plugin['version'] = '1.01beta';
$plugin['author'] = 'Andy Carter';
$plugin['author_uri'] = 'http://andy-carter.com/';
$plugin['description'] = 'Love redirects, hate 404s';
$plugin['order'] = '5';
$plugin['type'] = '1';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '2';

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
register_callback('arc_redirect_install','plugin_lifecycle.arc_redirect', 'installed');
register_callback('arc_redirect_uninstall','plugin_lifecycle.arc_redirect', 'deleted');
register_callback('arc_redirect', 'txp_die', 404);
add_privs('arc_redirect', '1,2,3,4');
register_tab('extensions', 'arc_redirect', 'arc_redirect');
register_callback('arc_redirect_tab', 'arc_redirect');

/*
 * Check for redirected URLs and forward with a 301 where necessary.
 */
function arc_redirect($event, $step) {
  $url = PROTOCOL.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
  
  // Strip final slash from url
  $url = rtrim($url, '/');
  
  $url = doSlash($url);
  
  $redirect = safe_row('redirectUrl', 'arc_redirect', "originalUrl = '$url'");

	  if (isset($redirect['redirectUrl'])) {
		  ob_end_clean();

		  header("Status: 301");
		  header("HTTP/1.0 301 Moved Permanently");
		  header("Location: ".$redirect['redirectUrl'], TRUE, 301);

		  // In case the header() method fails, fall back on a classic redirect
		  echo '<html><head><META HTTP-EQUIV="Refresh" CONTENT="0;URL='
		    . $redirect['redirectUrl'] . '"></head><body></body></html>';
		  die();
	  }

}

function arc_redirect_tab($event, $step) {
  switch ($step) {
    case 'add': arc_redirect_add(); break;
    case 'save': arc_redirect_save(); break;
    case 'edit': arc_redirect_edit(); break;
    case 'arc_redirect_multiedit': arc_redirect_multiedit(); break;
    default: arc_redirect_list();
  }
}

function arc_redirect_list($message = '') {

  global $event;
  
  extract(gpsa(array('page')));

  pagetop('arc_redirect',$message);
  
  $criteria = 1;
  
  $total = getCount('arc_redirect', $criteria);
  
  $limit = 25;
  list($page, $offset, $numPages) = pager($total, $limit, $page);
  
  $sort_sql = 'arc_redirectID desc';
    
  $rs = safe_rows_start('*', 'arc_redirect', "$criteria order by $sort_sql limit $offset, $limit");

  $html = "<h1 class='txp-heading'>arc_redirect</h1>";
  // Include a quick add form
  $form = "<p><span class='edit-label'><label for='originalUrl'>Redirect from URL (produces 404 page)</label></span>";
  $form .= "<span class='edit-value'>" . fInput('text', 'originalUrl', '', '', '', '', '', '', 'originalUrl') . "</span></p>";
  $form .= "<p><span class='edit-label'><label for='redirectUrl'>Redirect to URL</label></span>";
  $form .= "<span class='edit-value'>" . fInput('text', 'redirectUrl', '', '', '', '', '', '', 'redirectUrl') . '&nbsp;' . fInput('submit', 'add', gTxt('Add')) . "</span></p>";

  $form .= eInput('arc_redirect').sInput('add');
  $html .= form("<div class='plugin-column'>" . $form . "</div>", '', '', '', 'edit-form');
  
  // Add a list of existing redirects
  $html .= n.n.'<form action="index.php" id="arc_redirect_form" class="multi_edit_form" method="post" name="longform">';
  $html .= startTable(null, null, 'txp-list');
  
  $html .= '<thead>' . tr(
    hCell(fInput('checkbox', 'select_all', 0, '', '', '', '', '', 'select_all'), '', ' title="Toggle all/none selected" class="multi-edit"')
    .hCell('ID#')
    .hCell('Original URL')
    .hCell('Redirect URL')
    .hCell('Manage')
  ) . '</thead>';
  
  while ($redirect = nextRow($rs)) {
    $editLink = href(gTxt('edit'),
      '?event=arc_redirect&amp;step=edit&amp;id='.$redirect['arc_redirectID']);
    $redirectLink = href('Test',$redirect['originalUrl']);
    $html .= tr(
      td(fInput('checkbox', 'selected[]', $redirect['arc_redirectID']), '', 'multi-edit')
      .td($redirect['arc_redirectID'], 20, 'id')
      .td($redirect['originalUrl'], 175)
      .td($redirect['redirectUrl'], 175)
      .td("$editLink <span> | </span> $redirectLink", 35, 'manage')
    );
  }
  
  $html .= endTable();

  $methods = array(
    'delete' => gTxt('delete')
  );

  $html .= multi_edit($methods, 'arc_redirect', 'arc_redirect_multiedit');

  $html .= '</form>';
  
  $html .= n.'<div id="'.$event.'_navigation" class="txp-navigation">'
    .n.nav_form('arc_redirect', $page, $numPages, '', '', '', '', $total, $limit)
    .n.'</div>';
  
  echo $html;
  
}

function arc_redirect_edit($message='') {
  pagetop('arc_redirect',$message);
  
  $originalUrl = gps('originalUrl');
  $redirectUrl = gps('redirectUrl');
  
  if ($id=gps('id')) {
    $id = doSlash($id);
    $rs = safe_row('originalUrl,redirectUrl', 'arc_redirect', "arc_redirectID = $id");
    extract($rs);
  }

  $html = "<h1 class='txp-heading'>arc_redirect</h1>";
  $form = '<h2>' . ($id ? 'Edit' : 'Add') . ' Redirect</h2>';
  $fields = array(
    'originalUrl' => 'Redirect from URL',
    'redirectUrl' => 'Redirect to URL'
  );
  foreach ($fields as $key => $label) {
    $form .= "<p class='$key'><span class='edit-label'><label for='$key'>$label</label></span>";
    $form .= "<span class='edit-value'>" . fInput('text', $key, $$key, '', '', '', '', '', $key) . "</span>";
    $form .= '</p>';
  }
  $form .= eInput('arc_redirect');
  $form .= '<p>';
  if ($id) {
    $form .= sInput('save').hInput('id',$id).fInput('submit','save',gTxt('Save'),'publish');
  } else {
    $form .= sInput('add').fInput('submit','add',gTxt('Add'),'publish');
  }
  $form .= '</p>';
  $html .= form('<div class="plugin-column"><div class="txp-edit">' . $form . '</div></div>', '', '', '', 'edit-form');
  
  echo $html;
  
}

function arc_redirect_add() {
  $originalUrl = gps('originalUrl');
  $redirectUrl = gps('redirectUrl');
  
  if ($originalUrl === '' || $redirectUrl === '') {
    arc_redirect_edit('Unable to add new redirect');
    return;
  }
  
  // Strip final slash from original url
  $originalUrl = rtrim($originalUrl, '/');
  
  $q = safe_insert("arc_redirect",
    "originalUrl = '".trim(doSlash($originalUrl))."', redirectUrl = '".trim(doSlash($redirectUrl))."'"
  );
  
  $GLOBALS['ID'] = mysql_insert_id();
  
  if ($q) {
    $message = gTxt('Redirect added');
    arc_redirect_list($message);
  }
}

function arc_redirect_save() {

  if (!$id=gps('id')) {
    arc_redirect_list('Unable to save redirect');
    return;
  }
  
  $originalUrl = gps('originalUrl');
  $redirectUrl = gps('redirectUrl');
  
  if ($originalUrl == '' || $redirectUrl == '') {
    arc_redirect_edit('Unable to save redirect');
    return;
  }
    
  // Strip final slash from original url
  $originalUrl = rtrim($originalUrl, '/');
  
  $id = doSlash($id);
  
  $rs = safe_update("arc_redirect",
    "originalUrl    = '".trim(doSlash($originalUrl))."',  redirectUrl = '".trim(doSlash($redirectUrl))."'",
    "arc_redirectID = $id"
  );
  
  if ($rs) {
    $message = gTxt('Redirect updated');
    arc_redirect_list($message);
  }
}

function arc_redirect_multiedit() {
  $selected = ps('selected');
  
  if (!$selected || !is_array($selected)) {
    arc_redirect_list();
    return;
  }
  
  $method = ps('edit_method');
  $changed = array();
  
  $message = '';
  
  switch ($method) {
    case 'delete':
      
      foreach ($selected as $id) {
        $id = doSlash($id);
        if (safe_delete('arc_redirect', 'arc_redirectID = '.$id)) {
          $changed[] = $id;
        }
      }
      $message = count($changed).' redirects deleted';
      break;
  }
  
  arc_redirect_list($message);
}

// Installation function - builds MySQL table
function arc_redirect_install()
{

  // For first install, create table for tweets
  $sql = "CREATE TABLE IF NOT EXISTS ".PFX."arc_redirect ";
  $sql.= "(arc_redirectID INTEGER AUTO_INCREMENT PRIMARY KEY,
    originalUrl VARCHAR(255),
    redirectUrl VARCHAR(255));";

  if (!safe_query($sql)) {
    return 'Error - unable to create arc_redirect table';
  }

}

// Uninstall function - deletes MySQL table and related preferences

function arc_redirect_uninstall()
{
  $sql = "DROP TABLE IF EXISTS ".PFX."arc_redirect;";
  if (!safe_query($sql)) {
    return 'Error - unable to delete arc_redirect table';
  }
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---

h1(title). arc_redirect (love redirects, hate 404s)

If you're in the process of restructuring a Textpattern site, then this is the plugin for you!

Requirements:-

* Textpattern 4.5+


h2(section). Author

"Andy Carter":http://andy-carter.com. For other Textpattern plugins by me visit my "Plugins page":http://andy-carter.com/txp.

Thanks to "Oliver Ker":http://oliverker.co.uk/ for giving me the idea for this plugin.


h2(section). Installation / Uninstallation

To install go to the 'plugins' tab under 'admin' and paste the plugin code into the 'Install plugin' box, 'upload' and then 'install'. Finally activate the plugin.

To uninstall %(tag)arc_redirect% simply delete the plugin from the 'Plugins' tab.  This will remove the plugin code and drop the %(tag)arc_redirect% table from your Textpattern database.

h2(section). Usage

%(tag)arc_redirect% adds a new tab under 'Extensions' from where you can define pairs of URLs for handling redirects. Basically provide an original URL on your Textpattern site that is generating a 404, "page not found", error and a redirect URL. Then whenever someone goes to the original URL rather than get the standard 404 error page they will be redircted to the new URL (with a 301 permenantely moved).

The redirect from URL must produce a 404 page in Textpattern on the site this plugin is installed.

* %(tag)arc_redirect% treats http://www.example.com/missing the same as http://www.example.com/missing/
* %(tag)arc_redirect% does not treat http://example.com/missing and http://www.example.com/missing as the same URL


# --- END PLUGIN HELP ---
-->
<?php
}
?>
