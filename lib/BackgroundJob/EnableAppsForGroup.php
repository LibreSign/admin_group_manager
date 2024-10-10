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
			$enabled = $this->appConfig->getValueArray($appId, 'enabled', []);
			if (!in_array($this->groupId, $enabled)) {
				$enabled[] = $this->groupId;
				$this->appManager->enableAppForGroups($appId, $enabled);
			}
		}
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
