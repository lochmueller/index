<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\File;

use Lochmueller\Index\Indexing\IndexingInterface;
use Lochmueller\Index\Queue\Message\FrontendIndexMessage;
use Lochmueller\Index\Traversing\FileTraversing;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileIndexing implements IndexingInterface
{
    public function __construct(
        private FileTraversing $fileTraversing,
    ) {}


    public function fillQueue(array $fileMount, array $fileTypes): void
    {

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);


        $row = BackendUtility::getRecord('sys_filemounts', $fileMount);
        if ($row) {
            /** @var Folder $folder */
            $folder = $resourceFactory->getFolderObjectFromCombinedIdentifier($row['identifier']);


            var_dump($folder);
        }

        // Falls du die Storage-UID kennst (z. B. 1) und den Pfad im Filemount (z. B. 'user_upload/')

        #// Alle Dateien im Ordner abrufen
        #        $files = $folder->getFiles();
        #
        #       foreach ($files as $file) {
        #          /** @var \TYPO3\CMS\Core\Resource\FileInterface $file */
        ##         echo 'Dateiname: ' . $file->getName() . '<br>';
        #       echo 'Pfad: ' . $file->getPublicUrl() . '<br>';
        #  }

        // @todo handle the message
        #        $message = new FrontendIndexMessage();

        // Send the message async via doctrine transport
        // @todo check https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/13.4.x/Important-103140-AllowToConfigureRateLimiters.html
        #       $this->bus->dispatch((new Envelope($message))->with(new TransportNamesStamp('doctrine')));
    }

    public function handleMessage(FrontendIndexMessage $message): void
    {
        // DebuggerUtility::var_dump($message);

        // @todo handle message
        // @todo Execute webrequest and index content
    }
}
