<?php

use Lochmueller\Index\Hooks\DataHandlerUpdateHook;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Writer\Enum\Interval;
use TYPO3\CMS\Core\Log\Writer\RotatingFileWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// @see Bus class and https://forge.typo3.org/issues/101699
// $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing']['Lochmueller\\Index\\Queue\\Message\\*'] = 'index';

/** @var Environment $context */
$environment = GeneralUtility::makeInstance(Environment::class);
$level = $environment->getContext()->isDevelopment() ? LogLevel::DEBUG : LogLevel::WARNING;

$GLOBALS['TYPO3_CONF_VARS']['LOG']['Lochmueller']['Index']['writerConfiguration'] = [
    $level => [
        RotatingFileWriter::class => [
            'interval' => Interval::DAILY,
            'maxFiles' => 5,
            'logFileInfix' => 'index',
        ],
    ],
];

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['ext:index'] = DataHandlerUpdateHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['ext:index'] = DataHandlerUpdateHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearPageCacheEval']['ext:index'] = DataHandlerUpdateHook::class . '->clearCacheCmd';