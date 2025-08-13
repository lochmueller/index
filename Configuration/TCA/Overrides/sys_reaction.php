<?php

use Lochmueller\Index\Reaction\IndexExternalFileReaction;
use Lochmueller\Index\Reaction\IndexExternalPageReaction;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTcaSelectItem(
    'sys_reaction',
    'reaction_type',
    [
        'label' => IndexExternalPageReaction::getDescription(),
        'value' => IndexExternalPageReaction::getType(),
        'icon' => IndexExternalPageReaction::getIconIdentifier(),
    ]
);
ExtensionManagementUtility::addTcaSelectItem(
    'sys_reaction',
    'reaction_type',
    [
        'label' => IndexExternalFileReaction::getDescription(),
        'value' => IndexExternalFileReaction::getType(),
        'icon' => IndexExternalFileReaction::getIconIdentifier(),
    ]
);