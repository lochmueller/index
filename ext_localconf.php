<?php

use Lochmueller\Index\Queue\Message\CachePageMessage;
use Lochmueller\Index\Queue\Message\DatabaseIndexMessage;
use Lochmueller\Index\Queue\Message\FileMessage;
use Lochmueller\Index\Queue\Message\FinishProcessMessage;
use Lochmueller\Index\Queue\Message\StartProcessMessage;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Writer\Enum\Interval;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Log\Writer\RotatingFileWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][CachePageMessage::class] = 'doctrine';
// $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][DatabaseIndexMessage::class] = 'doctrine';
// $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][FileMessage::class] = 'doctrine';
// $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][FinishProcessMessage::class] = 'doctrine';
// $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][FrontendIndexMessage::class] = 'doctrine';
// $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][StartProcessMessage::class] = 'doctrine';

// @see https://forge.typo3.org/issues/101699
// unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing']['*']);

/** @var Environment $context */
$environment = GeneralUtility::makeInstance(Environment::class);
$level = $environment->getContext()->isDevelopment() ? LogLevel::DEBUG : LogLevel::WARNING;

$GLOBALS['TYPO3_CONF_VARS']['LOG']['Lochmueller']['Index']['writerConfiguration'] = [
    $level => [
        RotatingFileWriter::class => [
            'interval' => Interval::DAILY,
            'maxFiles' => 5,
            'logFileInfix' => 'index'
        ],
    ],
];