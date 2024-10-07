<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AdminGroupManager\Controller;

use OCA\AdminGroupManager\Controller\Attribute\RestrictIp;
use OCA\Provisioning_API\Controller\AUserData;
use OCA\Settings\Settings\Admin\Users;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\ISubAdmin;
use OCP\IAppConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class AdminGroupController extends AEnvironmentAwareController {
	public function __construct(
		$appName,
		IRequest $request,
		protected LoggerInterface $logger,
		protected IGroupManager $groupManager,
		protected IUserManager $userManager,
		protected ISubAdmin $subAdmin,
		protected IAppManager $appManager,
		protected IAppConfig $appConfig,
		protected IEventDispatcher $eventDispatcher,
		protected ISecureRandom $secureRandom,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Create admin group
	 *
	 * Create an admin group.
	 *
	 * @param string $groupid ID of the group
	 * @param string $displayname Display name of the group
	 * @param string $quota Group quota in "human readable" format. Default value is 1Gb.
	 * @param list<string> $apps List of app ids to enable
	 * @return DataResponse<Http::STATUS_OK, array<empty>, array{}>
	 *
	 * 200: OK
	 * 401: Unauthorized
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin-group', requirements: ['apiVersion' => '(v1)'])]
	#[AuthorizedAdminSetting(settings:Users::class)]
	#[RestrictIp]
	public function createAdminGroup(
		string $groupid,
		string $displayname = '',
		string $quota = '1Gb',
		array $apps = [],
	): DataResponse {
		$group = $this->addGroup($groupid, $displayname);
		$this->setGroupQuota($groupid, $quota);
		$this->enableApps($apps, $groupid);
		$user = $this->createUser($groupid, $displayname);
		$this->addSubAdmin($user, $group);
		return new DataResponse();
	}

	/**
	 * Make a user a subadmin of a group
	 *
	 * @param string $userId ID of the user
	 * @param string $groupid ID of the group
	 * @throws OCSException
	 */
	private function addSubAdmin(IUser $user, IGroup $group): void {
		// We cannot be subadmin twice
		if ($this->subAdmin->isSubAdminOfGroup($user, $group)) {
			return;
		}
		$this->subAdmin->createSubAdmin($user, $group);
	}

	private function createUser($userId, $displayName): IUser {
		$passwordEvent = new GenerateSecurePasswordEvent();
		$this->eventDispatcher->dispatchTyped($passwordEvent);
		$password = $passwordEvent->getPassword() ?? $this->secureRandom->generate(20);

		$user = $this->userManager->createUser($userId, $password);

		if ($displayName !== '') {
			try {
				$user->setDisplayName($displayName);
			} catch (OCSException $e) {
				$user->delete();
				throw $e;
			}
		}
		return $user;
	}

	private function addGroup(string $groupid, string $displayname = ''): IGroup {
		// Validate name
		if (empty($groupid)) {
			$this->logger->error('Group name not supplied', ['app' => 'provisioning_api']);
			throw new OCSException('Invalid group name', 101);
		}
		// Check if it exists
		if ($this->groupManager->groupExists($groupid)) {
			throw new OCSException('group exists', 102);
		}
		$group = $this->groupManager->createGroup($groupid);
		if ($group === null) {
			throw new OCSException('Not supported by backend', 103);
		}
		if ($displayname !== '') {
			$group->setDisplayName($displayname);
		}
		return $group;
	}

	private function setGroupQuota(string $groupId, string $quota): void {
		$quota = \OC_Helper::computerFileSize($quota);
		$this->appConfig->setValueString('groupquota', 'quota_' . $groupId, (string)$quota);
	}

	/**
	 * TODO: Identify a best approach, the list of apps enabled to a group is a
	 * json field at appsettings table, could have problems with simultaneous
	 * update and also could be a very big array.
	 *
	 * @param array $appIds
	 * @param string $groupId
	 * @return void
	 */
	private function enableApps(array $appIds, string $groupId): void {
		foreach ($appIds as $appId) {
			$appId = $this->appManager->cleanAppId($appId);
			$enabled = $this->appConfig->getValueArray($appId, 'enabled', []);
			if (!in_array($groupId, $enabled)) {
				$enabled[] = $groupId;
				$this->appManager->enableAppForGroups($appId, $enabled);
			}
		}
	}
}
