<?php

	/**
	 * Elgg file delete
	 * 
	 * @package ElggFile
	 */

		$guid = (int) get_input('file');
		if ($file = get_entity($guid)) {

			if ($file->canEdit()) {

				$container = get_entity($file->container_guid);
				
				$thumbnail = $file->thumbnail;
				$smallthumb = $file->smallthumb;
				$largethumb = $file->largethumb;
				if ($thumbnail) {

					$delfile = new ElggFile();
					$delfile->owner_guid = $file->owner_guid;
					$delfile->setFilename($thumbnail);
					$delfile->delete();

				}
				if ($smallthumb) {

					$delfile = new ElggFile();
					$delfile->owner_guid = $file->owner_guid;
					$delfile->setFilename($smallthumb);
					$delfile->delete();

				}
				if ($largethumb) {

					$delfile = new ElggFile();
					$delfile->owner_guid = $file->owner_guid;
					$delfile->setFilename($largethumb);
					$delfile->delete();

				}
				
				if (!$file->delete()) {
					register_error(elgg_echo("file:deletefailed"));
				} else {
					system_message(elgg_echo("file:deleted"));
				}

			} else {
				
				$container = get_loggedin_user();
				register_error(elgg_echo("file:deletefailed"));
				
			}

		} else {
			
			register_error(elgg_echo("file:deletefailed"));
			
		}
		
		forward("pg/file/$container->username/");

?>