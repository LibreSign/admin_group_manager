<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AdminGroupManager\BackgroundJob;

use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\IAppConfig;

class EnableAppsForGroup extends QueuedJob {
	private array $appIds;
	private string $groupId;

	public function __construct(
		private IAppManager $appManager,
		protected IAppConfig $appConfig,
		protected ITimeFactory $time,
	) {
		parent::__construct($time);
	}
	protected function run($argument): void {
		$this->setAllowParallelRuns(false);
		if (!$this->validateAndProccessArguments($argument)) {
			return;
		}
		foreach ($this->appIds as $appId) {
			$appId = $this->appManager->cleanAppId($appId);
			$toSave = $enabled = $this->appConfig->getValueArray($appId, 'enabled', []);
			if (!in_array($this->groupId, $toSave)) {
				$toSave[] = $this->groupId;
			}
			if (!in_array('admin', $toSave)) {
				$toSave[] = 'admin';
			}
			if ($enabled !== $toSave) {
				$this->appManager->enableAppForGroups($appId, $toSave);
			}
			$this->enableLibreSign($appId);
		}
	}

	private function enableLibreSign(string $appId): void {
		if ($appId !== 'libresign') {
			return;
		}
		$authorized = $this->appConfig->getValueArray('libresign', 'groups_request_sign', ['admin']);
		if (in_array($this->groupId, $authorized)) {
			return;
		}
		$authorized[] = $this->groupId;
		$this->appConfig->setValueArray('libresign', 'groups_request_sign', $authorized);
	}

	private function validateAndProccessArguments($argument): bool {
		if (!isset($argument['groupId'])) {
			return false;
		}
		if (!isset($argument['appIds'])) {
			return false;
		}
		if (!is_array($argument['appIds'])) {
			return false;
		}
		if (!count($argument['appIds'])) {
			return false;
		}
		$this->appIds = $argument['appIds'];
		$this->groupId = $argument['groupId'];
		return true;
	}
}
