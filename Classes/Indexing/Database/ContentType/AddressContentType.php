<?php

declare(strict_types=1);

namespace Lochmueller\Index\Indexing\Database\ContentType;

use Lochmueller\Index\Domain\Repository\GenericRepository;
use Lochmueller\Index\Indexing\Database\DatabaseIndexingDto;
use Lochmueller\Index\Traversing\RecordSelection;
use TYPO3\CMS\Core\Domain\FlexFormFieldValues;
use TYPO3\CMS\Core\Domain\Record;

final class AddressContentType implements ContentTypeInterface
{
    public function __construct(
        protected HeaderContentType $headerContentType,
        protected RecordSelection $recordSelection,
        protected GenericRepository $genericRepository,
    ) {}

    public function canHandle(Record $record): bool
    {
        return $record->getRecordType() === 'ttaddress_listview';
    }

    public function addContent(Record $record, DatabaseIndexingDto $dto): void
    {
        $addressId = $dto->arguments['tx_ttaddress_listview']['address'] ?? 0;
        if ($addressId <= 0) {
            return;
        }

        $this->headerContentType->addContent($record, $dto);
        $table = 'tt_address';
        $row = $this->genericRepository->setTableName($table)->findByUid($addressId);
        if ($row === null) {
            return;
        }
        $addressRecord = $this->recordSelection->mapRecord($table, $row);

        $fullName = $this->buildFullName($addressRecord);
        if ($fullName !== '') {
            $dto->title = $fullName . ' | ' . $dto->site->getAttribute('websiteTitle');
        }

        $dto->content .= $this->buildIndexContent($addressRecord);
    }

    /**
     * @param \SplQueue<DatabaseIndexingDto> $queue
     */
    public function addVariants(Record $record, \SplQueue &$queue): void
    {
        /** @var DatabaseIndexingDto $dto */
        $dto = $queue->offsetGet(0);

        /** @var FlexFormFieldValues $flexFormConfiguration */
        $flexFormConfiguration = $record->get('pi_flexform');
        $array = $flexFormConfiguration->toArray();

        $displayMode = $array['sDISPLAY']['settings']['displayMode'] ?? 'list';
        if ($displayMode !== 'single' && $displayMode !== 'list') {
            return;
        }

        $queue = new \SplQueue();

        foreach ($this->getAddressRecords($record, $dto->languageUid) as $addressRecord) {
            $arguments = [
                '_language' => $dto->languageUid,
                'tx_ttaddress_listview' => [
                    'action' => 'show',
                    'controller' => 'Address',
                    'address' => $addressRecord->getUid(),
                ],
            ];

            $queue[] = new DatabaseIndexingDto(
                $dto->title,
                $dto->content,
                $dto->pageUid,
                $dto->languageUid,
                $arguments,
                $dto->site,
            );
        }
    }

    /**
     * @return iterable<Record>
     */
    protected function getAddressRecords(Record $record, int $languageUid): iterable
    {
        /** @var FlexFormFieldValues $flexFormConfiguration */
        $flexFormConfiguration = $record->get('pi_flexform');
        $array = $flexFormConfiguration->toArray();

        $storage = [-99];
        foreach ($array['sDEF']['settings']['pages'] ?? [] as $page) {
            $storage[] = $page->get('uid');
        }

        yield from $this->recordSelection->findRecordsOnPage('tt_address', $storage, $languageUid);
    }

    protected function buildFullName(Record $record): string
    {
        $parts = array_filter([
            (string) $record->get('title'),
            (string) $record->get('first_name'),
            (string) $record->get('middle_name'),
            (string) $record->get('last_name'),
        ]);

        $name = implode(' ', $parts);
        $titleSuffix = (string) $record->get('title_suffix');
        if ($titleSuffix !== '') {
            $name .= ', ' . $titleSuffix;
        }

        if ($name === '') {
            $name = (string) $record->get('name');
        }

        return $name;
    }

    protected function buildIndexContent(Record $record): string
    {
        $parts = [];

        $fullName = $this->buildFullName($record);
        if ($fullName !== '') {
            $parts[] = $fullName;
        }

        $company = (string) $record->get('company');
        if ($company !== '') {
            $parts[] = $company;
        }

        $position = (string) $record->get('position');
        if ($position !== '') {
            $parts[] = $position;
        }

        $address = (string) $record->get('address');
        if ($address !== '') {
            $parts[] = str_replace("\n", ' ', $address);
        }

        $zip = (string) $record->get('zip');
        $city = (string) $record->get('city');
        if ($zip !== '' || $city !== '') {
            $parts[] = trim($zip . ' ' . $city);
        }

        $region = (string) $record->get('region');
        if ($region !== '') {
            $parts[] = $region;
        }

        $country = (string) $record->get('country');
        if ($country !== '') {
            $parts[] = $country;
        }

        $description = (string) $record->get('description');
        if ($description !== '') {
            $parts[] = strip_tags($description);
        }

        return implode(' ', $parts) . ' ';
    }
}
