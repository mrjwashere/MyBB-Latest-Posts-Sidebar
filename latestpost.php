<?php
/**
 * latest posts sidebar
 *
 * 
 * 
 *
 **/

/* Hooks */
$plugins->add_hook("index_end", "latestposts");
$plugins->add_hook("xmlhttp", "latestposts_refresh_posts");
$plugins->add_hook('misc_start', 'latestposts_usercp');

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

function latestposts_info()
{
	global $lang;
	$lang->load("latestposts");
    return array(
        "name"          => $lang->plugname,
        "description"   => $lang->plugdesc,
        "website"       => "https://community.mybb.com/",
        "author"        => "Dalzier",
        "authorsite"    => "https://community.mybb.com/",
        "version"       => "3.0",
        "guid"          => "leatestposts",
        "compatibility" => "18*"
    );
}

function latestposts_install()
{
    global $db, $lang;
	$lang->load("latestposts");
	
	if(!$db->field_exists("hidden_lp", "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD `hidden_lp` varchar(500) NOT NULL DEFAULT ''");
	}	
	if(!$db->field_exists("marked_lp", "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD `marked_lp` varchar(500) NOT NULL DEFAULT ''");
	}
	
    $new_setting_group = array(
    "name" => "latestposts",
    "title" => $lang->settings_name,
	"description" => "Plugin configuration options for lastpost on index sidebar plugin",
    "disporder" => 1,
    "isdefault" => 0
    );

    $gid = $db->insert_query("settinggroups", $new_setting_group);

    $settings[] = array(
    "name" => "latestposts_threadcount",
    "title" => $lang->num_posts_to_show,
	"description" => "Number of posts required to show sidebar",
    "optionscode" => "text",
    "disporder" => 1,
    "value" => 15,
    "gid" => $gid
    );

    $settings[] = array(
    "name" => "latestposts_forumskip",
    "title" => $lang->forums_to_skip,
    "description" => $lang->forums_to_skip_desc,
    "optionscode" => "text",
    "disporder" => 2,
	"value" => "",
    "gid" => $gid
    );

    $settings[] = array(
    "name" => "latestposts_showtime",
    "title" => $lang->latestposts_showtime,
	"description" => "Show lastpost time",	
    "optionscode" => "yesno",
    "disporder" => 3,
    "value" => 1,
    "gid" => $gid
    );

    $settings[] = array(
    "name" => "latestposts_rightorleft",
    "title" => $lang->rightorleft,
	"description" => "Select the side of sidebar",
    "optionscode" => "select
right=".$lang->latestposts_right."
left=".$lang->latestposts_left,
    "disporder" => 4,
    "value" => "right",
    "gid" => $gid
    );

    foreach($settings as $array => $content)
    {
        $db->insert_query("settings", $content);
    }
    rebuild_settings();

	require_once MYBB_ADMIN_DIR.'/inc/functions_themes.php';

    // Add stylesheet to the master template so it becomes inherited.
    $stylesheet = <<<code
	.latestpost {
		padding: 2px 10px;
	}
	.lp_button{
		float:right;
		border-radius: 3px;
		padding: 3px 6px;
		background: #007088;
		color: #fff;
		border: 1px solid #0d6477;
		cursor:pointer;
	}
	.thread_marked::before
	{
		content: 'Important';
	}
	.thread_marked
	{
		padding: 2px 4px;
		background: #007088;
		color: #fff;
		border-radius: 3px;
		font-size: 11px;
		font-weight: bold;
	}
	.latest-post-avatar
	{
		border-radius: 5px;
		height: 35px;
		width: 35px;
		float: left;
		margin-right: 6px;
	}
	.latest-post-uname
	{
		font-size: 12px;
	}
	.latest-replies
	{
		border-radius: 4px;
		background: #1a1919;
		float: right;
		padding: 4px 8px;
	}
code;
    $css = array(
        'name' => 'latestposts.css',
        'tid' => '1',
		'attachedto' => '',
        'stylesheet' => $db->escape_string($stylesheet),
        'cachefile' => 'latestposts.css',
        'lastmodified' => TIME_NOW,
    );
    $db->insert_query('themestylesheets', $css);
    cache_stylesheet(1, "latestposts.css", $stylesheet);
    update_theme_stylesheet_list(1, false, true);
}

function latestposts_is_installed()
{
    global $db;
    $query = $db->simple_select("settinggroups", "*", "name='latestposts'");
    if($db->num_rows($query))
    {
        return TRUE;
    }
    return FALSE;
}

function latestposts_activate()
{
    global $db, $lang;
	$lang->load("latestposts");
    $templates['index_sidebar'] = '<table border="0" cellspacing="0" cellpadding="5" class="tborder">
	<thead>
		<tr>
			<td class="thead">
				<div><strong>{$lang->latest_posts_title}</strong><a href="misc.php?action=lastpost_configure" class="lp_button">Options</a></div>
			</td>
		</tr>
	</thead>
	<tbody>
		{$postslist}
	</tbody>
</table>';
    $templates['index_sidebar_post'] = '<tr>
	<td class="trow1 latestpost" valign="top" style="padding: 10px;">
		<img src="{$avatar}" class="latest-post-avatar" />
		<div class="latest-post-text">
			{$thread[\'mark\']}&nbsp;{$recentprefix}<a href="{$mybb->settings[\'bburl\']}/showthread.php?tid={$tid}&action=lastpost">{$postname}</a>
			<br>
			<span class="latest-post-uname">By {$lastposterlink}, {$lastposttimeago}</span>
		</div>
		<div class="latest-replies">{$lang->latest_post_replies}</div>
	</td>
</tr>';
    $templates['lastpost_usercp'] = '<html>
<head>
<title>Lastpost user control panel</title>
{$headerinclude}
</head>
<body>
{$header}
<table border="0" class="tborder">
	<tr>
		<td class="thead" valign="top">
			<strong>Use the panel to customize your own settings</strong>
		</td>
	</tr>
	<tr>
		<td class="tcat" valign="top">
			<strong>Forums to hide</strong>
		</td>
	</tr>
	<tr>
		<td class="trow1 latestpost" valign="top">
			<form method="post" action="xmlhttp.php?action=lastpost_hide_forums" id="lphf_form">
				<select group="lphf" name="lphf" id="select_lphf">
					{$lasposts_hide_forums_opts}
				</select>
				<input type="submit" value="ADD" />
			</form>
			<div class="forumlist" id="forumlist_hfd">{$lasposts_hfd}</div>
			<div id="results_lphf">&nbsp;</div>
			<script type="text/javascript">
				$(document).on("ready", function(){
					$("#lphf_form").on("submit", function(e){
						e.preventDefault();
						$.ajax({
							method: "post",
							url: "xmlhttp.php?action=lastpost_hide_forums",
							data: $(this).serialize(),
							success: function(data)
							{
								var fnamelphf = $("#select_lphf option:selected").text();				
								$("#select_lphf option[value=\'"+data.id+"\']").remove();
								$("#results_lphf").html(data.template).fadeOut(7000);
								$("#forumlist_hfd").append(fnamelphf+"<br />");
							}
						});
					});
				})
			</script>			
		</td>
	</tr>
	<tr>
		<td class="tcat" valign="top">
			<strong>Forums to mark</strong>
		</td>
	</tr>	
	<tr>
		<td class="trow1 latestpost" valign="top">
			<form method="post" action="xmlhttp.php?action=lastpost_mark_forums" id="lpmf_form">
				<select group="lpmf" name="lpmf" id="select_lpmf">
					{$lasposts_mark_forums_opts}
				</select>
				<input type="submit" value="ADD" />
			</form>
			<script type="text/javascript">
				$(document).on("ready", function(){
					$("#lpmf_form").on("submit", function(e){
						e.preventDefault();
						$.ajax({
							method: "post",
							url: "xmlhttp.php?action=lastpost_mark_forums",
							data: $(this).serialize(),
							success: function(data)
							{
								var fnamelpmf = $("#select_lpmf option:selected").text();			
								$("#select_lpmf option[value=\'"+data.id+"\']").remove();
								$("#results_lpmf").html(data.template).fadeOut(7000);
								$("#forumlist_mfd").append(fnamelpmf+"<br />");
							}
						});
					});				
				})
			</script>			
			<div class="forumlist" id="forumlist_mfd">{$lasposts_mfd}</div>
			<div id="results_lpmf">&nbsp;</div>
		</td>
	</tr>	
</table>
{$footer}
</body>
</html>';
    $templates['lastpost_hide_forums_delete'] = '<div style="display:block" id="desap_btn1">{$lasposts_hide_forums}   <a href="xmlhttp.php?action=lastpost_hide_forums_remove&amp;id={$query_fh_list[\'fid\']}" id="lphf_{$query_fh_list[\'fid\']}">[ X ]</a>
<script type="text/javascript">	
	$(document).on("ready", function(){
		$("#lphf_{$query_fh_list[\'fid\']}").on("click",function(e){
			e.preventDefault();
			$.ajax({
				method: "post",
				url: "xmlhttp.php?action=lastpost_hide_forums_remove&id={$query_fh_list[\'fid\']}",
				data: $(this).serialize(),
				success: function(data)
				{
					$("#desap_btn1").remove();
					$("#results_lphf").html(data).fadeOut(7000);
				}
			});						
		});
	});
</script>
</div>';
    $templates['lastpost_mark_forums_delete'] = '<div style="display:block" id="desap_btn2">{$lasposts_mark_forums}   <a href="xmlhttp.php?action=lastpost_mark_forums_remove&amp;id={$query_fm_list[\'fid\']}" id="lpmf_{$query_fm_list[\'fid\']}">[ X ]</a>
<script type="text/javascript">	
	$(document).on("ready", function(){
		$("#lpmf_{$query_fm_list[\'fid\']}").on("click",function(e){
			e.preventDefault();
			$.ajax({
				method: "post",
				url: "xmlhttp.php?action=lastpost_hide_forums_remove&id={$query_fm_list[\'fid\']}",
				data: $(this).serialize(),
				success: function(data)
				{
					$("#desap_btn2").remove();
					$("#results_lpmf").html(data).fadeOut(7000);
				}
			});						
		});
	});		
</script>
</div>';
			
    foreach($templates as $title => $template) {
		$new_template = array('title' => $db->escape_string($title), 'template' => $db->escape_string($template), 'sid' => '-1', 'version' => '1800', 'dateline' => TIME_NOW);
		$db->insert_query('templates', $new_template);
	}

    //Archivo requerido para reemplazo de templates
    require_once '../inc/adminfunctions_templates.php';

    find_replace_templatesets('index', "#" . preg_quote('{$forums}') . "#i", '<div style="float:{$left};width: 74%;">{$forums}</div>
<div style="float:{$right};width:25%;">{$sidebar}</div>');
}

function latestposts_deactivate()
{
    global $db;
    $db->delete_query("templates", "title IN('index_sidebar','index_sidebar_post','lastpost_usercp','lastpost_hide_forums_delete','lastpost_mark_forums_delete')");

    //Archivo requerido para reemplazo de templates
    require_once '../inc/adminfunctions_templates.php';
	
    find_replace_templatesets('index', "#" . preg_quote('<div style="float:{$left};width: 74%;">{$forums}</div>
<div style="float:{$right};width:25%;">{$sidebar}</div>') . "#i", '{$forums}');
}

function latestposts_uninstall()
{
    global $db;
    $query = $db->simple_select("settinggroups", "gid", "name='latestposts'");
    $gid = $db->fetch_field($query, "gid");
    if(!$gid) {
        return;
    }
    $db->delete_query("settinggroups", "name='latestposts'");
    $db->delete_query("settings", "gid=$gid");
    rebuild_settings();

	if($db->field_exists("hidden_lp", "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP `hidden_lp`");
	}	
	if($db->field_exists("marked_lp", "users"))
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP `marked_lp`");
	}	
    require_once(MYBB_ROOT."admin/inc/functions_themes.php");

    // Remove latestposts.css from the theme cache directories if it exists
    $query = $db->simple_select("themes", "tid");
    while($tid = $db->fetch_field($query, "tid"))
    {
        $css_file = MYBB_ROOT."cache/themes/theme{$tid}/latestposts.css";
        if(file_exists($css_file))
            unlink($css_file);
    }

    update_theme_stylesheet_list("1", false, true);
}


function latestposts($return=false)
{
	global $mybb,$lang, $db, $templates, $postslist, $sidebar, $right, $left, $marketlist, $ressourceslist, $reputationlist;
	$lang->load("latestposts");
    $threadlimit = (int) $mybb->settings['latestposts_threadcount'];
    $where = NULL;

    if(!$threadlimit) {
	    $threadlimit = 15;
	}
    if($mybb->settings['latestposts_forumskip']) {
        $where .= " AND t.fid NOT IN(" . $mybb->settings['latestposts_forumskip'] . ") ";
    }
	require_once MYBB_ROOT."inc/functions_search.php";

	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums) {
		$where .= " AND t.fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums) {
		$where .= " AND t.fid NOT IN ($inactiveforums)";
	}

	$permissions = forum_permissions();
	for($i = 0; $i <= sizeof($permissions); $i++){
		if(isset($permissions[$i]['fid']) && ( $permissions[$i]['canview'] == 0 || $permissions[$i]['canviewthreads'] == 0 ))
		{
			$where .= " AND t.fid <> ".$permissions[$i]['fid'];
		}
	}
	$forums_to_hide = $mybb->user['hidden_lp'];
	$forums_to_mark = $mybb->user['marked_lp'];

	if(!empty($forums_to_hide))
	{
		$where .= " AND t.fid NOT IN({$forums_to_hide})";
	}
			
	$where .= " AND p.visible <> -1";

    // Last Posts Everywhere
	$query = $db->query("
		SELECT t.*, lp.avatar AS avatar, u.username AS userusername, u.usergroup, u.displaygroup, lp.usergroup AS lastusergroup, lp.displaygroup as lastdisplaygroup, p.visible
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
		LEFT JOIN ".TABLE_PREFIX."users lp ON (t.lastposteruid=lp.uid)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (t.tid=p.tid AND replyto = 0)
        WHERE 1=1 {$where}
		ORDER BY t.lastpost DESC
		LIMIT $threadlimit
	");
	while($thread = $db->fetch_array($query)) {
		if(!empty($forums_to_mark))
		{
			$forums_to_mark_list = explode(",", $forums_to_mark);
			if(in_array($thread['fid'], $forums_to_mark_list))
				$thread['mark'] = "<i class=\"fa fa-star fa-star-circle\"></i>&nbsp;";
			else
				$thread['mark'] = null;
		}
		$avatar = htmlspecialchars_uni($thread['avatar']);
		if(empty($avatar))
		{
		 	$avatar = htmlspecialchars_uni($mybb->settings['useravatar']);
		}
        $tid = $thread['tid'];
		
		$queryy = $db->simple_select("posts", "COUNT(*) AS max_replies", "tid='{$tid}'");
		$reply_count = $db->fetch_field($queryy, "max_replies");
		
		$reply_count = $reply_count - 1;
		
		$lang->latest_post_replies = $reply_count;
		
        $postname = htmlspecialchars_uni($thread['subject']);
		$postname = format_name($thread['subject'], $thread['usergroup'], $thread['displaygroup']);
		if(my_strlen($postname) > 25) {
			$postname = my_substr($thread['subject'], 0, 25)."...";
		}
        $lastpostlink = get_thread_link($thread['tid'], "", "lastpost");
		$lastposttimeago = my_date("relative", $thread['lastpost']);
		$lastposter = htmlspecialchars($thread['lastposter']);
		$lastposteruid = $thread['lastposteruid'];

        /* if($mybb->settings['latestposts_showtime'] == 1) {
            $latestposttime = $lang->sprintf($lang->latestposttime, $lastposttimeago);
		}
		else{
			$latestposttime =  NULL;
		} */
		
		if($mybb->settings['latestposts_showtime'] == 1) {
			if(strpos($lastposttimeago, 'minutes') !== false) {
				$str_time = str_replace('minutes', 'm', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			elseif(strpos($lastposttimeago, 'hours') !== false) {
				$str_time = str_replace('hours', 'h', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			elseif(strpos($lastposttimeago, 'minute') !== false) {
				$str_time = str_replace('minute', 'm', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			elseif(strpos($lastposttimeago, 'hour') !== false) {
				$str_time = str_replace('hour', 'h', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			else {
				$lastposttimeago= $lastposttimeago;
			}  
		}
		else{
			$lastposttimeago =  NULL;
		}

		if($lastposteruid == 0) {
			$lastposterlink = $lastposter;
		}
        else {
        	$lastposterlink = build_profile_link(format_name($lastposter, $thread['lastusergroup'], $thread['lastdisplaygroup']), $lastposteruid);
		}

        eval("\$postslist .= \"".$templates->get("index_sidebar_post")."\";");
		}
    
    // Last Posts in Courses
    
    
    $where = "t.fid = 69 OR t.fid = 70 OR t.fid = 71 OR t.fid = 72 OR t.fid = 73 OR t.fid = 74 OR t.fid = 75 OR t.fid = 76 OR t.fid = 77 OR t.fid = 78 OR t.fid = 79 OR t.fid = 80 OR t.fid = 81 OR t.fid = 82 OR t.fid = 83 OR t.fid = 84 OR t.fid = 68 OR t.fid = 85 OR t.fid = 86";
        
    $query = $db->query("
		SELECT t.*, lp.avatar AS avatar, u.username AS userusername, u.usergroup, u.displaygroup, lp.usergroup AS lastusergroup, lp.displaygroup as lastdisplaygroup, p.visible
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
		LEFT JOIN ".TABLE_PREFIX."users lp ON (t.lastposteruid=lp.uid)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (t.tid=p.tid AND replyto = 0)
        WHERE {$where}
		ORDER BY t.lastpost DESC
		LIMIT $threadlimit
	");
	while($thread = $db->fetch_array($query)) {
		if(!empty($forums_to_mark))
		{
			$forums_to_mark_list = explode(",", $forums_to_mark);
			if(in_array($thread['fid'], $forums_to_mark_list))
				$thread['mark'] = "<i class=\"fa fa-star fa-star-circle\"></i>&nbsp;";
			else
				$thread['mark'] = null;
		}
		$avatar = htmlspecialchars_uni($thread['avatar']);
		if(empty($avatar))
		{
		 	$avatar = htmlspecialchars_uni($mybb->settings['useravatar']);
		}
        $tid = $thread['tid'];
		
		$queryy = $db->simple_select("posts", "COUNT(*) AS max_replies", "tid='{$tid}'");
		$reply_count = $db->fetch_field($queryy, "max_replies");
		
		$reply_count = $reply_count - 1;
		
		$lang->latest_post_replies = $reply_count;
		
        $postname = htmlspecialchars_uni($thread['subject']);
		$postname = format_name($thread['subject'], $thread['usergroup'], $thread['displaygroup']);
		if(my_strlen($postname) > 25) {
			$postname = my_substr($thread['subject'], 0, 25)."...";
		}
        $lastpostlink = get_thread_link($thread['tid'], "", "lastpost");
		$lastposttimeago = my_date("relative", $thread['lastpost']);
		$lastposter = htmlspecialchars($thread['lastposter']);
		$lastposteruid = $thread['lastposteruid'];

        /* if($mybb->settings['latestposts_showtime'] == 1) {
            $latestposttime = $lang->sprintf($lang->latestposttime, $lastposttimeago);
		}
		else{
			$latestposttime =  NULL;
		} */
		
		if($mybb->settings['latestposts_showtime'] == 1) {
			if(strpos($lastposttimeago, 'minutes') !== false) {
				$str_time = str_replace('minutes', 'm', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			elseif(strpos($lastposttimeago, 'hours') !== false) {
				$str_time = str_replace('hours', 'h', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			elseif(strpos($lastposttimeago, 'minute') !== false) {
				$str_time = str_replace('minute', 'm', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			elseif(strpos($lastposttimeago, 'hour') !== false) {
				$str_time = str_replace('hour', 'h', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			else {
				$lastposttimeago= $lastposttimeago;
			}  
		}
		else{
			$lastposttimeago =  NULL;
		}

		if($lastposteruid == 0) {
			$lastposterlink = $lastposter;
		}
        else {
        	$lastposterlink = build_profile_link(format_name($lastposter, $thread['lastusergroup'], $thread['lastdisplaygroup']), $lastposteruid);
		}

        eval("\$courseslist .= \"".$templates->get("index_sidebar_post")."\";");
		}
    
    // Last Posts in Home
    
    $where = "t.fid = 2 OR t.fid = 3 OR t.fid = 4 OR t.fid = 6 OR t.fid = 20 OR t.fid = 7 OR t.fid = 8 OR t.fid = 9 OR t.fid = 10 OR t.fid = 17 OR t.fid = 18 OR t.fid = 19 OR t.fid = 20 OR t.fid = 7 OR t.fid = 11 OR t.fid = 12 OR t.fid = 8 OR t.fid = 13 OR t.fid = 14 OR t.fid = 15 OR t.fid = 16";
    
    $query = $db->query("
		SELECT t.*, lp.avatar AS avatar, u.username AS userusername, u.usergroup, u.displaygroup, lp.usergroup AS lastusergroup, lp.displaygroup as lastdisplaygroup, p.visible
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
		LEFT JOIN ".TABLE_PREFIX."users lp ON (t.lastposteruid=lp.uid)
		LEFT JOIN ".TABLE_PREFIX."posts p ON (t.tid=p.tid AND replyto = 0)
        WHERE {$where}
		ORDER BY t.lastpost DESC
		LIMIT $threadlimit
	");
	while($thread = $db->fetch_array($query)) {
		if(!empty($forums_to_mark))
		{
			$forums_to_mark_list = explode(",", $forums_to_mark);
			if(in_array($thread['fid'], $forums_to_mark_list))
				$thread['mark'] = "<i class=\"fa fa-star fa-star-circle\"></i>&nbsp;";
			else
				$thread['mark'] = null;
		}
		$avatar = htmlspecialchars_uni($thread['avatar']);
		if(empty($avatar))
		{
		 	$avatar = htmlspecialchars_uni($mybb->settings['useravatar']);
		}
        $tid = $thread['tid'];
		
		$queryy = $db->simple_select("posts", "COUNT(*) AS max_replies", "tid='{$tid}'");
		$reply_count = $db->fetch_field($queryy, "max_replies");
		
		$reply_count = $reply_count - 1;
		
		$lang->latest_post_replies = $reply_count;
		
        $postname = htmlspecialchars_uni($thread['subject']);
		$postname = format_name($thread['subject'], $thread['usergroup'], $thread['displaygroup']);
		if(my_strlen($postname) > 25) {
			$postname = my_substr($thread['subject'], 0, 25)."...";
		}
        $lastpostlink = get_thread_link($thread['tid'], "", "lastpost");
		$lastposttimeago = my_date("relative", $thread['lastpost']);
		$lastposter = htmlspecialchars($thread['lastposter']);
		$lastposteruid = $thread['lastposteruid'];

        /* if($mybb->settings['latestposts_showtime'] == 1) {
            $latestposttime = $lang->sprintf($lang->latestposttime, $lastposttimeago);
		}
		else{
			$latestposttime =  NULL;
		} */
		
		if($mybb->settings['latestposts_showtime'] == 1) {
			if(strpos($lastposttimeago, 'minutes') !== false) {
				$str_time = str_replace('minutes', 'm', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			elseif(strpos($lastposttimeago, 'hours') !== false) {
				$str_time = str_replace('hours', 'h', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			elseif(strpos($lastposttimeago, 'minute') !== false) {
				$str_time = str_replace('minute', 'm', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			elseif(strpos($lastposttimeago, 'hour') !== false) {
				$str_time = str_replace('hour', 'h', $lastposttimeago);
				$str_time = str_replace(' ', '', $str_time);
				$lastposttimeago = str_replace('ago', ' ago', $str_time);
			}
			else {
				$lastposttimeago= $lastposttimeago;
			}  
		}
		else{
			$lastposttimeago =  NULL;
		}

		if($lastposteruid == 0) {
			$lastposterlink = $lastposter;
		}
        else {
        	$lastposterlink = build_profile_link(format_name($lastposter, $thread['lastusergroup'], $thread['lastdisplaygroup']), $lastposteruid);
		}

        eval("\$homelist .= \"".$templates->get("index_sidebar_post")."\";");
		}
    
    // TOP Reputation
    
    $query = $db->query("SELECT * FROM ".TABLE_PREFIX."reputation ORDER BY rid DESC LIMIT $threadlimit");
    
    while ($rep = $db->fetch_array($query))
    {
        $query2 = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid = ".$rep['uid']);
        while ($user = $db->fetch_array($query2))
        {
            $avatar = htmlspecialchars_uni($user['avatar']);
            if(empty($avatar))
            {
                $avatar = htmlspecialchars_uni($mybb->settings['useravatar']);
            }
            //$username = $user['username'];
            $username = build_profile_link(format_name($user['username'], $user['usergroup'], $user['usergroup']), $user['uid']);
            $giver_uid = $rep['adduid'];
            $query3 = $db->query("SELECT * FROM ".TABLE_PREFIX."users WHERE uid = ".$giver_uid);
            while ($giver = $db->fetch_array($query3))
            {
                $g_username = build_profile_link(format_name($giver['username'], $giver['usergroup'], $giver['usergroup']), $giver['uid']);
            }
        }
        $g_date = my_date("relative", $rep['dateline']);
        if ($rep['reputation'] > 0)
        {
            $given = "<a style='color:green;'>+".$rep['reputation']."</a>";
            $g_sign = "positive";
        }
        elseif ($rep['reputation'] < 0)
        {
            $given = "<a style='color:red;'>".$rep['reputation']."</a>";
            $g_sign = "negative";
        }
        else
        {
            $given = "<a>".$rep['reputation']."</a>";
            $g_sign = "neutral";
        }
        eval("\$reputationlist .= \"".$templates->get("index_sidebar_reputation")."\";");
    }
    

    ////

		if($mybb->settings['latestposts_rightorleft'] == "right") {
			$right = "right";
			$left = "left";
		}
		else {
			$right = "left";
			$left = "right";
		}
        eval("\$sidebar = \"".$templates->get("index_sidebar")."\";");
		if($return)
		{
			return $sidebar;
		}
}

function latestposts_refresh_posts()
{
    global $db, $mybb;
    if($mybb->input['action'] == "latest_posts")
    {
        require_once MYBB_ROOT . "/inc/plugins/latestposts.php";
		echo(latestposts(true));
        die;
    }
	else if($mybb->get_input('action') == "lastpost_hide_forums")
	{
		if(!$mybb->user['uid'])
			return false;
		$to_add = (int)$mybb->input['lphf'];
		if($to_add > 0)
		{
			$to_find = $mybb->user['hidden_lp'];
			$pos = strpos($to_find, ",".$to_add);
			if($pos === false)
			{
				if(!empty($to_find))
					$to_find .= ",".(int)$to_add;
				else
					$to_find = (int)$to_add;
				$db->update_query("users",array("hidden_lp" => $to_find),"uid=".(int)$mybb->user['uid']);
				header("Content-type: application/json; charset=utf8");
				$template = "<div class=\"success_message\">Forum {$to_add} added success !!!</div>";
				$data = array("id" => (int)$to_add, "template" => $template);
				echo json_encode($data);
				exit;
				//redirect($mybb->settings['bburl']."/misc.php?action=lastpost_configure", "Forum {$to_add} added success !!!");
			}
			else
			{
				header("Content-type: application/json; charset=utf8");
				$template = "<div class=\"error_message\">Forum already added</div>";
				$data = array("id" => (int)$to_add, "template" => $template);
				echo json_encode($data);
				//redirect($mybb->settings['bburl']."/misc.php?action=lastpost_configure", "Forum already added");
			}
		}
	}
	else if($mybb->get_input('action') == "lastpost_hide_forums_remove")
	{
		$remove_id = (int)$mybb->input['id'];
		$db->simple_select("users","hidden_lp","uid=".(int)$mybb->user['uid']);
		$to_find = $mybb->user['hidden_lp'];
		if(!empty($to_find))
		{
			$pos = true;
			$search_array = explode(",", $to_find);
			if (($key = array_search($remove_id, $search_array)) !== false) {
				unset($search_array[$key]);
			}
			$search_array = implode(",", $search_array);
		}
		if($pos == true)
		{
			$db->update_query("users",array("hidden_lp" => $search_array),"uid=".(int)$mybb->user['uid']);
		}
		header("Content-type: application/json; charset=utf8");
		$template = "<div class=\"success_message\">Forum {$remove_id} removed success !!!</div>";
		echo json_encode($template);
		exit;		
		///redirect($mybb->settings['bburl']."/misc.php?action=lastpost_configure", "Forum {$remove_id} removed success !!!");
	}	
	else if($mybb->get_input('action') == "lastpost_mark_forums")
	{
		
		if(!$mybb->user['uid'])
			return false;		
		$to_add = (int)$mybb->input['lpmf'];
		if($to_add > 0)
		{
			$to_find = $mybb->user['marked_lp'];
			$pos = strpos($to_find, ",".$to_add);
			if($pos === false)
			{
				if(!empty($to_find))
					$to_find .= ",".(int)$to_add;
				else
					$to_find = (int)$to_add;
				$db->update_query("users",array("marked_lp" => $to_find),"uid=".(int)$mybb->user['uid']);
				header("Content-type: application/json; charset=utf8");
				$template = "<div class=\"success_message\">Forum {$to_add} added success !!!</div>";
				$data = array("id" => (int)$to_add, "template" => $template);
				echo json_encode($data);
				exit;
				//redirect($mybb->settings['bburl']."/misc.php?action=lastpost_configure", "Forum {$to_add} added success !!!");
			}
			else
			{
				header("Content-type: application/json; charset=utf8");
				$template = "<div class=\"error_message\">Forum already added</div>";
				$data = array("id" => (int)$to_add, "template" => $template);
				echo json_encode($data);
				exit;
				//redirect($mybb->settings['bburl']."/misc.php?action=lastpost_configure", "Forum already added");				
			}
		}
	}
	else if($mybb->get_input('action') == "lastpost_mark_forums_remove")
	{
		$remove_id = (int)$mybb->input['id'];
		$db->simple_select("users","marked_lp","uid=".(int)$mybb->user['uid']);
		$to_find = $mybb->user['marked_lp'];
		if(!empty($to_find))
		{
			$pos = true;
			$search_array = explode(",", $to_find);
			if (($key = array_search($remove_id, $search_array)) !== false) {
				unset($search_array[$key]);
			}
			$search_array = implode(",", $search_array);
		}
		if($pos === true)
		{
			$db->update_query("users",array("marked_lp" => $search_array),"uid=".(int)$mybb->user['uid']);
		}
		header("Content-type: application/json; charset=utf8");
		$template = "<div class=\"success_message\">Forum {$remove_id} removed success !!!</div>";
		echo json_encode($template);
		exit;		
		//redirect($mybb->settings['bburl']."/misc.php?action=lastpost_configure", "Forum {$remove_id} removed success !!!");
	}
}

function latestposts_usercp()
{
    global $db, $mybb, $templates, $lang, $header, $headerinclude, $footer;

    if($mybb->get_input('action') == 'lastpost_configure')
    {
        add_breadcrumb('Latest Posts Options', "misc.php?action=lastpost_configure");
		if(!$mybb->user['uid'])
		{
			$lasposts_hide_forums_opts = "<option value=\"0\">You can not use this function</option>";
			$lasposts_mark_forums_opts = "<option value=\"0\">You can not use this function</option>";
		}
		else
		{
			$sql_where1 = "";
			$sql_where2 = "";
			$sql_where = "";
			$lasposts_mfd = "";
			$lasposts_hfd = "";
			$forums_to_hide = $mybb->user['hidden_lp'];
			$forums_to_mark = $mybb->user['marked_lp'];
			$unviewable = get_unviewable_forums(true);
			if($unviewable)
			{
				$sql_where .= " AND fid NOT IN ($unviewable)";
			}
			$inactive = get_inactive_forums();
			if($inactive)
			{
				$sql_where .= " AND fid NOT IN ($inactive)";
			}
			$permissions = forum_permissions();
			for($i = 0; $i <= sizeof($permissions); $i++){
				if(isset($permissions[$i]['fid']) && ( $permissions[$i]['canview'] == 0 || $permissions[$i]['canviewthreads'] == 0 ))
				{
					$sql_where .= " AND fid <> ".$permissions[$i]['fid'];
				}
			}		
			if(!empty($forums_to_hide))
			{
				$sql_where1 = " AND fid NOT IN({$forums_to_hide})";
			}
			if(!empty($forums_to_mark))
			{
				$sql_where2 = " AND fid NOT IN({$forums_to_mark})";
			}
			if(!empty($forums_to_hide))
			{
				$sql_where_list1 = " AND fid IN({$forums_to_hide})";
				$query_fhl = $db->simple_select("forums","*","type='f'{$sql_where_list1}");
				while($query_fh_list = $db->fetch_array($query_fhl))
				{
					$lasposts_hide_forums = htmlspecialchars_uni($query_fh_list['name']);					
					eval("\$lasposts_hfd .= \"".$templates->get("lastpost_hide_forums_delete", 1, 0)."\";");					
				}
			}
			if(!empty($forums_to_mark))
			{	
				$sql_where_list2 = " AND fid IN({$forums_to_mark})";
				$query_fml = $db->simple_select("forums","*","type='f'{$sql_where_list2}");
				while($query_fm_list = $db->fetch_array($query_fml))
				{
					$lasposts_mark_forums = htmlspecialchars_uni($query_fm_list['name']);					
					eval("\$lasposts_mfd .= \"".$templates->get("lastpost_mark_forums_delete", 1, 0)."\";");
				}			
			}			
			$query = $db->simple_select("forums","*","type='f'{$sql_where1}{$sql_where}");
			while($lp_hf = $db->fetch_array($query))
			{
				$lasposts_hide_forums_opts .= "<option value=\"{$lp_hf['fid']}\">{$lp_hf['name']}</option>";
			}		
			$query2 = $db->simple_select("forums","*","type='f'{$sql_where2}{$sql_where}");
			while($lp_mf = $db->fetch_array($query2))
			{
				$lasposts_mark_forums_opts .= "<option value=\"{$lp_mf['fid']}\">{$lp_mf['name']}</option>";	
			}
		}
        eval('$page  = "' . $templates->get('lastpost_usercp') . '";');
        output_page($page);
    }
}
