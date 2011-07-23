<?php
$plugin['name'] = 'arc_redirect';

$plugin['version'] = '0.1-dev';
$plugin['author'] = 'Andy Carter';
$plugin['author_uri'] = 'http://redhotchilliproject.com/';
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
        @include_once('../zem_tpl.php');

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
  
  $sql = "SELECT redirectUrl FROM ".PFX."arc_redirect WHERE originalUrl = '".$url."';";
  $rs = safe_query($sql); $redirect = nextRow($rs);

	  if ($redirect['redirectUrl']) {
		  ob_end_clean();

		  header("Status: 301");
		  header("HTTP/1.0 301 Moved Permanently");
		  header("Location: ".$redirect['redirectUrl'], TRUE, 301);

		  // In case the header() method fails, fall back on a classic redirect
		  echo '<html><head><META HTTP-EQUIV="Refresh" CONTENT="0;URL='
		    .$permlink.'"></head><body></body></html>';
		  die();
	  }

}

function arc_redirect_tab($event, $step) {
  switch ($step) {
    case 'add': arc_redirect_add(); break;
    case 'save': arc_redirect_save(); break;
    case 'edit': arc_redirect_edit(); break;
    default: arc_redirect_list();
  }
}

function arc_redirect_list($message = '') {

  pagetop('arc_redirect',$message);

  $sql = "SELECT * FROM ".PFX."arc_redirect;";
  $rs = safe_query($sql);  
  
  // Include a quick add form
  $html = form(
    startTable('edit')
      .tr(
        tda(tag('Original URL','label', ' for="originalUrl"'),' style="vertical-align:middle"')
        .td(fInput('text','originalUrl','','edit','','','','','originalUrl'))
      ).tr(
        tda(tag('Redirect URL','label', ' for="redirectUrl"'),' style="vertical-align:middle"')
        .td(fInput('text','redirectUrl','','edit','','','','','redirectUrl')
        .eInput('arc_redirect')
        .sInput('add').'&nbsp;'.fInput('submit','add',gTxt('Add'),'publish')
        )
      )  
    .endTable()
  , 'margin-bottom:25px');
  
  // Add a list of existing redirects
  $html .= startTable('list');
  
  $html .= tr(
    hCell ('ID#')
    .hCell ()
    .hCell ('Original URL')
    .hCell ('Redirect URL')
  );
  
  while ($redirect = nextRow($rs)) {
    $editLink = href(gTxt('edit'),
      '?event=arc_redirect&amp;step=edit&amp;id='.$redirect['arc_redirectID']);
    $redirectLink = href('Test',$redirect['originalUrl']);
    $html .= tr(
      td($redirect['arc_redirectID'], 20, 'id')
      .td("<ul><li class='action-edit'>$editLink</li><li class='action-view'>$redirectLink</li></ul>", 35, 'actions')
      .td($redirect['originalUrl'], 175)
      .td($redirect['redirectUrl'], 175)
    );
  }
  
  $html .= endTable();
  
  echo $html;
  
}

function arc_redirect_edit($message='') {
  pagetop('arc_redirect',$message);
  
  $originalUrl = gps('originalUrl');
  $redirectUrl = gps('redirectUrl');
  
  if ($id=gps('id')) {
    $rs = safe_row('originalUrl,redirectUrl', 'arc_redirect', "arc_redirectID = $id");
    extract($rs);
  }
  
  echo startTable('edit').tr(td(
  
    form(
      tag(tag('Original URL','label', ' for="originalUrl"')
      .fInput('text','originalUrl',$originalUrl,'edit','','','','','originalUrl'),'p')
      .tag(tag('Redirect URL','label', ' for="redirectUrl"')
      .fInput('text','redirectUrl',$redirectUrl,'edit','','','','','redirectUrl'),'p')
      .eInput('arc_redirect')
      .(($id)?
        sInput('save').hInput('id',$id).fInput('submit','save',gTxt('Save'),'publish')
        :sInput('add').fInput('submit','add',gTxt('Add'),'publish')
      ).br
    )
  )).endTable();
  
}

function arc_redirect_add() {
  $originalUrl = gps('originalUrl');
  $redirectUrl = gps('redirectUrl');
  
  if ($originalUrl === '' || $redirectUrl === '') {
    arc_redirect_edit('Unable to add new redirect');
    return;
  }
  
  $q = safe_insert("arc_redirect",
    "originalUrl = '".trim($originalUrl)."', redirectUrl = '".trim($redirectUrl)."'"
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
  
  $rs = safe_update("arc_redirect",
    "originalUrl    = '".trim($originalUrl)."',  redirectUrl = '".trim($redirectUrl)."'",
    "arc_redirectID = $id"
  );
  
  if ($rs) {
    $message = gTxt('Redirect updated');
    arc_redirect_list($message);
  }
}

// Installation function - builds MySQL table
function arc_redirect_install()
{

  // For first install, create table for tweets
  $sql = "CREATE TABLE IF NOT EXISTS ".PFX."arc_redirect ";
  $sql.= "(arc_redirectID INTEGER AUTO_INCREMENT PRIMARY KEY,
    originalUrl VARCHAR(240),
    redirectUrl VARCHAR(240));";

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

# --- END PLUGIN HELP ---
-->
<?php
}
?>
