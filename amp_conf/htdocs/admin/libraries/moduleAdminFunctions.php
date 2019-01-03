<?php

//These were moved from page.modules.php Sorry for merge conflicts...

//-------------------------------------------------------------------------------------------
// Help functions
//

/** preps a string to use as an HTML id element
 */
function prep_id($name) {
	return preg_replace("/[^a-z0-9-]/i", "_", $name);
}

/** Progress callback used by module_download()
 */
function download_progress($action, $params) {
	switch ($action) {
	case 'untar':
		echo '<script type="text/javascript">' .
			'$("#installstatus_'.$params['module'].'").append("'._('Untarring..').'");'.
			'</script>';
		@ ob_flush();
		flush();
		break;
	case 'downloading':
		if ($params['total']==0) {
			$progress = $params['read'].' of '.$params['total'].' (0%)';
		} else {
			$progress = $params['read'].' of '.$params['total'].' ('.round($params['read']/$params['total']*100).'%)';
		}
		echo '<script type="text/javascript">'.
			'$("#downloadprogress_'.$params['module'].'").html("'.$progress.'");'.
			'</script>';
		@ ob_flush();
		flush();
		break;
		case 'done';
		echo '<script type="text/javascript">'.
			'$("#installstatus_'.$params['module'].'").append("'._('Done').'<br/>");'.
			'</script>';
		@ ob_flush();
		flush();
		break;
	}
}

function format_changelog($changelog) {
	$changelog = nl2br($changelog);
	$changelog = preg_replace('/(\d+(\.\d+|\.\d+beta\d+|\.\d+alpha\d+|\.\d+rc\d+|\.\d+RC\d+)+):/', '<strong>$0</strong>', $changelog);
	$changelog = preg_replace('/\*(\d+(\.\d+|\.\d+beta\d+|\.\d+alpha\d+|\.\d+rc\d+|\.\d+RC\d+)+)\*/', '<strong>$1:</strong>', $changelog);
	$changelog = preg_replace('/(\d+(\.\d+|\.\d+beta\d+|\.\d+alpha\d+|\.\d+rc\d+|\.\d+RC\d+)+) /', '<strong>$1: </strong>', $changelog);

	$changelog = format_ticket($changelog);

	$changelog = preg_replace_callback('/(?<!\w)r(\d+)(?!\w)/', 'trac_replace_changeset', $changelog);
	$changelog = preg_replace_callback('/(?<!\w)\[(\d+)\](?!\w)/', 'trac_replace_changeset', $changelog);

	return $changelog;
}

function format_ticket($string) {
	// convert '#xxx', 'ticket xxx', 'bug xxx' to ticket links and rxxx to changeset links in trac
	$string = preg_replace_callback('/(?<!\w)(?:#|bug |ticket )([^&]\d{3,5})(?!\w)/i', 'trac_replace_ticket', $string);

	// Convert FREEPBX|FPBXDISTRO(-| )6745 for jira
	$string = preg_replace_callback('/(FREEPBX|FPBXDISTRO)(?:\-| )([^&]\d{3,5})(?!\w)/', 'jira_replace_ticket', $string);

	return $string;
}

/* enable_option($module_name, $option)
	This function will return false if the particular option, which is a module xml tag,
	is set to 'no'. It also provides for some hardcoded overrides on critical modules to
	keep people from editing the xml themselves and then breaking their the system.
 */
function enable_option($module_name, $option) {
	global $modules;

	$enable=true;
	$override = array(
		'core'	=> array(
			'candisable' => 'no',
			'canuninstall' => 'no',
		),
		'framework' => array(
			'candisable' => 'no',
			'canuninstall' => 'no',
		),
	);
	if (isset($modules[$module_name][$option]) && strtolower(trim($modules[$module_name][$option])) == 'no') {
		$enable=false;
	}
	if (isset($override[$module_name][$option]) && strtolower(trim($override[$module_name][$option])) == 'no') {
		$enable=false;
	}
	return $enable;
}

/**
 *  Replace '#nnn', 'bug nnn', 'ticket nnn' type ticket numbers in changelog with a link, taken from Greg's drupal filter
 */
function trac_replace_ticket($match) {
	$baseurl = 'http://freepbx.org/trac/ticket/';
	return '<a target="tractickets" href="'.$baseurl.$match[1].'" title="ticket '.$match[1].'">'.$match[0].'</a>';
}

/**
 *  Replace 'rnnn' changeset references to a link, taken from Greg's drupal filter
 */
function trac_replace_changeset($match) {
	// We continue to use trac here eventhough we are using jira for backwards compatibility
	// and to let jira know its an old reference
	$baseurl = 'http://freepbx.org/trac/changeset/';
	return '<a target="tractickets" href="'.$baseurl.$match[1].'" title="changeset '.$match[1].'">'.$match[0].'</a>';
}

/**
 *  Replace 'FREEPBX-nnn', 'FPBXDISTRO-nnn' type ticket numbers in changelog with a link
 */
function jira_replace_ticket($match) {
	$baseurl = 'http://issues.freepbx.org/browse/'.$match[1].'-';
	return '<a target="tractickets" href="'.$baseurl.$match[2].'" title="ticket '.$match[2].'">#'.$match[2].'</a>';
}

function pageReload(){
	return "";
}

function displayRepoSelect($buttons,$online=false,$repo_list=array()) {
	global $display, $online, $tabindex;
    $FreePBX = FreePBX::Create();
	$modulef = module_functions::create();
	$displayvars = array("display" => $display, "online" => $online, "tabindex" => $tabindex, "repo_list" => $repo_list, "active_repos" => $modulef->get_active_repos());
	$button_display = '';
	$href = "config.php?display=$display";
	$button_template = '<input type="button" value="%s" onclick="location.href=\'%s\';" />'."\n";

	$displayvars['button_display'] = '';
	foreach ($buttons as $button) {
		switch($button) {
		case 'local':
			$displayvars['button_display'] .= sprintf($button_template, _("Manage local modules"), $href);
			break;
		case 'upload':
			$displayvars['button_display'] .= sprintf($button_template, _("Upload modules"), $href.'&action=upload');
			break;
		}
	}

	$brand = $FreePBX->Config->get("DASHBOARD_FREEPBX_BRAND");

	$displayvars['tooltip']  = _("Choose the repositories that you want to check for new modules. Any updates available for modules you have on your system will be detected even if the repository is not checked. If you are installing a new system, you may want to start with the Basic repository and update all modules, then go back and review the others.").' ';
	$displayvars['tooltip'] .= sprintf(_(" The modules in the Extended repository are less common and may receive lower levels of support. The Unsupported repository has modules that are not supported by the %s team but may receive some level of support by the authors."),$brand).' ';
	$displayvars['tooltip'] .= _("The Commercial repository is reserved for modules that are available for purchase and commercially supported.").' ';
	$displayvars['tooltip'] .= '<br /><br /><small><i>('.sprintf(_("Checking for updates will transmit your %s, Distro, Asterisk and PHP version numbers along with a unique but random identifier. This is used to provide proper update information and track version usage to focus development and maintenance efforts. No private information is transmitted."),$brand).')</i></small>';

	return load_view(__DIR__.'views/module_admin/reposelect.php',$displayvars);
}
