<?php
include_once('functions.php');
require_once('workflows.php');

$query = $argv[1];
# thanks to http://www.alfredforum.com/topic/1788-prevent-flash-of-no-result
$query = iconv("UTF-8-MAC", "UTF-8", $query);

$w = new Workflows();

//
// Install spotify-app-miniplayer app if needed
// very first time use
//
if (!file_exists($w->home() . "/Spotify/spotify-app-miniplayer"))
{	
	exec("mkdir -p ~/Spotify");
	symlink($w->path() . "/spotify-app-miniplayer", $w->home() . "/Spotify/spotify-app-miniplayer");
}

//
// Create settings.db with default values if needed
//
if(!file_exists($w->data() . "/settings.db"))
{
	touch($w->data() . "/settings.db");
	
	$sql = 'sqlite3 "' . $w->data() . '/settings.db" ' . ' "create table settings (all_playlists boolean, is_spotifious_active boolean, is_alfred_playlist_active boolean, is_artworks_active boolean, is_displaymorefrom_active boolean, max_results int, alfred_playlist_uri text)"';
	exec($sql);
	
	$sql = 'sqlite3 "' . $w->data() . '/settings.db" ' . '"insert into settings values (0,1,1,0,1,15,\"\")"';
	exec($sql);
}

//
// Read settings from DB
//
$getSettings = "select * from settings";
$dbfile = $w->data() . "/settings.db";
exec("sqlite3 -separator '	' \"$dbfile\" \"$getSettings\"", $settings);

foreach($settings as $setting):

	$setting = explode("	",$setting);
	
	$all_playlists = $setting[0];
	$is_spotifious_active = $setting[1];
	$is_alfred_playlist_active = $setting[2];
	$is_artworks_active = $setting[3];
	$is_displaymorefrom_active = $setting[4];
	$max_results = $setting[5];
	$alfred_playlist_uri = $setting[6];
endforeach;


# thanks to http://www.alfredforum.com/topic/1788-prevent-flash-of-no-result
mb_internal_encoding("UTF-8");
if(mb_strlen($query) < 3 || 
	((substr_count( $query, '→' ) == 1) && (strpos("Settings→",$query) !== false))
)
{					
	if ( substr_count( $query, '→' ) == 0 )
	{
		// check for correct configuration
		if (file_exists($w->data() . "/library.db") && file_exists($w->home() . "/Spotify/spotify-app-miniplayer/manifest.json"))
		{
			if($all_playlists == true)
			{
				$w->result( '', '', "Search for music in all your playlists", "Begin typing to search (at least 3 characters)", './images/allplaylists.png', 'no', '' );
			}
			else
			{
				$w->result( '', '', "Search for music in your ★ playlist", "Begin typing to search (at least 3 characters)", './images/star.png', 'no', '' );
			}
			
			if($is_displaymorefrom_active == true)
			{	
				// get info on current song
				$command_output = exec("./track_info.sh");
		
				if(substr_count( $command_output, '→' ) > 0)
				{
					$results = explode('→', $command_output);
					$currentArtwork = getTrackOrAlbumArtwork($w,$is_artworks_active,$results[4],false);
					$currentArtistArtwork = getArtistArtwork($w,$is_artworks_active,$results[1],false);
					$w->result( '', '||||playpause||||', "$results[0]", "$results[2] by $results[1]", ($results[3] == 'playing') ? './images/pause.png' : './images/play.png', 'yes', '' );
					$w->result( '', '', "$results[1]", "More from this artist..", $currentArtistArtwork, 'no', $results[1] );
					$w->result( '', '', "$results[2]", "More from this album..", $currentArtwork, 'no', $results[2] );
				}
			}
			if ($is_alfred_playlist_active == true)
			{
				$w->result( '', '', "Alfred Playlist", "Control your Alfred Playlist", './images/alfred_playlist.png', 'no', 'Alfred Playlist→' );	
			}
			if (file_exists($w->data() . "/playlists.json"))
			{
				$w->result( '', '', "Playlists", "Browse by playlist", './images/playlists.png', 'no', 'Playlist→' );
			}
			$w->result( '', '', "Artists", "Browse by artist", './images/artists.png', 'no', 'Artist→' );
			$w->result( '', '', "Albums", "Browse by album", './images/albums.png', 'no', 'Album→' );			
		}
		else
		{
			if(!file_exists($w->data() . "/library.db"))
			{
				$w->result( '', '', "Workflow is not configured, library.db is missing", "Select Open Spotify Mini Player App below, and copy json data", './images/warning.png', 'no', '' );
			}
			elseif(!file_exists($w->home() . "/Spotify/spotify-app-miniplayer"))
			{
				$w->result( '', '', "Workflow is not configured, Spotify Mini Player App is missing", "Select Install library below, and make sure ~/Spotify/spotify-app-miniplayer directory exists", './images/warning.png', 'no', '' );				
			}
			$w->result( '', "|||||||" . "open_spotify_export_app|", "Open Spotify Mini Player App <spotify:app:miniplayer>", "Once clipboard contains json data, get back here and use Install library.", './images/app_miniplayer.png', 'yes', '' );
			$w->result( '', "|||||||" . "update_library_json|", "Install library", "Make sure the clipboard contains the json data from the Spotify App <spotify:app:miniplayer>", './images/update.png', 'yes', '' );
		}

		if ($is_spotifious_active == true)
		{
			$spotifious_state = 'enabled';
		}
		else
		{
			$spotifious_state = 'disabled';		
		}
		if ($is_alfred_playlist_active == true)
		{
			$alfred_playlist_state = 'enabled';
		}
		else
		{
			$alfred_playlist_state = 'disabled';		
		}
		if ($all_playlists == true)
		{
			$w->result( '', '', "Settings", "Search scope=<all>, Max results=<" . $max_results . ">, Spotifious is <" . $spotifious_state . ">, Alfred Playlist is <" . $alfred_playlist_state . ">", './images/settings.png', 'no', 'Settings→' );
		}
		else
		{
			$w->result( '', '', "Settings", "Search scope=<only ★>, Max results=<" . $max_results  . ">, Spotifious is <" . $spotifious_state . ">, Alfred Playlist is <" . $alfred_playlist_state . ">", './images/settings.png', 'no', 'Settings→' );
		}	
		
	}
	//
	// Settings
	//
	elseif ( substr_count( $query, '→' ) == 1 )
	{	
		if ($all_playlists == true)
		{
			// argument is csv form: track_uri|album_uri|artist_uri|playlist_uri|spotify_command|query|other_settings|other_action|alfred_playlist_uri|artist_name
			$w->result( '', "|||||||" . "disable_all_playlist||", "Change Search Scope", "Select to change to ★ playlist only", './images/search.png', 'yes', '' );
		}
		else
		{
			$w->result( '', "|||||||" . "enable_all_playlist||", "Change Search Scope", "Select to change to ALL playlists", './images/search.png', 'yes', '' );
		}
		$w->result( '', "|||||||" . "open_spotify_export_app||", "Open Spotify Mini Player App <spotify:app:miniplayer>", "Once clipboard contains json data, get back here and use Update library.", './images/app_miniplayer.png', 'yes', '' );
		$w->result( '', "|||||||" . "update_library_json||", "Update library", "Make sure the clipboard contains the json data from the Spotify Mini Player App <spotify:app:miniplayer>", './images/update.png', 'yes', '' );
		$w->result( '', '', "Configure Max Number of Results", "Number of results displayed", './images/numbers.png', 'no', 'Settings→MaxResults→' );
		$w->result( '', "|||||||" . "cache||", "Cache All Artworks", "This is recommended to do it before using the player", './images/cache.png', 'yes', '' );
		$w->result( '', "|||||||" . "clear||", "Clear Cached Artworks", "All cached artworks will be deleted", './images/uncheck.png', 'yes', '' );
		if ($is_spotifious_active == true)
		{
			$w->result( '', "|||||||" . "disable_spotifiuous||", "Disable Spotifious", "Do not display Spotifious in default results", './images/uncheck.png', 'yes', '' );
		}
		else
		{
			$w->result( '', "|||||||" . "enable_spotifiuous||", "Enable Spotifious", "Display Spotifious in default results", './images/check.png', 'yes', '' );
		}
		if ($is_alfred_playlist_active == true)
		{
			$w->result( '', "|||||||" . "disable_alfred_playlist||", "Disable Alfred Playlist", "Do not display Alfred Playlist", './images/uncheck.png', 'yes', '' );
		}
		else
		{
			$w->result( '', "|||||||" . "enable_alfred_playlist||", "Enable Alfred Playlist", "Display Alfred Playlist", './images/check.png', 'yes', '' );
		}
		if ($is_artworks_active == true)
		{
			$w->result( '', "|||||||" . "disable_artworks||", "Disable artworks", "Do not display artworks", './images/uncheck.png', 'yes', '' );
		}
		else
		{
			$w->result( '', "|||||||" . "enable_artworks||", "Enable artworks", "Display artworks", './images/check.png', 'yes', '' );
		}	
		if ($is_displaymorefrom_active == true)
		{
			$w->result( '', "|||||||" . "disable_displaymorefrom||", "Disable \"More from this artist/album\"", "Disable the option which displays more tracks from current artist/album", './images/uncheck.png', 'yes', '' );
		}
		else
		{
			$w->result( '', "|||||||" . "enable_displaymorefrom||", "Enable \"More from this artist/album\"", "Enable the option which displays more tracks from current artist/album", './images/check.png', 'yes', '' );
		}			
	}
} 
else 
{
	////////////
	//
	// NO DELIMITER
	//
	////////////	
	if ( substr_count( $query, '→' ) == 0 )
	{	
		//
		// Search categories for fast access
		//		
		if (strpos(strtolower("playlist"),strtolower($query)) !== false)
		{	
			$w->result( '', '', "Playlists", "Browse by playlist", './images/playlists.png', 'no', 'Playlist→' );
		}
		else if (strpos(strtolower("album"),strtolower($query)) !== false)
		{
			$w->result( '', '', "Albums", "Browse by album", './images/albums.png', 'no', 'Album→' );	
		}
		else if (strpos(strtolower("artist"),strtolower($query)) !== false)
		{
			$w->result( '', '', "Artists", "Browse by artist", './images/artists.png', 'no', 'Artist→' );	
		}
		else if (strpos(strtolower("alfred"),strtolower($query)) !== false)
		{
			$w->result( '', '', "Alfred Playlist", "Control your Alfred Playlist", './images/alfred_playlist.png', 'no', 'Alfred Playlist→' );	
		}
		else if (strpos(strtolower("setting"),strtolower($query)) !== false)
		{
			$w->result( '', '', "Settings", "Go to settings", './images/settings.png', 'no', 'Settings→' );
		}		
		
			
		
		//
		// Search in Playlists
		//
		if (file_exists($w->data() . "/playlists.json"))
		{		
			$json = file_get_contents($w->data() . "/playlists.json");
			$json = json_decode($json,true);
			
			foreach ($json as $key => $val) 
			{				
				if (strpos(strtolower($val),strtolower($query)) !== false &&
					$val != "Alfred Playlist" )
				{	
					$w->result( "spotify_mini-spotify-playlist-$val", '', ucfirst($val), "Browse Playlist", './images/playlists.png', 'no', "Playlist→" . $val . "→" );
				}
			};
		}

		//
		// Search everything
		//
		if($all_playlists == false)
		{
			$getTracks = "select * from tracks where starred=1 and (artist_name like '%".$query."%' or album_name like '%".$query."%' or track_name like '%".$query."%')"." limit ".$max_results;
		}
		else
		{
			$getTracks = "select * from tracks where artist_name like '%".$query."%' or album_name like '%".$query."%' or track_name like '%".$query."%'"." limit ".$max_results;
		}
		
		
		$dbfile = $w->data() . "/library.db";
		exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
	
		$currentResultNumber = 1;
		foreach($tracks as $track):
			if($currentResultNumber > $max_results)
				break;
			$track = explode("	",$track);
							
			$subtitle = $track[6] . "  ⌥ (play album) ⌘ (play artist)";
			if($is_alfred_playlist_active ==true)
			{
				$subtitle = "$subtitle fn (add track to ♫) ⇧ (add album to ♫)";
			}
			
			if(checkIfResultAlreadyThere($w->results(),ucfirst($track[7]) . " - " . $track[5]) == false)
			{					
				$w->result( "spotify_mini-spotify-track" . $track[2], $track[2] . "|" . $track[3] . "|" . $track[4] . "|||||"  . "|" . $alfred_playlist_uri . "|" . $track[7], ucfirst($track[7]) . " - " . $track[5], $subtitle, getTrackOrAlbumArtwork($w,$is_artworks_active,$track[2],true), 'yes', '' );
			}
			$currentResultNumber++;
		endforeach;
		
		$w->result( '', "||||activate (open location \"spotify:search:" . $query . "\")|||||", "Search for " . $query . " with Spotify", "This will start a new search in Spotify", 'fileicon:/Applications/Spotify.app', 'yes', '' );
		if($is_spotifious_active == true)
		{
			$w->result( '', "|||||" . "$query" . "||||", "Search for " . $query . " with Spotifious", "Spotifious workflow must be installed", './images/spotifious.png', 'yes', '' );
		}
	}
	////////////
	//
	// FIRST DELIMITER: Artist→, Album→, Playlist→, Alfred Playlist→, Settings→ or Online→artist uri
	//
	////////////
	elseif ( substr_count( $query, '→' ) == 1 )
	{		
		$words = explode('→', $query);
		
		$kind=$words[0];

		if($kind == "Playlist")
		{
			//
			// Search playlists
			//
			$playlist=$words[1];
			
			if(mb_strlen($playlist) < 3)
			{
				//
				// Display all playlists
				//
				$json = file_get_contents($w->data() . "/playlists.json");
				$json = json_decode($json,true);
				
				foreach ($json as $key => $val) 
				{	
					$r = explode(':', $key);
					$playlist_user = $r[2];
					$w->result( "spotify_mini-spotify-playlist-$val", '', ucfirst($val), "by " . $playlist_user, './images/playlists.png', 'no', "Playlist→" . $val . "→" );
				};
			}
			else
			{
				$json = file_get_contents($w->data() . "/playlists.json");
				$json = json_decode($json,true);
				
				foreach ($json as $key => $val) 
				{
					$r = explode(':', $key);
					$playlist_user = $r[2];
								
					if (strpos(strtolower($val),strtolower($playlist)) !== false ||
						strpos(strtolower($playlist_user),strtolower($playlist)) !== false )
					{	
						$w->result( "spotify_mini-spotify-playlist-$val", '', ucfirst($val), "by " . $playlist_user, './images/playlists.png', 'no', "Playlist→" . $val . "→" );
					}
				};
			}
		} // search by Playlist end	
		elseif($kind == "Alfred Playlist")
		{
			//
			// Alfred Playlist
			//
			$playlist=$words[1];
						
			if($alfred_playlist_uri == "")
			{
				$w->result( "spotify_mini-spotify-alfredplaylist-set", '', "Set your Alfred playlist URI", "define the URI of your Alfred playlist",'./images/alfred_playlist.png', 'no', 'Alfred Playlist→Set Alfred Playlist URI→');				
			}
			else
			{
				$w->result( "spotify_mini-spotify-alfredplaylist-browse", '', "Browse your Alfred playlist", "browse your alfred playlist",'./images/alfred_playlist.png', 'no', 'Playlist→Alfred Playlist→');
			
				$w->result( "spotify_mini-spotify-alfredplaylist-set", '', "Update your Alfred playlist URI", "define the URI of your Alfred playlist",'./images/settings.png', 'no', 'Alfred Playlist→Set Alfred Playlist URI→');
				
				$w->result( "spotify_mini-spotify-alfredplaylist-refresh", "||||||||" . "refresh_alfred_playlist|" . $alfred_playlist_uri, "Refresh your Alfred playlist", "this will refresh your Alfred playlist",'./images/update.png', 'yes', '');
				
				$w->result( "spotify_mini-spotify-alfredplaylist-clear", "||||||||" . "clear_alfred_playlist|" . $alfred_playlist_uri, "Clear your Alfred playlist", "this will clear your Alfred playlist",'./images/uncheck.png', 'yes', '');
			
			}
		} //  Alfred Playlist end	
		elseif($kind == "Artist")
		{
			//
			// Search artists
			//
			$artist=$words[1];
								
			
			if(mb_strlen($artist) < 3)
			{
				if($all_playlists == false)
				{
					$getTracks = "select * from tracks where starred=1"." limit ".$max_results;
				}
				else
				{
					$getTracks = "select * from tracks"." limit ".$max_results;
				}
				
				
				$dbfile = $w->data() . "/library.db";
				exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
				
				// display all artists
				$currentResultNumber = 1;
				foreach($tracks as $track):
					if($currentResultNumber > $max_results)
						break;
					$track = explode("	",$track);
					
					if(checkIfResultAlreadyThere($w->results(),ucfirst($track[7])) == false)
					{													
						$w->result( "spotify_mini-spotify-artist-" . $track[7], '', ucfirst($track[7]), "Get tracks from this artist", getArtistArtwork($w,$is_artworks_active,$track[7],true), 'no', "Artist→" . $track[7] . "→" );
						
						$currentResultNumber++;
					}
				endforeach;
			}
			else
			{
				if($all_playlists == false)
				{
					$getTracks = "select * from tracks where starred=1 and artist_name like '%".$artist."%'"." limit ".$max_results;
				}
				else
				{
					$getTracks = "select * from tracks where artist_name like '%".$artist."%'"." limit ".$max_results;
				}
				
				
				$dbfile = $w->data() . "/library.db";
				exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
				
				$currentResultNumber = 1;
				foreach($tracks as $track):
					if($currentResultNumber > $max_results)
						break;
					$track = explode("	",$track);
					
					if(checkIfResultAlreadyThere($w->results(),ucfirst($track[7])) == false)
					{									
						$w->result( "spotify_mini-spotify-artist-" . $track[7], '', ucfirst($track[7]), "Get tracks from this artist", getArtistArtwork($w,$is_artworks_active,$track[7],true), 'no', "Artist→" . $track[7] . "→" );
						
						$currentResultNumber++;
					}
				endforeach;
				
				$w->result( '', "||||activate (open location \"spotify:search:" . $artist . "\")|||||", "Search for " . $artist . " with Spotify", "This will start a new search in Spotify", 'fileicon:/Applications/Spotify.app', 'yes', '' );
				if($is_spotifious_active == true)
				{
					$w->result( '', "|||||" . $artist . "||||", "Search for " . $artist . " with Spotifious", "Spotifious workflow must be installed", './images/spotifious.png', 'yes', '' );
				}
			}
		} // search by Artist end
		elseif($kind == "Album")
		{		
			//
			// Search albums
			//
			$album=$words[1];
			
			if(mb_strlen($album) < 3)
			{
				if($all_playlists == false)
				{
					$getTracks = "select * from tracks where starred=1"." limit ".$max_results;
				}
				else
				{
					$getTracks = "select * from tracks"." limit ".$max_results;
				}
				
				
				$dbfile = $w->data() . "/library.db";
				exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
				
				// display all albums
				$currentResultNumber = 1;
				foreach($tracks as $track):
					if($currentResultNumber > $max_results)
						break;
					$track = explode("	",$track);
					
					if(checkIfResultAlreadyThere($w->results(),ucfirst($track[6])) == false)
					{						
						$w->result( "spotify_mini-spotify-album" . $track[6], '', ucfirst($track[6]), "by " . $track[7] . " (" . $track[8] . ")", getTrackOrAlbumArtwork($w,$is_artworks_active,$track[3],true), 'no', "Album→" . $track[6] . "→" );
						
						$currentResultNumber++;
					}
				endforeach;
			}
			else
			{
				if($all_playlists == false)
				{
					$getTracks = "select * from tracks where starred=1 and album_name like '%".$album."%'"." limit ".$max_results;
				}
				else
				{
					$getTracks = "select * from tracks where album_name like '%".$album."%'"." limit ".$max_results;
				}
				
				
				$dbfile = $w->data() . "/library.db";
				exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
				
				$currentResultNumber = 1;
				foreach($tracks as $track):
					if($currentResultNumber > $max_results)
						break;
					$track = explode("	",$track);
					
					if(checkIfResultAlreadyThere($w->results(),ucfirst($track[6])) == false)
					{								
						$w->result( "spotify_mini-spotify-album" . $track[6], '', ucfirst($track[6]), "by " . $track[7] . " (" . $track[8] . ")", getTrackOrAlbumArtwork($w,$is_artworks_active,$track[3],true), 'no', "Album→" . $track[6] . "→" );
						
						$currentResultNumber++;
					}
				endforeach;
				

				$w->result( '', "||||activate (open location \"spotify:search:" . $album . "\")|||||", "Search for " . $album . " with Spotify", "This will start a new search in Spotify", 'fileicon:/Applications/Spotify.app', 'yes', '' );
				if($is_spotifious_active == true)
				{
					$w->result( '', "||||||" . "$album" . "|||", "Search for " . $album . " with Spotifious", "Spotifious workflow must be installed", './images/spotifious.png', 'yes', '' );
				}
			}
		} // search by Album end		
		elseif($kind == "Online")
		{
			if( substr_count( $query, '@' ) == 1 )
			{
				//
				// Search Artist Online
				//
				$tmp=$words[1];
				$words = explode('@', $tmp);
				$artist_uri=$words[0];
				$artist_name=$words[1];
		
				$json = $w->request( "http://ws.spotify.com/lookup/1/.json?uri=$artist_uri&extras=albumdetail" );
				
				$json = json_decode($json);
				if (json_last_error() === JSON_ERROR_NONE && !empty($json))
				{
					foreach ($json->artist->albums as $key => $value)
					{
						$album = array();
						$album = $value->album;
						
						// only display albums from the artist
						if(strpos($album->artist,$artist_name) !== false )
						{
							$availability = array();
							$availability = $album->availability;
							
							if(empty($availability->territories)/* ||
								strpos($availability->territories,"FR") !== false */)
							{						
								if(checkIfResultAlreadyThere($w->results(),ucfirst($album->name)) == false)
								{	
									$w->result( "spotify_mini-spotify-online-album" . $album->name, '', ucfirst($album->name), "by " . $album->artist . " (" . $album->released . ")", getTrackOrAlbumArtwork($w,$is_artworks_active,$album->href,false), 'no', "Online→" . $artist_uri . "@" . $album->artist . "@" . $album->href . "@" . $album->name);
								}
							}
						}
					}			
				}
				else
				{
					$w->result( '', '', "There was an error when retrieving online information", "Please retry later", './images/warning.png', 'no', '' );
				}
			}
			elseif( substr_count( $query, '@' ) == 3 )
			{
				//
				// Search Album Online
				//
				$tmp=$words[1];			
				$words = explode('@', $tmp);
				$artist_uri=$words[0];
				$artist_name=$words[1];
				$album_uri=$words[2];
				$album_name=$words[3];

				$json = $w->request( "http://ws.spotify.com/lookup/1/.json?uri=$album_uri&extras=trackdetail" );

				$json = json_decode($json);
				if (json_last_error() === JSON_ERROR_NONE && !empty($json))
				{
					foreach ($json->album->tracks as $key => $value)
					{						
						$subtitle = $album_name . "  ⌥ (play album) ⌘ (play artist)";
						if($is_alfred_playlist_active ==true)
						{
							$subtitle = "$subtitle fn (add track to ♫) ⇧ (add album to ♫)";
						}
	
						if(checkIfResultAlreadyThere($w->results(),ucfirst($artist_name) . " - " . $value->name) == false)
						{	
							$w->result( "spotify_mini-spotify-online-track-" . $value->name, $value->href . "|" . $album_uri . "|" . $artist_uri . "|||||"  . "|" . $alfred_playlist_uri . "|" . $track[7], ucfirst($artist_name) . " - " . $value->name, $subtitle, getTrackOrAlbumArtwork($w,$is_artworks_active,$value->href,false), 'yes', '' );
						}
					}			
				}
				else
				{
					$w->result( '', '', "There was an error when retrieving online information", "Please retry later", './images/warning.png', 'no', '' );
				}
			}
			
		} // Online mode end
	}
	////////////
	//
	// SECOND DELIMITER: Artist→the_artist→tracks , Album→the_album→tracks, Playlist→the_playlist→tracks or Settings→MaxResults→max_numbers, Alfred Playlist→Set Alfred Playlist URI→alfred_playlist_uri
	//
	////////////
	elseif ( substr_count( $query, '→' ) == 2 )
	{		
		//
		// Get all songs for selected artist
		//
		
		$words = explode('→', $query);
		
		$kind=$words[0];
		if($kind == "Artist")
		{	
			//		
			// display tracks for selected artists
			//
			$artist=$words[1];
			$track=$words[2];
			
			if(mb_strlen($track) < 3)
			{
				if($all_playlists == false)
				{
					$getTracks = "select * from tracks where starred=1 and artist_name='".$artist."'"." limit ".$max_results;
				}
				else
				{
					$getTracks = "select * from tracks where artist_name='".$artist."'"." limit ".$max_results;
				}
				
				
				$dbfile = $w->data() . "/library.db";
				exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
				
				$currentResultNumber = 1;
				foreach($tracks as $track):
					if($currentResultNumber > $max_results)
						break;
					$track = explode("	",$track);
						
					$subtitle = $track[6] . "  ⌥ (play album) ⌘ (play artist)";
					if($is_alfred_playlist_active ==true)
					{
						$subtitle = "$subtitle fn (add track to ♫) ⇧ (add album to ♫)";
					}

					if(checkIfResultAlreadyThere($w->results(),ucfirst($track[7]) . " - " . $track[5]) == false)
					{	
						$w->result( "spotify_mini-spotify-track-" . $track[5], $track[2] . "|" . $track[3] . "|" . $track[4] . "|||||"  . "|" . $alfred_playlist_uri . "|" . $track[7], ucfirst($track[7]) . " - " . $track[5], $subtitle, getTrackOrAlbumArtwork($w,$is_artworks_active,$track[2],true), 'yes', '' );
					}
					if($artist_uri == "")
						$artist_uri = $track[4];
					$currentResultNumber++;
				endforeach;
				
				$w->result( '', "||||activate (open location \"spotify:search:" . $artist . "\")||||", "Search for " . $artist . " with Spotify", "This will start a new search in Spotify", 'fileicon:/Applications/Spotify.app', 'yes', '' );
				if($is_spotifious_active == true)
				{
					$w->result( '', "|||||" . $artist_uri . " ► " . $artist . " ►" . "||||", "Search for " . $artist . " with Spotifious", "Spotifious workflow must be installed", './images/spotifious.png', 'yes', '' );
				}
			}
			else
			{
				if($all_playlists == false)
				{
					$getTracks = "select * from tracks where starred=1 and (artist_name='".$artist."' and track_name like '%".$track."%')"." limit ".$max_results;
				}
				else
				{
					$getTracks = "select * from tracks where artist_name='".$artist."' and track_name like '%".$track."%'"." limit ".$max_results;
				}
				
				
				$dbfile = $w->data() . "/library.db";
				exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
				
				$currentResultNumber = 1;
				foreach($tracks as $track):
					if($currentResultNumber > $max_results)
						break;
					$track = explode("	",$track);
						
					$subtitle = $track[6] . "  ⌥ (play album) ⌘ (play artist)";
					if($is_alfred_playlist_active ==true)
					{
						$subtitle = "$subtitle fn (add track to ♫) ⇧ (add album to ♫)";
					}

					if(checkIfResultAlreadyThere($w->results(),ucfirst($track[7]) . " - " . $track[5]) == false)
					{								
						$w->result( "spotify_mini-spotify-track-" . $track[5], $track[2] . "|" . $track[3] . "|" . $track[4] . "|||||"  . "|" . $alfred_playlist_uri, ucfirst($track[7]) . " - " . $track[5], $subtitle, getTrackOrAlbumArtwork($w,$is_artworks_active,$track[2],true), 'yes', '' );
					}
					$currentResultNumber++;
				endforeach;

				$w->result( '', "||||activate (open location \"spotify:search:" . $track . "\")|||||", "Search for " . $track . " with Spotify", "This will start a new search in Spotify", 'fileicon:/Applications/Spotify.app', 'yes', '' );
				if($is_spotifious_active == true)
				{
					$w->result( '', "|||||" . "$track" . "||||", "Search for " . $track . " with Spotifious", "Spotifious workflow must be installed", './images/spotifious.png', 'yes', '' );
				}
			}
		}// end of tracks by artist
		elseif($kind == "Album")
		{
			//		
			// display tracks for selected album
			//
			$album=$words[1];
			$track=$words[2];
			
			if(mb_strlen($track) < 3)
			{
				$currentResultNumber = 1;
				$album_uri = "";
				
				if($all_playlists == false)
				{
					$getTracks = "select * from tracks where starred=1 and album_name='".$album."'"." limit ".$max_results;
				}
				else
				{
					$getTracks = "select * from tracks where album_name='".$album."'"." limit ".$max_results;
				}
				
				
				$dbfile = $w->data() . "/library.db";
				exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
				
				$currentResultNumber = 1;
				foreach($tracks as $track):
					if($currentResultNumber > $max_results)
						break;
					$track = explode("	",$track);
						
					$subtitle = $track[6] . "  ⌥ (play album) ⌘ (play artist)";
					if($is_alfred_playlist_active ==true)
					{
						$subtitle = "$subtitle fn (add track to ♫) ⇧ (add album to ♫)";
					}

					if(checkIfResultAlreadyThere($w->results(),ucfirst($track[7]) . " - " . $track[5]) == false)
					{	
						$w->result( "spotify_mini-spotify-track-" . $track[5], $track[2] . "|" . $track[3] . "|" . $track[4] . "|||||"  . "|" . $alfred_playlist_uri . "|" . $track[7], ucfirst($track[7]) . " - " . $track[5], $subtitle, getTrackOrAlbumArtwork($w,$is_artworks_active,$track[2],true), 'yes', '' );
					}
					if($album_uri == "")
						$album_uri = $track[3];
					$currentResultNumber++;
				endforeach;

				$w->result( '', "||||activate (open location \"spotify:search:" . $album . "\")|||||", "Search for " . $album . " with Spotify", "This will start a new search in Spotify", 'fileicon:/Applications/Spotify.app', 'yes', '' );
				if($is_spotifious_active == true)
				{
					$w->result( '', "||||||" . $album_uri . " ► " . $album . " ►" . "|||", "Search for " . $album . " with Spotifious", "Spotifious workflow must be installed", './images/spotifious.png', 'yes', '' );
				}
			}
			else
			{
				if($all_playlists == false)
				{
					$getTracks = "select * from tracks where starred=1 and (album_name='".$album."' and track_name like '%".$track."%')"." limit ".$max_results;
				}
				else
				{
					$getTracks = "select * from tracks where album_name='".$album."' and track_name like '%".$track."%'"." limit ".$max_results;
				}
				
				
				$dbfile = $w->data() . "/library.db";
				exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
				
				$currentResultNumber = 1;
				foreach($tracks as $track):
					if($currentResultNumber > $max_results)
						break;
					$track = explode("	",$track);
						
					$subtitle = $track[6] . "  ⌥ (play album) ⌘ (play artist)";
					if($is_alfred_playlist_active ==true)
					{
						$subtitle = "$subtitle fn (add track to ♫) ⇧ (add album to ♫)";
					}

					if(checkIfResultAlreadyThere($w->results(),ucfirst($track[7]) . " - " . $track[5]) == false)
					{	
						$w->result( "spotify_mini-spotify-track-" . $track[5], $track[2] . "|" . $track[3] . "|" . $track[4] . "|||||"  . "|" . $alfred_playlist_uri . "|" . $track[7], ucfirst($track[7]) . " - " . $track[5], $subtitle, getTrackOrAlbumArtwork($w,$is_artworks_active,$track[2],true), 'yes', '' );
					}
					$currentResultNumber++;
				endforeach;


				$w->result( '', "||||activate (open location \"spotify:search:" . $track . "\")|||||", "Search for " . $track . " with Spotify", "This will start a new search in Spotify", 'fileicon:/Applications/Spotify.app', 'yes', '' );
				if($is_spotifious_active == true)
				{
					$w->result( '', "||||||" . "$track" . "|||", "Search for " . $track . " with Spotifious", "Spotifious workflow must be installed", './images/spotifious.png', 'yes', '' );
				}
			}			
		}// end of tracks by album
		elseif($kind == "Playlist")
		{
			//		
			// display tracks for selected playlist
			//
			$playlist=$words[1];
			$thetrack=$words[2];
			
			// retrieve playlist uri from playlist name
			if(file_exists($w->data() . "/playlists.json"))
			{
				$json = file_get_contents($w->data() . "/playlists.json");
				$json = json_decode($json,true);
				
				foreach ($json as $key => $val) 
				{
					if (strpos(str_replace(")","\)",str_replace("(","\(",strtolower($val))),strtolower($playlist)) !== false)
					{
						$res = explode(':', $key);
						$playlist_name = $res[4];
						$playlist_user = $res[2];
						if($res[4])
						{
							$playlist_name = $res[4];
						}elseif ($res[3] == "starred")
						{
							$playlist_name = "starred";
						}
						break;
					}
				}
				
				if($playlist_name)
				{	
					$subtitle = "Launch Playlist";
					if($is_alfred_playlist_active ==true &&
						$val != "Alfred Playlist")
					{
						$subtitle = "$subtitle ,⇧ → add playlist to ♫";
					}
					$w->result( "spotify_mini-spotify-playlist-$val", "|||" . $key . "||||" . "||" . $alfred_playlist_uri, ucfirst($val) . " by " . $playlist_user, $subtitle, './images/playlists.png', 'yes', '' );
									
					if(mb_strlen($thetrack) < 3)
					{
						//
						// display all tracks from playlist
						//
						$getTracks = "select * from \"playlist_" . $playlist_name . "\""." limit ".$max_results;
						
						$dbfile = $w->data() . "/library.db";
						exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
						
						$currentResultNumber = 1;
						foreach($tracks as $track):
							if($currentResultNumber > $max_results)
								break;
							$track = explode("	",$track);	
		
							if(checkIfResultAlreadyThere($w->results(),ucfirst($track[7]) . " - " . $track[5]) == false)
							{	
								$w->result( "spotify_mini-spotify-playlist-track-" . $playlist_name . "-" .$track[5], $track[2] . "|" . $track[3] . "|" . $track[4] . "|||||"  . "|" . $alfred_playlist_uri . "|" . $track[7], ucfirst($track[7]) . " - " . $track[5], "Play track", getTrackOrAlbumArtwork($w,$is_artworks_active,$track[2],true), 'yes', '' );
							}
							$currentResultNumber++;
						endforeach;
					}
					else
					{
						$getTracks = "select * from \"playlist_" . $playlist_name . "\" where track_name like '%".$thetrack."%'"." limit ".$max_results;
						
						$dbfile = $w->data() . "/library.db";
						exec("sqlite3 -separator '	' \"$dbfile\" \"$getTracks\"", $tracks);
						
						$currentResultNumber = 1;
						foreach($tracks as $track):
							if($currentResultNumber > $max_results)
								break;
							$track = explode("	",$track);
															
							$subtitle = $track[6] . "  ⌥ (play album) ⌘ (play artist)";
							if($is_alfred_playlist_active ==true)
							{
								$subtitle = "$subtitle fn (add track to ♫) ⇧ (add album to ♫)";
							}
		
							if(checkIfResultAlreadyThere($w->results(),ucfirst($track[7]) . " - " . $track[5]) == false)
							{	
								$w->result( "spotify_mini-spotify-playlist-track-" . $playlist_name . "-" .$track[5], $track[2] . "|" . $track[3] . "|" . $track[4] . "|||||"  . "|" . $alfred_playlist_uri . "|" . $track[7], ucfirst($track[7]) . " - " . $track[5], $subtitle, getTrackOrAlbumArtwork($w,$is_artworks_active,$track[2],true), 'yes', '' );
							}
							$currentResultNumber++;
						endforeach;

						$w->result( '', "||||activate (open location \"spotify:search:" . $thetrack . "\")|||||", "Search for " . $thetrack . " with Spotify", "This will start a new search in Spotify", 'fileicon:/Applications/Spotify.app', 'yes', '' );
						if($is_spotifious_active == true)
						{
							$w->result( '', "|||||" . "$thetrack" . "||||", "Search for " . $thetrack . " with Spotifious", "Spotifious workflow must be installed", './images/spotifious.png', 'yes', '' );	
						}
					}									
				}				
				
			}			
		}// end of tracks by Playlist
		elseif($kind == "Settings")
		{
			$max_results=$words[2];
			
			if(mb_strlen($max_results) == 0)
			{					
				$w->result( '', '', "Enter the Max Results number (must be greater than 0):", "The number of results has impact on performances", './images/settings.png', 'no', '' );
			}
			else
			{
				// max results has been set
				if(is_numeric($max_results) == true && $max_results > 0)
				{
					$w->result( '', "||||||$max_results|||", "Max Results will be set to <" . $max_results . ">", "Type enter to validate the Max Results", './images/settings.png', 'yes', '' );
				}
				else
				{
					$w->result( '', '', "The Max Results value entered is not valid", "Please fix it", './images/warning.png', 'no', '' );

				}
			}			
		}// end of Settings
		elseif($kind == "Alfred Playlist")
		{
			$alfred_playlist_uri=$words[2];
			
			if(mb_strlen($alfred_playlist_uri) == 0)
			{					
				$w->result( '', '', "Enter the Alfred Spotify URI:", "Create the playlist in Spotify(shall be named <Alfred Playlist>, right click on it and select copy spotify URI", './images/settings.png', 'no', '' );
			}
			else
			{
				// alfred_playlist_uri has been set
				if(substr_count( $alfred_playlist_uri, ':' ) == 4)
				{
					$playlistName = getPlaylistName($alfred_playlist_uri);
					if($playlistName == "Alfred Playlist")
					{
						$w->result( '', "||||||$alfred_playlist_uri||", "Alfred Playlist URI will be set to <" . $alfred_playlist_uri . ">", "Type enter to validate", './images/settings.png', 'yes', '' );
					}
					else
					{
						$w->result( '', '', "The playlist name entered <" . $playlistName . "> is not valid", "shall be <Alfred Playlist>", './images/warning.png', 'no', '' );						
					}
				}
				else
				{
					$w->result( '', '', "The playlist URI entered is not valid", "format is spotify:user:myuser:playlist:20SZYrktr658JNa42Lt1vV", './images/warning.png', 'no', '' );

				}
			}			
		}// end of Settings
	}
}

echo $w->toxml();

?>