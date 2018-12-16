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

\define('READ_ONLY_MODE', false);
\define('CONFIG_PATH_RELATIVE', '../data/config.json');
\define('DATABASE_PATH_RELATIVE', '../data/database.json');
\define('SPOTIFY_API_SCOPES', [ 'playlist-modify-public', 'playlist-modify-private', 'user-library-read' ]);
\define('SPOTIFY_URI_PLAYLIST_REGEX', '/^spotify:user:([^:]+):playlist:([^:]+)$/');
\define('SAVED_TRACKS_PSEUDO_PLAYLIST_URI', 'me:tracks');

$config = \Storage::readConfiguration(\CONFIG_PATH_RELATIVE);
$database = \Storage::readDatabase(\DATABASE_PATH_RELATIVE);

if (isset($_GET['code'])) {
	echo 'Starting ...' . "\n";

	$requestStartTime = \time();
	$responseJson = \Spotify::fetchAccessToken($config['api']['clientId'], $config['api']['clientSecret'], $_GET['code']);

	if ($responseJson === false) {
		echo ' * Could not get an access token from the Spotify API ...' . "\n";
		echo ' * Cancelling ...' . "\n";
		echo 'Failed' . "\n";
		exit(2);
	}
	else {
		$response = \json_decode($responseJson, true);

		$database['auth']['accessToken'] = (string) $response['access_token'];
		$database['auth']['expiresAt'] = $requestStartTime + (int) $response['expires_in'];
		$database['auth']['refreshToken'] = (string) $response['refresh_token'];

		echo ' * Processing ' . \count($config['playlists']['sync']['oneWay']) . ' playlists from configuration' . "\n";

		foreach ($config['playlists']['sync']['oneWay'] as $oneWaySync) {
			if (isset($oneWaySync['from']) && isset($oneWaySync['to'])) {
				echo '   * From “' . (!empty($oneWaySync['fromName']) ? $oneWaySync['fromName'] : $oneWaySync['from']) . '” to “' . (!empty($oneWaySync['toName']) ? $oneWaySync['toName'] : $oneWaySync['to']) . '” ...' . "\n";

				$whereYearIn = (isset($oneWaySync['whereYearIn']) && \is_array($oneWaySync['whereYearIn']) && !empty($oneWaySync['whereYearIn'])) ? $oneWaySync['whereYearIn'] : null;

				if (\preg_match(\SPOTIFY_URI_PLAYLIST_REGEX, $oneWaySync['from'], $oneWaySyncFrom) || $oneWaySync['from'] === \SAVED_TRACKS_PSEUDO_PLAYLIST_URI) {
					if ($oneWaySync['from'] === \SAVED_TRACKS_PSEUDO_PLAYLIST_URI) {
						$oneWaySyncFrom = [ null, null, null ];
					}

					if (\preg_match(\SPOTIFY_URI_PLAYLIST_REGEX, $oneWaySync['to'], $oneWaySyncTo)) {
						$trackUris = \SpotifyPlaylist::fetchTrackUris(
							$database['auth']['accessToken'],
							$oneWaySyncFrom[1],
							$oneWaySyncFrom[2],
							null,
							$whereYearIn
						);

						if ($trackUris !== null) {
							if (!isset($database['playlists'][$oneWaySync['to']]) || !\is_array($database['playlists'][$oneWaySync['to']])) {
								$database['playlists'][$oneWaySync['to']] = [];
							}

							if (!isset($database['playlists'][$oneWaySync['to']]['inserted']) || !\is_array($database['playlists'][$oneWaySync['to']]['inserted'])) {
								$database['playlists'][$oneWaySync['to']]['inserted'] = [];
							}

							$trackUris = \array_filter($trackUris, function ($each) use ($database, $oneWaySync) {
								return !\in_array($each, $database['playlists'][$oneWaySync['to']]['inserted'], true);
							});
							$trackUris = \array_values($trackUris);

							if (!empty($trackUris)) {
								$tracksSavedSuccessfully = \READ_ONLY_MODE || \SpotifyPlaylist::saveTrackUris(
									$database['auth']['accessToken'],
									$oneWaySyncTo[1],
									$oneWaySyncTo[2],
									$trackUris
								);

								if ($tracksSavedSuccessfully) {
									$database['playlists'][$oneWaySync['to']]['inserted'] = \array_merge(
										$database['playlists'][$oneWaySync['to']]['inserted'],
										$trackUris
									);

									echo '     * Added ' . \count($trackUris) . ' tracks to playlist ...' . "\n";
								}
								else {
									echo '     * Could not save tracks to playlist ...' . "\n";
									echo '     * Skipping ...' . "\n";
								}
							}
							else {
								echo '     * Already up to date ...' . "\n";
							}
						}
						else {
							echo '     * Could not fetch tracks from playlist ...' . "\n";
							echo '     * Skipping ...' . "\n";
						}
					}
					else {
						echo '     * Invalid “to” URI ...' . "\n";
						echo '     * Skipping ...' . "\n";
					}
				}
				else {
					echo '     * Invalid “from” URI ...' . "\n";
					echo '     * Skipping ...' . "\n";
				}
			}
			else {
				echo '   * Skipping invalid playlist entry ...' . "\n";
			}
		}

		if (\READ_ONLY_MODE || \Storage::writeDatabase(\DATABASE_PATH_RELATIVE, $database)) {
			echo 'Succeeded' . "\n";
		}
		else {
			echo ' * Could not update information in database ...' . "\n";
			echo 'Failed' . "\n";
			exit(10);
		}
	}
}
elseif (isset($_GET['error'])) {
	echo 'Starting ...' . "\n";
	echo ' * You have denied the authorization request with the Spotify API' . "\n";
	echo ' * Cancelling ...' . "\n";
	echo 'Failed' . "\n";
	exit(1);
}
else {
	\Spotify::fetchAuthorizationCode($config['api']['clientId'], \SPOTIFY_API_SCOPES);
}
