<?php

/*
 * Tools for Spotify (https://github.com/delight-im/Tools-for-Spotify)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

\error_reporting(\E_ALL);
\ini_set('display_errors', 'stdout');

\ini_set('memory_limit', '128M');
\set_time_limit(0);

\header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/Spotify.php';
require_once __DIR__ . '/SpotifyPlaylist.php';
require_once __DIR__ . '/Storage.php';

\define('CONFIG_PATH_RELATIVE', '../data/config.json');
\define('DATABASE_PATH_RELATIVE', '../data/database.json');
\define('SPOTIFY_API_SCOPES', [ 'playlist-modify-public', 'playlist-modify-private', 'user-library-read', 'user-library-modify' ]);
\define('SPOTIFY_URI_PLAYLIST_REGEX', '/^spotify:user:([^:]+):playlist:([^:]+)$/');
\define('SAVED_TRACKS_PSEUDO_PLAYLIST_URI', 'me:tracks');

$config = \Storage::readConfiguration(\CONFIG_PATH_RELATIVE);
$database = \Storage::readDatabase(\DATABASE_PATH_RELATIVE);

if (isset($_GET['code'])) {
	echo 'Starting …' . "\n";

	$requestStartTime = \time();
	$responseJson = \Spotify::fetchAccessToken($config['api']['clientId'], $config['api']['clientSecret'], $_GET['code']);

	if ($responseJson === false) {
		echo ' * Could not get an access token from the Spotify API …' . "\n";
		echo ' * Cancelling …' . "\n";
		echo 'Failed' . "\n";
		exit(2);
	}
	else {
		$response = \json_decode($responseJson, true);

		$database['auth']['accessToken'] = (string) $response['access_token'];
		$database['auth']['expiresAt'] = $requestStartTime + (int) $response['expires_in'];
		$database['auth']['refreshToken'] = (string) $response['refresh_token'];

		if (!empty($config['playlists']['deduplicate'])) {
			echo ' * Processing ' . \count($config['playlists']['deduplicate']) . ' playlists from configuration …' . "\n";

			foreach ($config['playlists']['deduplicate'] as $deduplicate) {
				if (isset($deduplicate['which'])) {
					echo '   * Deduplicating “' . (!empty($deduplicate['whichName']) ? $deduplicate['whichName'] : $deduplicate['which']) . '” …' . "\n";

					if (\preg_match(\SPOTIFY_URI_PLAYLIST_REGEX, $deduplicate['which'], $deduplicateWhich) || $deduplicate['which'] === \SAVED_TRACKS_PSEUDO_PLAYLIST_URI) {
						if ($deduplicate['which'] === \SAVED_TRACKS_PSEUDO_PLAYLIST_URI) {
							$deduplicateWhich = [ null, null, null ];
						}

						$numberOfTracksRemoved = \SpotifyPlaylist::deduplicate(
							$database['auth']['accessToken'],
							$deduplicateWhich[1],
							$deduplicateWhich[2]
						);

						if ($numberOfTracksRemoved !== null) {
							echo '     * Removed ' . $numberOfTracksRemoved . ' tracks …' . "\n";
						}
						else {
							echo '     * Could not deduplicate playlist …' . "\n";
							echo '     * Skipping …' . "\n";
						}
					}
					else {
						echo '     * Invalid “which” URI …' . "\n";
						echo '     * Skipping …' . "\n";
					}
				}
				else {
					echo '   * Skipping invalid playlist entry …' . "\n";
				}
			}

			if (\Storage::writeDatabase(\DATABASE_PATH_RELATIVE, $database)) {
				echo 'Succeeded' . "\n";
			}
			else {
				echo ' * Could not update information in database …' . "\n";
				echo 'Failed' . "\n";
				exit(10);
			}
		}
		else {
			echo ' * No playlists found in configuration …' . "\n";
			echo 'Failed' . "\n";
			exit(13);
		}
	}
}
elseif (isset($_GET['error'])) {
	echo 'Starting …' . "\n";
	echo ' * You have denied the authorization request with the Spotify API …' . "\n";
	echo ' * Cancelling …' . "\n";
	echo 'Failed' . "\n";
	exit(1);
}
else {
	\Spotify::fetchAuthorizationCode($config['api']['clientId'], \SPOTIFY_API_SCOPES);
}
