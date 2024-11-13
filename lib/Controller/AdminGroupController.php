<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AdminGroupManager\Controller;

use OCA\AdminGroupManager\BackgroundJob\EnableAppsForGroup;
use OCA\AdminGroupManager\Controller\Attribute\RestrictIp;
use OCA\Settings\Settings\Admin\Users;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Group\ISubAdmin;
use OCP\IAppConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
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
		protected IMailer $mailer,
		protected ISubAdmin $subAdmin,
		protected IAppManager $appManager,
		protected IAppConfig $appConfig,
		protected IEventDispatcher $eventDispatcher,
		protected ISecureRandom $secureRandom,
		protected IJobList $jobList,
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
	 * @param string $email Email of admin
	 * @param string $quota Group quota in "human readable" format. Default value is 1Gb.
	 * @param list<string> $apps List of app ids to enable
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
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
		string $email = '',
		string $quota = '1Gb',
		array $apps = [],
	): DataResponse {
		$group = $this->addGroup($groupid, $displayname);
		$this->setGroupQuota($groupid, $quota);
		$this->enableApps($apps, $groupid);
		$user = $this->createUser($groupid, $displayname, $email);
		$group->addUser($user);
		$this->addSubAdmin($user, $group);
		return new DataResponse();
	}

	/**
	 * Set enabled status
	 *
	 * Change the status of all users of a group to be enabled or not.
	 *
	 * @param string $groupid ID of the group
	 * @param int<0, 1> $enabled 1 or 0
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: OK
	 * 401: Unauthorized
	 * 404: Group not found
	 */
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/users-of-group/set-enabled', requirements: ['apiVersion' => '(v1)'])]
	#[AuthorizedAdminSetting(settings:Users::class)]
	#[RestrictIp]
	public function setEnabled(
		string $groupid,
		int $enabled,
	): DataResponse {
		$group = $this->groupManager->get($groupid);
		if (!$group instanceof IGroup) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$users = $group->getUsers();
		foreach ($users as $user) {
			$user->setEnabled((bool)$enabled);
		}
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

	private function createUser($userId, $displayName, $email): IUser {
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
		if ($email !== '') {
			if (!$this->mailer->validateMailAddress($email)) {
				throw new OCSException('Invalid email');
			}
			$user->setSystemEMailAddress($email);
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

	private function enableApps(array $appIds, string $groupId): void {
		if (!$appIds) {
			return;
		}
		$this->jobList->add(EnableAppsForGroup::class, [
			'groupId' => $groupId,
			'appIds' => $appIds,
		]);
	}
}
