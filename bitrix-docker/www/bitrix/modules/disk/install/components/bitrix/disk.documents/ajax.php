<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Disk;
use Bitrix\Disk\File;
use Bitrix\Disk\Integration\Bitrix24Manager;
use Bitrix\Main;
use Bitrix\Main\Localization\Loc;

Main\Loader::requireModule('disk');

final class DiskDocumentsController extends Disk\Internals\Engine\Controller
{
	public function showFileHistoryAction(Disk\Document\TrackedObject $trackedObject): Main\Engine\Response\AjaxJson
	{
		if (!$trackedObject->canRead($this->getCurrentUser()->getId()))
		{
			if ($trackedObject->getUserId() == $this->getCurrentUser()->getId())
			{
				$trackedObject->delete();
			}

			return Main\Engine\Response\AjaxJson::createDenied()->setStatus('403 Forbidden');
		}

		return new Main\Engine\Response\Component(
			'bitrix:disk.file.history',
			'',
			[
				'STORAGE' => $trackedObject->getFile()->getStorage(),
				'FILE' => $trackedObject->getFile(),
			]
		);
	}

	public function getMenuActionsAction(Disk\Document\TrackedObject $trackedObject)
	{
		$urlManager = Disk\Driver::getInstance()->getUrlManager();
		if (!$trackedObject->canRead($this->getCurrentUser()->getId()))
		{
			if ($trackedObject->getUserId() == $this->getCurrentUser()->getId())
			{
				$trackedObject->delete();
			}

			return Main\Engine\Response\AjaxJson::createDenied()->setStatus('403 Forbidden');
		}

		$file = $trackedObject->getFile();
		/** @see \Bitrix\Disk\Controller\TrackedObject::downloadAction */
		$downloadUri = (new Disk\Controller\TrackedObject())->getActionUri('download', ['id' => $trackedObject->getId()]);

		$actions = [
			[
				'id' => 'download',
				'icon' => '/bitrix/js/ui/actionpanel/images/ui_icon_actionpanel_download.svg',
				'text' => Loc::getMessage('DISK_DOCUMENTS_ACT_DOWNLOAD'),
				'href' => $downloadUri,
			]
		];

		$actionToShare = [];
		if (Disk\Configuration::isPossibleToShowExternalLinkControl())
		{
			$featureBlocker = Bitrix24Manager::filterJsAction('disk_manual_external_link', '');
			$actionToShare[] = [
				'id' => 'externalLink',
				'text' => Loc::getMessage('DISK_DOCUMENTS_ACT_GET_EXT_LINK'),
				'dataset' => [
					'shouldBlockFeature' => (bool)$featureBlocker,
					'blocker' => $featureBlocker ?: null,
				]
			];
		}

		$belongsToDiskStorages = $this->belongsToDiskStorages($file);
		if ($belongsToDiskStorages)
		{
			$internalLink = $urlManager->getUrlFocusController('showObjectInGrid', [
					'objectId' => $file->getId(),
					'cmd' => 'show',
			], true);

			$actionToShare[] = [
				'id' => 'internalLink',
				'text' => Loc::getMessage('DISK_DOCUMENTS_ACT_COPY_INTERNAL_LINK'),
				'dataset' => [
					'internalLink' => $internalLink,
					'textCopied' => Loc::getMessage('DISK_DOCUMENTS_ACT_COPIED_INTERNAL_LINK')
				]
			];
		}

		$actionToShare[] = [
			'id' => 'sharing',
			'text' => Loc::getMessage('DISK_DOCUMENTS_ACT_SHARING_2'),
			'dataset' => [
				'objectId' => $trackedObject->getFileId(),
				'objectName' => $trackedObject->getFile()->getName(),
				'type' => $this->getSharingControlType($trackedObject),
			]
		];

		if ($actionToShare)
		{
			$actions[] = [
				'id' => 'share-section',
				'text' => Loc::getMessage('DISK_DOCUMENTS_ACT_SHARE_COMPLEX'),
				'icon' => '/bitrix/js/ui/actionpanel/images/ui_icon_actionpanel_share.svg',
				'items' => $actionToShare,
			];
		}

		if ($trackedObject->canRename($this->getCurrentUser()->getId()))
		{
			$actions[] = [
				'id' => 'rename',
				'text' => Loc::getMessage('DISK_DOCUMENTS_ACT_RENAME'),
				'icon' => '/bitrix/js/ui/actionpanel/images/ui_icon_actionpanel_rename.svg'
			];
		}

		if ($belongsToDiskStorages)
		{
			$actions[] = [
				'id' => 'history',
				'text' => Loc::getMessage('DISK_DOCUMENTS_ACT_SHOW_HISTORY'),
				'dataset' => [
					'objectId' => $trackedObject->getFileId(),
					'objectName' => $trackedObject->getFile()->getName(),
					'fileHistoryUrl' => $urlManager->getPathFileHistory($file),
					'blockedByFeature' => !Bitrix24Manager::isFeatureEnabled('disk_file_history')
				],
			];
		}

		if ($trackedObject->canMarkDeleted($this->getCurrentUser()->getId()))
		{
			$actions[] = [
				'id' => 'delete',
				'text' => Loc::getMessage('DISK_DOCUMENTS_ACT_DELETE'),
				'dataset' => [
					'objectId' => $trackedObject->getFileId(),
					'objectName' => $trackedObject->getFile()->getName(),
				],
			];
		}

		return $actions;
	}

	protected function getSharingControlType(Disk\Document\TrackedObject $trackedObject): ?string
	{
		$currentUserId = $this->getCurrentUser()->getId();
		if (!$trackedObject->canChangeRights($currentUserId) && !$trackedObject->canShare($currentUserId))
		{
			return 'without-edit';
		}
		if ($trackedObject->canChangeRights($currentUserId))
		{
			return 'with-change-rights';
		}
		if ($trackedObject->canShare($currentUserId))
		{
			return 'with-sharing';
		}

		return 'without-edit';
	}

	protected function belongsToDiskStorages(File $file): bool
	{
		$storage = $file->getStorage();
		if (!$storage)
		{
			return false;
		}

		return $storage->getProxyType() instanceof Disk\ProxyType\Common
			|| $storage->getProxyType() instanceof Disk\ProxyType\Group
			|| $storage->getProxyType() instanceof Disk\ProxyType\User
		;
	}

	public function getInfoAction(array $trackedObjectIds): array
	{
		$result = [];
		$fileController = Main\Engine\ControllerBuilder::build(Disk\Controller\File::class, []);
		$userId = $this->getCurrentUser()?->getId();
		if (!$userId)
		{
			return $result;
		}

		foreach ($trackedObjectIds as $trackedObjectId => $actions)
		{
			$result[$trackedObjectId] = [
				'shared' => null,
				'externalLink' => null,
			];

			$trackedObject = Disk\Document\TrackedObject::getById((int)$trackedObjectId);
			if (!$trackedObject || !$trackedObject->canRead($userId))
			{
				continue;
			}

			if (\in_array('shared', $actions, true))
			{
				if (!$trackedObject->canShare($userId) && !$trackedObject->canChangeRights($userId))
				{
					$user = Disk\User::getById($userId);
					$result[$trackedObjectId]['shared'] = [[
						'entityId' => Disk\Sharing::CODE_USER . $userId,
						'name' => $user->getFormattedName(),
						'url' => $user->getDetailUrl(),
						'avatar' => $user->getAvatarSrc(),
						'type' => 'users',
					]];
				}
				else
				{
					$result[$trackedObjectId]['shared'] = $trackedObject->getFile()->getMembersOfSharing();
				}
			}

			if (\in_array('externalLink', $actions, true))
			{
				$externalLinkData = $fileController->getExternalLinkAction($trackedObject->getFile());
				$result[$trackedObjectId]['externalLink'] = $externalLinkData['externalLink'] ?? null;
			}
		}

		return $result;
	}

	public function getMenuOpenAction($trackedObjectId)
	{
		return 'someUrl';
	}
}
