<?php

use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/** @var Environment $context */
$environment = GeneralUtility::makeInstance(Environment::class);
if ($environment->getContext()->isDevelopment()) {
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['Lochmueller']['Index']['EventListener']['writerConfiguration'] = [
        LogLevel::DEBUG => [
            FileWriter::class => [
                'logFileInfix' => 'index'
            ],
        ],
    ];
}