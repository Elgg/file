<?php
/**
 * Elgg file plugin
 *
 * @package ElggFile
 */

elgg_register_event_handler('init', 'system', 'file_init');

/**
 * File plugin initialisation functions.
 */
function file_init() {

	// register a library of helper functions
	elgg_register_library('elgg:file', elgg_get_plugin_path() . 'file/lib/file.php');

	// Site navigation
	$item = new ElggMenuItem('file', elgg_echo('file'), 'pg/file/all');
	elgg_register_menu_item('site', $item);

	// Extend CSS
	elgg_extend_view('css/screen', 'file/css');

	// extend group main page
	elgg_extend_view('groups/tool_latest', 'file/group_module');

	// Register a page handler, so we can have nice URLs
	register_page_handler('file', 'file_page_handler');

	// Add a new file widget
	elgg_register_widget_type('filerepo', elgg_echo("file"), elgg_echo("file:widget:description"));

	// Register a URL handler for files
	register_entity_url_handler('file_url', 'object', 'file');

	// Register granular notification for this object type
	register_notification_object('object', 'file', elgg_echo('file:newupload'));

	// Listen to notification events and supply a more useful message
	elgg_register_plugin_hook_handler('notify:entity:message', 'object', 'file_notify_message');

	// add the group files tool option
	add_group_tool_option('file', elgg_echo('groups:enablefiles'), true);

	// Register entity type for search
	register_entity_type('object', 'file');

	// add a file link to owner blocks
	elgg_register_plugin_hook_handler('register', 'menu:owner_block', 'file_owner_block_menu');

	// Register actions
	$action_path = elgg_get_plugin_path() . 'file/actions/file';
	elgg_register_action("file/upload", "$action_path/upload.php");
	elgg_register_action("file/delete", "$action_path/delete.php");
	// temporary - see #2010
	elgg_register_action("file/download", "$action_path/download.php");


	// embed support
	elgg_register_plugin_hook_handler('embed_get_sections', 'all', 'file_embed_get_sections');
	elgg_register_plugin_hook_handler('embed_get_items', 'file', 'file_embed_get_items');
	elgg_register_plugin_hook_handler('embed_get_upload_sections', 'all', 'file_embed_get_upload_sections');
}

/**
 * Dispatches file pages.
 * URLs take the form of
 *  All files:       pg/file/all
 *  User's files:    pg/file/owner/<username>
 *  Friends' files:  pg/file/friends/<username>
 *  View file:       pg/file/view/<guid>/<title>
 *  New file:        pg/file/add/<guid>
 *  Edit file:       pg/file/edit/<guid>
 *  Group files:     pg/file/group/<guid>/owner
 *
 * Title is ignored
 *
 * @param array $page
 * @return NULL
 */
function file_page_handler($page) {

	if (!isset($page[0])) {
		$page[0] = 'all';
	}

	$file_dir = elgg_get_plugin_path() . 'file';

	$page_type = $page[0];
	switch ($page_type) {
		case 'owner':
			$owner = get_user_by_username($page[1]);
			set_input('guid', $owner->guid);
			include "$file_dir/index.php";
			break;
		case 'friends':
			set_input('username', $page[1]);
			include "$file_dir/friends.php";
			break;
		case 'view':
			set_input('guid', $page[1]);
			include "$file_dir/view.php";
			break;
		case 'add':
			set_input('guid', $page[1]);
			include "$file_dir/upload.php";
			break;
		case 'edit':
			set_input('guid', $page[1]);
			include "$file_dir/edit.php";
			break;
		case 'group':
			set_input('guid', $page[1]);
			include "$file_dir/index.php";
			break;
		case 'all':
		default:
			include "$file_dir/world.php";
			break;
	}
}

/**
 * Creates the notification message body
 *
 * @param unknown_type $hook
 * @param unknown_type $entity_type
 * @param unknown_type $returnvalue
 * @param unknown_type $params
 */
function file_notify_message($hook, $entity_type, $returnvalue, $params) {
	$entity = $params['entity'];
	$to_entity = $params['to_entity'];
	$method = $params['method'];
	if (($entity instanceof ElggEntity) && ($entity->getSubtype() == 'file')) {
		$descr = $entity->description;
		$title = $entity->title;
		global $CONFIG;
		$url = elgg_get_site_url() . "pg/view/" . $entity->guid;
		if ($method == 'sms') {
			$owner = $entity->getOwnerEntity();
			return $owner->name . ' ' . elgg_echo("file:via") . ': ' . $url . ' (' . $title . ')';
		}
		if ($method == 'email') {
			$owner = $entity->getOwnerEntity();
			return $owner->name . ' ' . elgg_echo("file:via") . ': ' . $entity->title . "\n\n" . $descr . "\n\n" . $entity->getURL();
		}
		if ($method == 'web') {
			$owner = $entity->getOwnerEntity();
			return $owner->name . ' ' . elgg_echo("file:via") . ': ' . $entity->title . "\n\n" . $descr . "\n\n" . $entity->getURL();
		}
	}
	return null;
}

/**
* Add a menu item to the user ownerblock
*/
function file_owner_block_menu($hook, $type, $return, $params) {
	if (elgg_instanceof($params['entity'], 'user')) {
		$url = "pg/file/owner/{$params['entity']->username}";
		$item = new ElggMenuItem('file', elgg_echo('file'), $url);
		$return[] = $item;
	} else {
		if ($params['entity']->file_enable != "no") {
			$url = "pg/file/group/{$params['entity']->guid}/owner";
			$item = new ElggMenuItem('file', elgg_echo('file:group'), $url);
			$return[] = $item;
		}
	}

	return $return;
}

/**
 * Returns an overall file type from the mimetype
 *
 * @param string $mimetype The MIME type
 * @return string The overall type
 */
function file_get_simple_type($mimetype) {

	switch ($mimetype) {
		case "application/msword":
			return "document";
			break;
		case "application/pdf":
			return "document";
			break;
	}

	if (substr_count($mimetype, 'text/')) {
		return "document";
	}

	if (substr_count($mimetype, 'audio/')) {
		return "audio";
	}

	if (substr_count($mimetype, 'image/')) {
		return "image";
	}

	if (substr_count($mimetype, 'video/')) {
		return "video";
	}

	if (substr_count($mimetype, 'opendocument')) {
		return "document";
	}

	return "general";
}

// deprecated and will be removed
function get_general_file_type($mimetype) {
	elgg_deprecated_notice('Use file_get_simple_type() instead of get_general_file_type()', 1.8);
	return file_get_simple_type($mimetype);
}

/**
 * Returns a list of filetypes
 *
 * @param int       $container_guid The GUID of the container of the files
 * @param bool      $friends        Whether we're looking at the container or the container's friends
 * @return string The typecloud
 */
function file_get_type_cloud($container_guid = "", $friends = false) {

	$container_guids = $container_guid;

	if ($friends) {
		// tags interface does not support pulling tags on friends' content so
		// we need to grab all friends
		$friend_entities = get_user_friends($container_guid, "", 999999, 0);
		if ($friend_entities) {
			$friend_guids = array();
			foreach ($friend_entities as $friend) {
				$friend_guids[] = $friend->getGUID();
			}
		}
		$container_guids = $friend_guids;
	}

	elgg_register_tag_metadata_name('simpletype');
	$options = array(
		'type' => 'object',
		'subtype' => 'file',
		'container_guids' => $container_guids,
		'threshold' => 0,
		'limit' => 10,
		'tag_names' => array('simpletype')
	);
	$types = elgg_get_tags($options);

	$params = array(
		'friends' => $friends,
		'types' => $types,
	);

	return elgg_view('file/typecloud', $params);
}

function get_filetype_cloud($owner_guid = "", $friends = false) {
	elgg_deprecated_notice('Use file_get_type_cloud instead of get_filetype_cloud', 1.8);
	return file_get_type_cloud($owner_guid, $friends);
}

/**
 * Register file as an embed type.
 *
 * @param unknown_type $hook
 * @param unknown_type $type
 * @param unknown_type $value
 * @param unknown_type $params
 */
function file_embed_get_sections($hook, $type, $value, $params) {
	$value['file'] = array(
		'name' => elgg_echo('file'),
		'layout' => 'list',
		'icon_size' => 'small',
	);

	return $value;
}

/**
 * Return a list of files for embedding
 *
 * @param unknown_type $hook
 * @param unknown_type $type
 * @param unknown_type $value
 * @param unknown_type $params
 */
function file_embed_get_items($hook, $type, $value, $params) {
	$options = array(
		'owner_guid' => get_loggedin_userid(),
		'type_subtype_pair' => array('object' => 'file'),
		'count' => TRUE
	);

	if ($count = elgg_get_entities($options)) {
		$value['count'] += $count;

		unset($options['count']);
		$options['offset'] = $params['offset'];
		$options['limit'] = $params['limit'];

		$items = elgg_get_entities($options);

		$value['items'] = array_merge($items, $value['items']);
	}

	return $value;
}

/**
 * Register file as an embed type.
 *
 * @param unknown_type $hook
 * @param unknown_type $type
 * @param unknown_type $value
 * @param unknown_type $params
 */
function file_embed_get_upload_sections($hook, $type, $value, $params) {
	$value['file'] = array(
		'name' => elgg_echo('file'),
		'view' => 'file/embed_upload'
	);

	return $value;
}

/**
 * Populates the ->getUrl() method for file objects
 *
 * @param ElggEntity $entity File entity
 * @return string File URL
 */
function file_url($entity) {
	$title = $entity->title;
	$title = elgg_get_friendly_title($title);
	return "pg/file/view/" . $entity->getGUID() . "/" . $title;
}
