<?php
$plugin['name'] = 'arc_redirect';

$plugin['version'] = '1.1';
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
function arc_redirect($event, $step)
{
	$url = $_SERVER['REQUEST_URI'];
	// Strip final slash from url
	$url = rtrim($url, '/');
	// Build the full URL including the protocol and domain
	$fullUrl = PROTOCOL . $_SERVER['SERVER_NAME'] . $url;
	
	$url = doSlash($url);
	$fullUrl = doSlash($fullUrl);
	
	$redirect = safe_row(
		'redirectUrl', 
		'arc_redirect', 
		"originalUrl = '$url' OR originalUrl = '$fullUrl' ORDER BY CHAR_LENGTH(originalUrl) DESC"
	);

	if (isset($redirect['redirectUrl']))
	{
		ob_end_clean();

		$status = 'HTTP/1.0 ';
		$status .= $redirect['statusCode']==301 ? '301 Moved Permanently' : '302 Moved Temporarily';

		header("Status: {$redirect['statusCode']}");
		header($status);
		header('Location: ' . $redirect['redirectUrl'], TRUE, $redirect['statusCode']);

		// In case the header() method fails, fall back on a classic redirect
		echo '<html><head><meta http-equiv="Refresh" content="0;URL='
			. $redirect['redirectUrl'] . '"></head><body></body></html>';
		die();
	}

	return;

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

function arc_redirect_list($message = '')
{
	global $event;
	
	extract(gpsa(array('page')));

	pagetop('arc_redirect',$message);
	
	$criteria = 1;
	
	$total = getCount('arc_redirect', $criteria);
	
	$limit = 25;
	list($page, $offset, $numPages) = pager($total, $limit, $page);
	
	$sort_sql = 'arc_redirectID desc';
		
	$rs = safe_rows_start('*', 'arc_redirect', "$criteria order by $sort_sql limit $offset, $limit");

	$statusCodes = array(
		301 => 'Permanent',
		302 => 'Temporary'
	);

	$html = '<h1 class="txp-heading">arc_redirect</h1>';
	// Include a quick add form
	$form = '<p><span class="edit-label"><label for="originalUrl">Redirect from URL (produces 404 page)</label></span>';
	$form .= '<span class="edit-value">' . fInput('text', 'originalUrl', '', '', '', '', '', '', 'originalUrl') . '</span></p>';
	$form .= '<p><span class="edit-label"><label for="redirectUrl">Redirect to URL</label></span>';
	$form .= '<span class="edit-value">' . fInput('text', 'redirectUrl', '', '', '', '', '', '', 'redirectUrl') . '</span></p>';
	$form .= '<p><span class="edit-label"><label for="statusCode">Redirect Type</label></span>';
	$form .= '<span class="edit-value"><select name="statusCode">' . type_options($statusCodes) . '</select>&nbsp;' . fInput('submit', 'add', gTxt('Add')) . '</span></p>';

	$form .= eInput('arc_redirect').sInput('add');
	$html .= form('<div class="plugin-column">' . $form . '</div>', '', '', 'post', 'edit-form');
	
	// Add a list of existing redirects
	$html .= n . n . '<form action="index.php" id="arc_redirect_form" class="multi_edit_form" method="post" name="longform">';
	$html .= startTable(null, null, 'txp-list');
	
	$html .= '<thead>' . tr(
		hCell(
			fInput('checkbox', 'select_all', 0, '', '', '', '', '', 'select_all'), 
			'',
			' title="Toggle all/none selected" class="multi-edit"'
		)
		. hCell('ID#')
		. hCell('Original URL')
		. hCell('Redirect URL')
		. hCell('Type')
		. hCell('Manage')
	) . '</thead>';
	
	while ($redirect = nextRow($rs))
	{
		$editLink = href(
			gTxt('edit'),
			'?event=arc_redirect&amp;step=edit&amp;id=' . $redirect['arc_redirectID']
		);
		$redirectLink = href('Test', $redirect['originalUrl']);
		$html .= tr(
			td(fInput('checkbox', 'selected[]', $redirect['arc_redirectID']), '', 'multi-edit')
			. td($redirect['arc_redirectID'], 20, 'id')
			. td(htmlspecialchars($redirect['originalUrl']), 175)
			. td(htmlspecialchars($redirect['redirectUrl']), 175)
			. td($redirect['statusCode']==301 ? 'Permanent' : 'Temporary', 175)
			. td("$editLink <span> | </span> $redirectLink", 35, 'manage')
		);
	}
	
	$html .= endTable();

	$methods = array(
		'delete' => gTxt('delete')
	);

	$html .= multi_edit($methods, 'arc_redirect', 'arc_redirect_multiedit');

	$html .= '</form>';
	
	$html .= n . '<div id="'.$event.'_navigation" class="txp-navigation">'
		. n . nav_form('arc_redirect', $page, $numPages, '', '', '', '', $total, $limit)
		. n . '</div>';
	
	echo $html;

	return;
	
}

function arc_redirect_edit($message='')
{
	pagetop('arc_redirect',$message);
	
	$originalUrl = gps('originalUrl');
	$redirectUrl = gps('redirectUrl');
	$statusCode = gps('statusCode');
	
	if ($id=gps('id')) {
		$id = doSlash($id);
		$rs = safe_row('originalUrl,redirectUrl,statusCode', 'arc_redirect', "arc_redirectID = $id");
		extract($rs);
	}

	$statusCodes = array(
		301 => 'Permanent',
		302 => 'Temporary'
	);

	$html = '<h1 class="txp-heading">arc_redirect</h1>';
	$form = '<h2>' . ($id ? 'Edit' : 'Add') . ' Redirect</h2>';
	$fields = array(
		'originalUrl' => 'Redirect from URL',
		'redirectUrl' => 'Redirect to URL'
	);
	foreach ($fields as $key => $label) 
	{
		$form .= '<p class="' . $key . '"><span class="edit-label"><label for="$key">' . $label . '</label></span>';
		$form .= '<span class="edit-value">' . fInput('text', $key, $$key, '', '', '', '', '', $key) . '</span>';
		$form .= '</p>';
	}
	$form .= '<p class="statusCode"><span class="edit-label"><label for="statusCode">Redirect Type</label></span>';
	$form .= selectInput('statusCode', $statusCodes, $statusCode);
	$form .= '</p>';
	$form .= eInput('arc_redirect');
	$form .= '<p>';
	if ($id) {
		$form .= sInput('save').hInput('id',$id).fInput('submit','save',gTxt('Save'),'publish');
	} else {
		$form .= sInput('add').fInput('submit','add',gTxt('Add'),'publish');
	}
	$form .= '</p>';
	$html .= form('<div class="plugin-column"><div class="txp-edit">' . $form . '</div></div>', '', '', 'post', 'edit-form');
	
	echo $html;
	
}

function arc_redirect_add()
{
	$originalUrl = ps('originalUrl');
	$redirectUrl = ps('redirectUrl');
	
	if ($originalUrl === '' || $redirectUrl === '')
	{
		arc_redirect_edit('Unable to add new redirect');
		return;
	}

	$statusCode = ps('statusCode') == 301 ? 301 : 302;
	
	// Strip final slash from original url
	$originalUrl = rtrim($originalUrl, '/');
	
	$q = safe_insert(
		"arc_redirect",
		"originalUrl = '" . trim(doSlash($originalUrl)) . "', redirectUrl = '" . trim(doSlash($redirectUrl)) . "', statusCode = " . $statusCode
	);
	
	if ($q)
	{
		$message = gTxt('Redirect added');
		arc_redirect_list($message);
	}

	return;
}

function arc_redirect_save()
{
	if (!$id=ps('id'))
	{
		arc_redirect_list('Unable to save redirect');
		return;
	}
	
	$originalUrl = ps('originalUrl');
	$redirectUrl = ps('redirectUrl');
	$statusCode = ps('statusCode');
	
	if ($originalUrl == '' || $redirectUrl == '' || empty($statusCode))
	{
		arc_redirect_edit('Unable to save redirect');
		return;
	}
		
	// Strip final slash from original url
	$originalUrl = rtrim($originalUrl, '/');
	
	$id = doSlash($id);
	
	$rs = safe_update(
		"arc_redirect",
		"originalUrl    = '" . trim(doSlash($originalUrl)) . "',  redirectUrl = '" . trim(doSlash($redirectUrl)) . "',  statusCode = " . trim(doSlash($statusCode)) . "",
		"arc_redirectID = $id"
	);
	
	if ($rs)
	{
		$message = gTxt('Redirect updated');
		arc_redirect_list($message);
	}
	return;
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

	// For first install, create table for redirects
	$sql = 'CREATE TABLE IF NOT EXISTS '.PFX.'arc_redirect ';
	$sql .= '(arc_redirectID INTEGER AUTO_INCREMENT PRIMARY KEY,
		originalUrl VARCHAR(255),
		redirectUrl VARCHAR(255));';

	if (!safe_query($sql))
	{
		return 'Error - unable to create arc_redirect table';
	}

	if (!in_array('statusCode', getThings('DESCRIBE ' . safe_pfx('arc_redirect'))))
	{
		safe_alter('arc_redirect', 'ADD statusCode INT NOT NULL DEFAULT \'301\'');
	}

	return;

}

// Uninstall function - deletes MySQL table and related preferences

function arc_redirect_uninstall()
{
	$sql = "DROP TABLE IF EXISTS ".PFX."arc_redirect;";
	if (!safe_query($sql))
	{
		return 'Error - unable to delete arc_redirect table';
	}
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---

h1. arc_redirect (love redirects, hate 404s)

If you're in the process of restructuring a Textpattern site, then this is the plugin for you!

Requirements:-

* Textpattern 4.5+

h2. Installation

To install go to 'Plugins' under 'Admin' and paste the plugin code into the 'Install plugin' box, 'upload' and then 'install'. You will then need to activate the plugin.


h2. Uninstall

To uninstall %(tag)arc_redirect% simply delete the plugin from the 'Plugins' tab.  This will remove the plugin code and drop the %(tag)arc_redirect% table from your Textpattern database.


h2. Usage

arc_redirect adds a new tab under 'Extensions' from where you can define pairs of URLs for handling redirects. Basically provide an original URL on your Textpattern site that is generating a 404, "page not found", error and a redirect URL. Then whenever someone goes to the original URL rather than get the standard 404 error page they will be redircted to the new URL (with a 301 permenantely moved, or 302 temporarily removed).

The redirect from URL must produce a 404 page in Textpattern on the site this plugin is installed.

* arc_redirect treats _http://www.example.com/missing_ the same as _http://www.example.com/missing/_
* arc_redirect does not treat _http://example.com/missing_ and _http://www.example.com/missing_ as the same URL
* You can use absolute URLs like _/missing_


h2. Author

"Andy Carter":http://andy-carter.com. For other Textpattern plugins by me visit my "Plugins page":http://andy-carter.com/txp.

Thanks to "Oliver Ker":http://oliverker.co.uk/ for giving me the idea for this plugin.

h2. License

The MIT License (MIT)

Copyright (c) 2014 Andy Carter

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

# --- END PLUGIN HELP ---
-->
<?php
}
?>
