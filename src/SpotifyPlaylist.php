<?php

/*
 * Tools for Spotify (https://github.com/delight-im/Tools-for-Spotify)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

require_once __DIR__ . '/Http.php';

final class SpotifyPlaylist {

	/**
	 * Fetches a list of track URIs from the specified playlist
	 *
	 * @param string $accessToken the “Access Token” for access to the API
	 * @param string $ownerName the name of the playlist's owner
	 * @param string $id the ID of the playlist
	 * @param int|null $offset (optional) the offset within the playlist
	 * @param array|null $whereYearIn (optional) a list of years to filter by
	 * @param array|null $whereAnyArtistIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAnyArtistNotIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAllArtistsIn (optional) a list of artist names or IDs to filter by
	 * @param array|null $whereAllArtistsNotIn (optional) a list of artist names or IDs to filter by
	 * @return array|null the list of URIs or `null`
	 */
	public static function fetchTrackUris($accessToken, $ownerName, $id, $offset = null, $whereYearIn = null, $whereAnyArtistIn = null, $whereAnyArtistNotIn = null, $whereAllArtistsIn = null, $whereAllArtistsNotIn = null) {
		$offset = isset($offset) ? (int) $offset : 0;

		if (isset($ownerName) && isset($id)) {
			$trackFields = 'uri';

			if (isset($whereYearIn)) {
				$trackFields .= ',album(release_date)';
			}

			if (isset($whereAnyArtistIn) || isset($whereAnyArtistNotIn) || isset($whereAllArtistsIn) || isset($whereAllArtistsNotIn)) {
				$trackFields .= ',artists';
			}

			$apiUrl = 'https://api.spotify.com/v1/users/' . \urlencode($ownerName) . '/playlists/' . \urlencode($id) . '/tracks?offset=' . $offset . '&limit=100&fields=items(track(' . $trackFields . ')),offset,limit,total';
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

				$trackUris = \array_map(function ($each) {
					return $each['track']['uri'];
				}, $tracks);

				if (($offset + $limit) < $total) {
					$remainingTrackUris = self::fetchTrackUris($accessToken, $ownerName, $id, $offset + $limit, $whereYearIn, $whereAnyArtistIn, $whereAnyArtistNotIn, $whereAllArtistsIn, $whereAllArtistsNotIn);

					if ($remainingTrackUris === null) {
						return null;
					}

					$trackUris = \array_merge(
						$trackUris,
						$remainingTrackUris
					);
				}

				return $trackUris;
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

}
