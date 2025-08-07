<?php

use Lochmueller\Index\Queue\Message\WebIndexMessage;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;


// "index"-transport for
$GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][WebIndexMessage::class] = 'doctrine';


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