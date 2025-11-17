<?php

use Lochmueller\Index\Hooks\DataHandlerUpdateHook;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/** @var Environment $context */
$environment = GeneralUtility::makeInstance(Environment::class);
$level = $environment->getContext()->isDevelopment() ? LogLevel::DEBUG : LogLevel::WARNING;

$GLOBALS['TYPO3_CONF_VARS']['LOG']['Lochmueller']['Index']['writerConfiguration'] = [
    $level => [
        FileWriter::class => [
            'logFileInfix' => 'index',
        ],
    ],
];

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['ext:index'] = DataHandlerUpdateHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['ext:index'] = DataHandlerUpdateHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearPageCacheEval']['ext:index'] = DataHandlerUpdateHook::class . '->clearCacheCmd';

$GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing']['Lochmueller\\Index\\Queue\\Message\\*'] = 'index';

if ($environment->getContext()->isDevelopment()) {
    $extensionConfiguration = (new ExtensionConfiguration())->get('index');
    $defaultTransportInDevelopmentContext = isset($extensionConfiguration['defaultTransportInDevelopmentContext']) && (bool)$extensionConfiguration['defaultTransportInDevelopmentContext'];
    if($defaultTransportInDevelopmentContext) {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing']['Lochmueller\\Index\\Queue\\Message\\*']);
    }
}
