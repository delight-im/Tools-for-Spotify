<?php

/*
 * Tools for Spotify (https://github.com/delight-im/Tools-for-Spotify)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

require_once __DIR__ . '/Http.php';

final class SpotifyPlaylist {

	/**
	 * Fetches a list of tracks from the specified playlist
	 *
	 * @param string $accessToken the “Access Token” for access to the API
	 * @param string $ownerName the name of the playlist's owner
	 * @param string $id the ID of the playlist
	 * @param array|null $whereYearIn (optional) a list of years to filter by
	 * @param array|null $whereAnyArtistIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAnyArtistNotIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAllArtistsIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAllArtistsNotIn (optional) a list of artist names or IDs to filter by
	 * @return array|null the list of tracks or `null`
	 */
	public static function fetchTracks($accessToken, $ownerName, $id, $whereYearIn = null, $whereAnyArtistIn = null, $whereAnyArtistNotIn = null, $whereAllArtistsIn = null, $whereAllArtistsNotIn = null) {
		return self::fetchTracksInternal($accessToken, $ownerName, $id, $whereYearIn, $whereAnyArtistIn, $whereAnyArtistNotIn, $whereAllArtistsIn, $whereAllArtistsNotIn, false);
	}

	/**
	 * Fetches a list of track URIs from the specified playlist
	 *
	 * @param string $accessToken the “Access Token” for access to the API
	 * @param string $ownerName the name of the playlist's owner
	 * @param string $id the ID of the playlist
	 * @param array|null $whereYearIn (optional) a list of years to filter by
	 * @param array|null $whereAnyArtistIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAnyArtistNotIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAllArtistsIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAllArtistsNotIn (optional) a list of artist names or IDs to filter by
	 * @return array|null the list of track URIs or `null`
	 */
	public static function fetchTrackUris($accessToken, $ownerName, $id, $whereYearIn = null, $whereAnyArtistIn = null, $whereAnyArtistNotIn = null, $whereAllArtistsIn = null, $whereAllArtistsNotIn = null) {
		return self::fetchTracksInternal($accessToken, $ownerName, $id, $whereYearIn, $whereAnyArtistIn, $whereAnyArtistNotIn, $whereAllArtistsIn, $whereAllArtistsNotIn, true);
	}

	/**
	 * Saves a list of track URIs to the specified playlist
	 *
	 * @param string $accessToken the “Access Token” for access to the API
	 * @param string $ownerName the name of the playlist's owner
	 * @param string $id the ID of the playlist
	 * @param array $uris the list of URIs
	 * @param int|null $offset (optional) the offset within the list of URIs
	 * @return bool whether the tracks could be saved to the playlist
	 */
	public static function saveTrackUris($accessToken, $ownerName, $id, array $uris, $offset = null) {
		$offset = isset($offset) ? (int) $offset : 0;
		$limit = 100;

		$responseJson = \Http::makeRequest(
			'POST',
			'https://api.spotify.com/v1/users/' . \urlencode($ownerName) . '/playlists/' . \urlencode($id) . '/tracks',
			[
				'Authorization: Bearer ' . $accessToken,
				'Content-Type: application/json'
			],
			\json_encode([ 'uris' => \array_slice($uris, $offset, $limit) ])
		);

		if ($responseJson !== false) {
			$response = \json_decode($responseJson, true);
			$success = $response !== false && isset($response['snapshot_id']);

			if ($success) {
				if (($offset + $limit) < \count($uris)) {
					$success = $success && self::saveTrackUris($accessToken, $ownerName, $id, $uris, $offset + $limit);
				}
			}

			return $success;
		}
		else {
			return false;
		}
	}

	/**
	 * Fetches a list of tracks from the specified playlist
	 *
	 * @param string $accessToken the “Access Token” for access to the API
	 * @param string $ownerName the name of the playlist's owner
	 * @param string $id the ID of the playlist
	 * @param array|null $whereYearIn (optional) a list of years to filter by
	 * @param array|null $whereAnyArtistIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAnyArtistNotIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAllArtistsIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAllArtistsNotIn (optional) a list of artist names or IDs to filter by
	 * @param bool|null $idsOnly (optional) whether to return scalar IDs only instead of extended records
	 * @param int|null $offset (optional) the offset within the playlist
	 * @return array|null the list of tracks or `null`
	 */
	private static function fetchTracksInternal($accessToken, $ownerName, $id, $whereYearIn = null, $whereAnyArtistIn = null, $whereAnyArtistNotIn = null, $whereAllArtistsIn = null, $whereAllArtistsNotIn = null, $idsOnly = null, $offset = null) {
		$offset = isset($offset) ? (int) $offset : 0;

		if (isset($ownerName) && isset($id)) {
			if ($idsOnly) {
				$itemFields = '';
				$trackFields = 'uri';

				if (isset($whereYearIn)) {
					$trackFields .= ',album(release_date)';
				}

				if (isset($whereAnyArtistIn) || isset($whereAnyArtistNotIn) || isset($whereAllArtistsIn) || isset($whereAllArtistsNotIn)) {
					$trackFields .= ',artists';
				}
			}
			else {
				$itemFields = 'added_at,added_by(uri),';
				$trackFields = 'album(album_type,id,name,release_date,uri),artists(id,name,uri),disc_number,duration_ms,external_ids,id,name,track_number,uri';
			}

			$apiUrl = 'https://api.spotify.com/v1/users/' . \urlencode($ownerName) . '/playlists/' . \urlencode($id) . '/tracks?offset=' . $offset . '&limit=100&fields=items(' . $itemFields . 'track(' . $trackFields . ')),offset,limit,total';
		}
		else {
			$apiUrl = 'https://api.spotify.com/v1/me/tracks?offset=' . $offset . '&limit=50';
		}

		$responseJson = \Http::makeCacheableRequest(
			'GET',
			$apiUrl,
			[
				'Authorization: Bearer ' . $accessToken
			]
		);

		if ($responseJson !== false) {
			$response = \json_decode($responseJson, true);

			if ($response !== false && isset($response['items'])) {
				$offset = isset($response['offset']) ? (int) $response['offset'] : null;
				$limit = isset($response['limit']) ? (int) $response['limit'] : null;
				$total = isset($response['total']) ? (int) $response['total'] : null;

				$tracks = $response['items'];

				if (isset($whereYearIn)) {
					$tracks = \array_filter($tracks, function ($each) use ($whereYearIn) {
						$releaseYear = isset($each['track']['album']['release_date']) ? (int) \substr($each['track']['album']['release_date'], 0, 4) : null;

						return \in_array($releaseYear, $whereYearIn, true);
					});
				}

				if (isset($whereAnyArtistIn)) {
					$tracks = \array_filter($tracks, function ($each) use ($whereAnyArtistIn) {
						if (isset($each['track']['artists']) && \is_array($each['track']['artists']) && !empty($each['track']['artists'])) {
							foreach ($each['track']['artists'] as $artist) {
								$matches = (isset($artist['id']) && \in_array($artist['id'], $whereAnyArtistIn, true)) || (isset($artist['name']) && \in_array($artist['name'], $whereAnyArtistIn, true));

								if ($matches) {
									return true;
								}
							}
						}

						return false;
					});
				}

				if (isset($whereAnyArtistNotIn)) {
					$tracks = \array_filter($tracks, function ($each) use ($whereAnyArtistNotIn) {
						if (isset($each['track']['artists']) && \is_array($each['track']['artists']) && !empty($each['track']['artists'])) {
							foreach ($each['track']['artists'] as $artist) {
								$matches = (isset($artist['id']) && \in_array($artist['id'], $whereAnyArtistNotIn, true)) || (isset($artist['name']) && \in_array($artist['name'], $whereAnyArtistNotIn, true));

								if (!$matches) {
									return true;
								}
							}
						}

						return false;
					});
				}

				if (isset($whereAllArtistsIn)) {
					$tracks = \array_filter($tracks, function ($each) use ($whereAllArtistsIn) {
						if (isset($each['track']['artists']) && \is_array($each['track']['artists']) && !empty($each['track']['artists'])) {
							foreach ($each['track']['artists'] as $artist) {
								$matches = (isset($artist['id']) && \in_array($artist['id'], $whereAllArtistsIn, true)) || (isset($artist['name']) && \in_array($artist['name'], $whereAllArtistsIn, true));

								if (!$matches) {
									return false;
								}
							}
						}

						return true;
					});
				}

				if (isset($whereAllArtistsNotIn)) {
					$tracks = \array_filter($tracks, function ($each) use ($whereAllArtistsNotIn) {
						if (isset($each['track']['artists']) && \is_array($each['track']['artists']) && !empty($each['track']['artists'])) {
							foreach ($each['track']['artists'] as $artist) {
								$matches = (isset($artist['id']) && \in_array($artist['id'], $whereAllArtistsNotIn, true)) || (isset($artist['name']) && \in_array($artist['name'], $whereAllArtistsNotIn, true));

								if ($matches) {
									return false;
								}
							}
						}

						return true;
					});
				}

				if ($idsOnly) {
					$tracks = \array_map(function ($each) {
						return $each['track']['uri'];
					}, $tracks);
				}

				if (($offset + $limit) < $total) {
					$remainingTracks = self::fetchTracksInternal($accessToken, $ownerName, $id, $whereYearIn, $whereAnyArtistIn, $whereAnyArtistNotIn, $whereAllArtistsIn, $whereAllArtistsNotIn, $idsOnly, $offset + $limit);

					if ($remainingTracks === null) {
						return null;
					}

					$tracks = \array_merge(
						$tracks,
						$remainingTracks
					);
				}

				return $tracks;
			}
			else {
				return null;
			}
		}
		else {
			return null;
		}
	}

	/**
	 * Deletes a list of tracks from the specified playlist
	 *
	 * Each track must have the three properties `id`, `uri` and `position`
	 *
	 * @param string $accessToken the “Access Token” for access to the API
	 * @param string $ownerName the name of the playlist's owner
	 * @param string $id the ID of the playlist
	 * @param array $tracks the list of tracks to remove
	 * @param int|null $offset (optional) the offset within the list of tracks
	 * @return bool whether the tracks could be deleted from the playlist
	 */
	private static function deleteTracks($accessToken, $ownerName, $id, array $tracks, $offset = null) {
		$offset = isset($offset) ? (int) $offset : 0;

		if (isset($ownerName) && isset($id)) {
			$limit = 100;

			\usort($tracks, function ($a, $b) {
				return ($a['position'] === $b['position'] ? 0 : ($a['position'] < $b['position'] ? -1 : 1));
			});
		}
		else {
			$limit = 50;
		}

		$batch = \array_slice($tracks, $offset, $limit);

		if (isset($ownerName) && isset($id)) {
			$batch = \array_map(function ($each) use ($offset) {
				unset($each['id']);

				$each['positions'] = [
					$each['position'] - $offset
				];

				unset($each['position']);

				return $each;
			}, $batch);

			$apiUrl = 'https://api.spotify.com/v1/users/' . \urlencode($ownerName) . '/playlists/' . \urlencode($id) . '/tracks';
			$requestBody = [ 'tracks' => $batch ];
		}
		else {
			$batch = \array_map(function ($each) {
				return $each['id'];
			}, $batch);

			$apiUrl = 'https://api.spotify.com/v1/me/tracks';
			$requestBody = $batch;
		}

		$responseJson = \Http::makeRequest(
			'DELETE',
			$apiUrl,
			[
				'Authorization: Bearer ' . $accessToken,
				'Content-Type: application/json'
			],
			\json_encode($requestBody)
		);

		if ($responseJson !== false) {
			if (($offset + $limit) < \count($tracks)) {
				return self::deleteTracks($accessToken, $ownerName, $id, $tracks, $offset + $limit);
			}
			else {
				return true;
			}
		}
		else {
			return false;
		}
	}

}
