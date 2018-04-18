# Tools for Spotify

Tools and utilities for Spotify using the Spotify Web API

## Requirements

 * PHP 7.0.0+

## Installation

 1. Register your own application for the Spotify Web API

    1. Go to [Spotify’s developer site](https://developer.spotify.com/my-applications)

    1. Choose to create a new app

    1. Enter an arbitrary title and description for your new app

    1. Locate the “Client ID” for your new app and write it down

    1. “Edit” your app

    1. As a “Redirect URI”, add

       ```
       http://localhost/Tools-for-Spotify/src/playlists-sync-one-way.php
       ```

       using whatever may be the URL to your (local) version of this project

    1. Locate the “Client Secret” for your app and write it down

    1. Save the new settings

 1. Create an empty configuration file

    ```bash
    $ touch data/config.json
    # sudo chown www-data:www-data data/config.json
    # sudo chmod 0400 data/config.json
    ```

    Add your “Client ID” and “Client Secret” for the Spotify Web API, e.g.:

    ```json
    {
        "api": {
            "clientId": "abcdefghijklmnopqrstuvwxyz012345",
            "clientSecret": "bcdefghijklmnopqrstuvwxyz0123456"
        }
    }
    ```

 1. Create an empty database file

    ```bash
    $ touch data/database.json
    # sudo chown www-data:www-data data/database.json
    # sudo chmod 0644 data/database.json
    ```

## Usage

### One-way synchronization between playlists

 1. Define your desired sources and targets in `data/config.json`, e.g. like this:

    ```json
    {
        "playlists": {
            "sync": {
                "oneWay": [
                    {
                        "from": "spotify:user:spotify:playlist:abcdefghijklmnopqrstuv",
                        "to": "spotify:user:bcdefghijklmnopqrstuvw:playlist:cdefghijklmnopqrstuvwx"
                    },
                    {
                        "from": "spotify:user:spotify:playlist:defghijklmnopqrstuvwxy",
                        "to": "spotify:user:bcdefghijklmnopqrstuvw:playlist:efghijklmnopqrstuvwxyz"
                    }
                ]
            }
        }
    }
    ```

 1. Navigate your browser to

    ```
    http://localhost/Tools-for-Spotify/src/playlists-sync-one-way.php
    ```

## References

 * [Spotify Web API](https://developer.spotify.com/web-api/)
   * [Get a playlist’s tracks](https://developer.spotify.com/web-api/get-playlists-tracks/)
   * [Add tracks to a playlist](https://developer.spotify.com/web-api/add-tracks-to-playlist/)
   * [Scopes (permissions)](https://developer.spotify.com/web-api/using-scopes/)

## Contributing

All contributions are welcome! If you wish to contribute, please create an issue first so that your feature, problem or question can be discussed.

## License

This project is licensed under the terms of the [MIT License](https://opensource.org/licenses/MIT).