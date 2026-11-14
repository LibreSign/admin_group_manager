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
- Service account

	Call this API with a dedicated account that is not in the `admin` group. Put this account in a group that has the "Users" admin section delegated to it (Administration settings > Administration privileges). This account is also able to change the password of users that are not admins through the provisioning API.

- Allowed IP range

	This API only receives requests from the configured IP range. The range is optional for compatibility, but it is required in production. Set it with the following command:
	```bash
	occ config:system:set admin_group_manager_allowed_range 0 --value <theWordPressIp>
	```

	To test if your setting is working fine, use a IP range that don't match with WordPressIP and tun a tail with grep to watch by the word "Unauthorized access".
		```bash
		tail -f data/nextcloud.log|grep "Unauthorized access"
		```

## Performance improving
Systemd service

- Create a systemd service file in /etc/systemd/system/admin-group-manager.service with the following content:
	```ini
	[Unit]
	Description=Admin Group Manager worker
	After=network.target

	[Service]
	ExecStart=/opt/admin-group-manager/taskprocessing.sh
	Restart=always

	[Install]
	WantedBy=multi-user.target
	```


- Create a shell script in /opt/admin-group-manager/taskprocessing.sh with the following content and make sure to make it executable:

	```bash
	#!/bin/sh
	echo "Starting Admin Group Manager worker $1"
	cd /path/to/nextcloud
	sudo -u www-data php occ background-job:worker 'OCA\AdminGroupManager\BackgroundJob\EnableAppsForGroup'
	```

- Enable and start the service:
  ```bash
  systemctl enable --now admin-group-manager.service
  ```
- Check if is working fine:
	```bash
	systemctl list-units --type=service | grep admin-group-manager
	```
- Check execution log:
	```bash
	journalctl -xeu nextcloud-ai-worker.service -f
	```

## How to use

Check the available endpoints installing the app `ocs_api_viewer` and going to Nextcloud web interface at ocs_api_viewer app to verify the endpoints of this app.
