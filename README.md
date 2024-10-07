# Admin group manager

Manage routines associates to admin groups

Available features:
- Create admin group setting the default group quota and enabled apps

## Setup

- Clone this repo at your app folder
- Run the follow commands:
	```bash
	composer install --no-dev
	npm ci
	npm run build
	occ app:enable admin_group_manager
	occ app:enable groupquota
	```

## How to use

Check the available endpoints installing the app `ocs_api_viewer` and going to Nextcloud web interface at ocs_api_viewer app to verify the endpoints of this app.
