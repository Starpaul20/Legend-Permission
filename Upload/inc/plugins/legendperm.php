<?php
/**
 * Legend Permission
 * Copyright 2014 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(defined('THIS_SCRIPT'))
{
	if(THIS_SCRIPT == 'editpost.php')
	{
		global $templatelist;
		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		$templatelist .= 'editpost_editedby';
	}
}

// Tell MyBB when to run the hooks
$plugins->add_hook("datahandler_post_update", "legendperm_run");
$plugins->add_hook("postbit", "legendperm_postbit");
$plugins->add_hook("editpost_start", "legendperm_edit_page");

$plugins->add_hook("admin_formcontainer_output_row", "legendperm_usergroup_permission");
$plugins->add_hook("admin_user_groups_edit_commit", "legendperm_usergroup_permission_commit");

// The information that shows up on the plugin manager
function legendperm_info()
{
	global $lang;
	$lang->load("legendperm", true);

	return array(
		"name"				=> $lang->legendperm_info_name,
		"description"		=> $lang->legendperm_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.3",
		"codename"			=> "legendperm",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is installed.
function legendperm_install()
{
	global $db, $cache;
	legendperm_uninstall();

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("usergroups", "canremoveeditedby", "smallint NOT NULL default '0'");

			$db->add_column("posts", "disableeditedby", "smallint NOT NULL default '0'");
			break;
		default:
			$db->add_column("usergroups", "canremoveeditedby", "tinyint(1) NOT NULL default '0'");

			$db->add_column("posts", "disableeditedby", "tinyint(1) NOT NULL default '0'");
			break;
	}

	$cache->update_usergroups();
}

// Checks to make sure plugin is installed
function legendperm_is_installed()
{
	global $db;
	if($db->field_exists("canremoveeditedby", "usergroups"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function legendperm_uninstall()
{
	global $db, $cache;

	if($db->field_exists("canremoveeditedby", "usergroups"))
	{
		$db->drop_column("usergroups", "canremoveeditedby");
	}

	if($db->field_exists("disableeditedby", "posts"))
	{
		$db->drop_column("posts", "disableeditedby");
	}

	$cache->update_usergroups();
}

// This function runs when the plugin is activated.
function legendperm_activate()
{
	global $db;

	$insert_array = array(
		'title'		=> 'editpost_editedby',
		'template'	=> $db->escape_string('<td class="trow2"><strong>{$lang->edited_by}</strong></td>
<td class="trow2"><span class="smalltext">
<label><input type="checkbox" class="checkbox" name="disableeditedby" value="1" tabindex="7" {$disableeditedby} /> {$lang->disable_edited_by}</label></span>
</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("editpost", "#".preg_quote('{$pollbox}')."#i", '{$pollbox}{$editedby}');
}

// This function runs when the plugin is deactivated.
function legendperm_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('editpost_editedby')");

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("editpost", "#".preg_quote('{$editedby}')."#i", '', 0);
}

// Update 'Edited by' input from edit page
function legendperm_run()
{
	global $db, $mybb, $post;
	$edit = get_post($post['pid']);

	$editedby = array(
		"disableeditedby" => $mybb->get_input('disableeditedby', MyBB::INPUT_INT)
	);
	$db->update_query("posts", $editedby, "pid='{$edit['pid']}'");
}

// Remove edited by on posts if option says so
function legendperm_postbit($post)
{
	if($post['disableeditedby'] == 1)
	{
		$post['editedmsg'] = '';
	}

	return $post;
}

// Edit page options
function legendperm_edit_page()
{
	global $mybb, $templates, $lang, $editedby;
	$lang->load("legendperm");

	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
	$edit = get_post($pid);

	if($mybb->usergroup['canremoveeditedby'] == 1)
	{
		if($edit['disableeditedby'] == 1)
		{
			$disableeditedby = "checked=\"checked\"";
		}
		else
		{
			$disableeditedby = '';
		}
		eval("\$editedby = \"".$templates->get("editpost_editedby")."\";");
	}
}

// Admin CP permission control
function legendperm_usergroup_permission($above)
{
	global $mybb, $lang, $form;
	$lang->load("legendperm", true);

	if(isset($lang->editing_deleting_options) && $above['title'] == $lang->editing_deleting_options)
	{
		$above['content'] .= "<div class=\"group_settings_bit\">".$form->generate_check_box("canremoveeditedby", 1, $lang->can_remove_edited_by, array("checked" => $mybb->input['canremoveeditedby']))."</div>";
	}

	return $above;
}

function legendperm_usergroup_permission_commit()
{
	global $mybb, $updated_group;
	$updated_group['canremoveeditedby'] = $mybb->get_input('canremoveeditedby', MyBB::INPUT_INT);
}
