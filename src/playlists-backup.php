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
\define('SPOTIFY_API_SCOPES', [ 'playlist-modify-private', 'user-library-read' ]);
\define('SPOTIFY_URI_PLAYLIST_REGEX', '/^spotify:user:([^:]+):playlist:([^:]+)$/');
\define('SAVED_TRACKS_PSEUDO_PLAYLIST_URI', 'me:tracks');
\define('BACKUPS_DIRECTORY_PATH_RELATIVE', '../backups');
\define('BACKUPS_FILE_EXTENSION', 'csv');
\define('BACKUPS_TIMESTAMP', \date('Ymd\\THisO'));

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

		if (!empty($config['playlists']['backup'])) {
			$fileDirRelative = \BACKUPS_DIRECTORY_PATH_RELATIVE . '/' . \BACKUPS_TIMESTAMP;
			$fileDir = __DIR__ . '/' . $fileDirRelative;

			if (@\mkdir($fileDir)) {
				echo ' * Created target directory “' . $fileDirRelative . '” …' . "\n";
				echo ' * Processing ' . \count($config['playlists']['backup']) . ' playlists from configuration …' . "\n";

				foreach ($config['playlists']['backup'] as $backup) {
					if (isset($backup['which'])) {
						echo '   * Backing up “' . (!empty($backup['whichName']) ? $backup['whichName'] : $backup['which']) . '” …' . "\n";

						if (\preg_match(\SPOTIFY_URI_PLAYLIST_REGEX, $backup['which'], $backupWhich) || $backup['which'] === \SAVED_TRACKS_PSEUDO_PLAYLIST_URI) {
							if ($backup['which'] === \SAVED_TRACKS_PSEUDO_PLAYLIST_URI) {
								$backupWhich = [ null, null, null ];
							}

							$tracks = \SpotifyPlaylist::fetchTracks(
								$database['auth']['accessToken'],
								$backupWhich[1],
								$backupWhich[2]
							);

							if ($tracks !== null) {
								$rows = \array_map(
									function ($each) {
										return [
											!empty($each['track']['name']) ? $each['track']['name'] : '',
											!empty($each['track']['id']) ? $each['track']['id'] : '',
											!empty($each['track']['uri']) ? $each['track']['uri'] : '',
											!empty($each['track']['external_ids']['isrc']) ? $each['track']['external_ids']['isrc'] : '',
											!empty($each['track']['artists']) ? \implode(
												', ',
												\array_map(
													function ($artist) {
														return \str_replace(',', '\\,', $artist['name']);
													},
													$each['track']['artists']
												)
											) : '',
											!empty($each['track']['artists']) ? \implode(
												' ',
												\array_map(
													function ($artist) {
														return \str_replace(' ', '\\ ', $artist['id']);
													},
													$each['track']['artists']
												)
											) : '',
											!empty($each['track']['artists']) ? \implode(
												' ',
												\array_map(
													function ($artist) {
														return \str_replace(' ', '\\ ', $artist['uri']);
													},
													$each['track']['artists']
												)
											) : '',
											!empty($each['track']['album']['name']) ? $each['track']['album']['name'] : '',
											!empty($each['track']['album']['id']) ? $each['track']['album']['id'] : '',
											!empty($each['track']['album']['uri']) ? $each['track']['album']['uri'] : '',
											!empty($each['track']['album']['album_type']) ? \ucfirst($each['track']['album']['album_type']) : '',
											!empty($each['track']['album']['release_date']) ? $each['track']['album']['release_date'] : '',
											!empty($each['track']['disc_number']) ? $each['track']['disc_number'] : '',
											!empty($each['track']['track_number']) ? $each['track']['track_number'] : '',
											!empty($each['track']['duration_ms']) ? $each['track']['duration_ms'] : '',
											!empty($each['added_by']['uri']) ? $each['added_by']['uri'] : '',
											!empty($each['added_at']) ? $each['added_at'] : ''
										];
									},
									$tracks
								);

								\array_unshift(
									$rows,
									[
										'Title',
										'ID',
										'URI',
										'ISRC',
										'Artist Name',
										'Artist ID',
										'Artist URI',
										'Album Name',
										'Album ID',
										'Album URI',
										'Album Type',
										'Release Date',
										'Disc Number',
										'Track Number',
										'Duration [ms]',
										'Added By',
										'Added At'
									]
								);

								$csv = '';

								foreach ($rows as $row) {
									foreach (\array_keys($row) as $columnIndex) {
										$row[$columnIndex] = '"' . \str_replace('"', '""', $row[$columnIndex]) . '"';
									}

									$csv .= \implode(',', $row);
									$csv .= "\n";
								}

								$filename = !empty($backup['whichName']) ? $backup['whichName'] : $backup['which'];
								$filePath = $fileDir . '/' . $filename . '.' . \BACKUPS_FILE_EXTENSION;

								$backedUpSuccessfully = @\file_put_contents($filePath, $csv);

								if ($backedUpSuccessfully !== false) {
									echo '     * Stored ' . \count($tracks) . ' tracks …' . "\n";
								}
								else {
									echo '     * Could not store tracks …' . "\n";
									echo '     * Skipping …' . "\n";
								}
							}
							else {
								echo '     * Could not fetch tracks from playlist …' . "\n";
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
				echo ' * Could not create target directory …' . "\n";
				echo 'Failed' . "\n";
				exit(14);
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
