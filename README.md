# EXT:index

EXT:index is a TYPO3 indexing framework to handle the content generation of a TYPO3 website related to different index
configurations. This could be used for search engine fill-up processes or other content engines that need the generated
content of TYPO3 pages and files (AI provider). To speed up the indexing, the TYPO3 internal message bus is used.

You can use the PSR-14 events to get the index information or the webhook functions of the core to move the indexed
information to external services. Please do not use the internal messages, because they are handled internally in the
extension.

This extension was funded by the [TYPO3 Association](https://typo3.org): [community ideas I](https://typo3.org/article/members-have-selected-five-ideas-to-be-funded-in-quarter-3-2025) / [community idea II](https://talk.typo3.org/t/ext-seal-ecosystem-expansion-advanced-search-ai-vector-integration-tim-lochmuller/6594) & [first blogpost](https://typo3.org/article/typo3-meets-seal-a-breath-of-fresh-air-for-search) / [second blogpost](https://news.typo3.com/article/extseal-takes-the-next-step-advanced-search-geo-features-ai-vector-integration)

## Installation & Configuration

1. Run `composer require lochmueller/index`
2. Create at least two scheduler tasks and take care that the scheduler is executed:
    - `index:queue` (example: every two days at midnight) fills up the message queue with the indexed pages via full
      index
    - `messenger:consume index` (based on
      the [documentation](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/MessageBus/Index.html#message-bus-consume-command))
      to handle the index processes of the queue
3. Create index configurations on pages (example: on the root page of your site or subpages)
4. Use the extension that is based on EXT:index and have fun

Feel free to create an [issue](https://github.com/lochmueller/index/issues) if you have some more ideas or found a
bug.

### Configuration

The traversing configuration of the database and frontend indexing is configured via JSON. This is an example
configuration for content indexing on the pages (database indexing online) and the URL build process for news. Only the
first valid (`limitToPages`) extender is executed for every page.

Possible types are:

- record (Lochmueller\Index\Traversing\Extender\RecordExtender) - generic, see [below](#generic-record-extender)
- news (Lochmueller\Index\Traversing\Extender\News)
- address (Lochmueller\Index\Traversing\Extender\Address)
- calendarize (Lochmueller\Index\Traversing\Extender\Calendarize)
- sf_event_mgt (Lochmueller\Index\Traversing\Extender\SfEventMgt)

```json
{
  "extender": [
    {
      "type": "news",
      "limitToPages": [
        13,
        392
      ],
      "recordStorages": [
        12,
        24
      ],
      "dropOriginalUri": true
    }
  ]
}
```

Every extender supports these common options, they are evaluated by the page traversing itself:

| Option            | Type    | Description                                                                                     |
|-------------------|---------|-------------------------------------------------------------------------------------------------|
| `type`            | string  | Name of the extender (`getName()` of the `ExtenderInterface` implementation).                     |
| `limitToPages`    | int[]   | Restrict the extender to these page UIDs. Without this option the extender is used on every page. |
| `dropOriginalUri` | bool    | Do not index the page itself, only the URIs that are created by the extender.                     |

#### Generic record extender

Most detail views follow the same pattern: a TCA table, an optional record type filter and a plugin namespace with
the controller, the action and the record argument. The `record` extender covers this pattern via configuration, so
there is no need for an own PHP class. The configuration of the `news` extender looks like this:

```json
{
  "extender": [
    {
      "type": "record",
      "table": "tx_news_domain_model_news",
      "recordTypes": [
        "0"
      ],
      "recordStorages": [
        12,
        24
      ],
      "limitToPages": [
        13
      ],
      "dropOriginalUri": true,
      "arguments": {
        "tx_news_pi1": {
          "controller": "News",
          "action": "detail",
          "news": "{uid}"
        }
      }
    }
  ]
}
```

| Option           | Type                | Description                                                                                                                      |
|------------------|---------------------|----------------------------------------------------------------------------------------------------------------------------------|
| `table`          | string              | Required. TCA table of the detail records. Without this option the extender does nothing.                                          |
| `recordStorages` | int[]               | Storage pages of the records. Defaults to the current detail page, if the option is missing or empty.                              |
| `recordTypes`    | string[]            | Optional filter on the record type (TCA `type` field). Without this option all record types are used.                              |
| `constraints`    | object              | Optional filter on record fields. A scalar value is compared as a string, an array works like an `IN` comparison.                  |
| `arguments`      | object              | Routing arguments for the URI generation. The structure is passed to the page router, placeholders are resolved per record.        |

The records are selected with the frontend restrictions (hidden, start/endtime, fe_group) and the language overlay
handling of the extension, so the same rules as for the dedicated extenders apply.

These placeholders can be used in every string of `arguments`:

| Placeholder     | Value                                                                       |
|-----------------|-----------------------------------------------------------------------------|
| `{uid}`         | UID of the record                                                            |
| `{pid}`         | PID of the record                                                            |
| `{pageUid}`     | UID of the current detail page                                               |
| `{languageId}`  | ID of the current site language                                              |
| `{field:slug}`  | Value of the record field `slug` (every field of the record can be used)     |

A string that only consists of one placeholder keeps the original type (`"{uid}"` becomes an integer), which is
important for the aspects of the route enhancers. If a string contains more content, the placeholders are replaced
inside the string (`"{uid}-{field:slug}"` becomes `"99-my-slug"`). Unknown placeholders stay untouched, so that
broken configurations are visible in the generated URI. Fields that are not part of the record resolve to `null`.
The `_language` argument is always set by the extender and cannot be overridden.

If you want to ship a named preset for your own extension instead of repeating the configuration, extend the class
and override `getBaseConfiguration()`. The values of the index configuration always win over the base configuration:

```php
class MyExtension extends RecordExtender
{
    public function getName(): string
    {
        return 'my_extension';
    }

    protected function getBaseConfiguration(): array
    {
        return [
            'table' => 'tx_myextension_domain_model_item',
            'arguments' => [
                'tx_myextension_pi1' => [
                    'controller' => 'Item',
                    'action' => 'detail',
                    'item' => '{uid}',
                ],
            ],
        ];
    }
}
```

Use an own implementation of the `ExtenderInterface` if the URI generation needs real logic, e.g. more than one URI
per record, an external data source or additional queries.

### Content processing

Before the generated HTML is handed over to the indexing process, it can be piped through a chain of content
processors. In each index configuration record you can activate the desired processors via checkboxes; the list is
built dynamically from all services that implement `Lochmueller\Index\ContentProcessing\ContentProcessorInterface`
(tag `index.content_processor`).

Shipped processors:

- *TYPO3SEARCH markers* (`Typo3SearchMakerContentProcessor`) - Respects the classic `<!--TYPO3SEARCH_begin-->` /
  `<!--TYPO3SEARCH_end-->` markers to include/exclude parts of the HTML.
- *Event-based* (`EventContentProcessor`) - Dispatches the `Lochmueller\Index\Event\ModifyContentEvent` so that
  listeners can freely modify the content (e.g. strip navigation, inject metadata).

To provide your own processor, implement the interface and register a `getLabel()` method - autoconfiguration and
the TCA `itemsProcFunc` pick it up automatically.

### Index mechanisms

Here are some information about the different index mechanisms and the advantages or disadvantages. You can use
different index mechanism on different sub pages.

- *Cache*
    - Easy in the configuration.
    - No active full index process.
    - Indexing is done in the regular cache fill process.
    - No hard impact on the page performance.
- *Database*
    - Regular full indexing.
    - Very fast build process of the page content with separate PHP integrations.
    - Custom content elements need separate class integrations.
    - Creates only a light version of the HTML markup.
    - Support EXT:bootstrap_package, EXT:calendarize, EXT:tt_address, EXT:container, EXT:content_blocks
- *External*
    - Not directly selectable in the backend.
    - Used for content that is sent via webhook to the EXT:reactions endpoint.
    - This content uses the same path in the index workflow (internal message + event).
- *File*
    - Not directly selectable in the backend.
    - Is used in the regular index process to select additional files.
- *Frontend*
    - Uses an internal subrequest and executes the regular frontend middleware stack.
    - The TYPO3 system and all extensions have to use the middleware in the right way.
    - Faster than "real frontend requests".
    - Creates the real HTML markup of pages.
- *Http*
    - If there are problems with "Frontend", you can select Http.
    - This creates real frontend requests that are sent via network.
    - Please keep password protection in mind.
    - Very slow and significantly higher load on the server.
    - Creates the real HTML markup of pages.
- *None*
    - Use it to exclude the current page & subpages in the traversing process of the parent configuration.

## Developer information

The extension provides a framework for easy indexing pages and files. Use this documentation to get the right
information for your extension.

### Events

There are four public main events that you can use in your extension. EXT:index takes care of the index processing and
the async handling of the queue. So you can directly consume the event, add your business logic and run more or less
complex processes in the event listener. Please *DO NOT* use the internal messages that are part of the internal
process.

- **StartIndexProcessEvent** - Start event incl. meta information like technology, type
- **IndexPageEvent** - Index event for pages incl. title, content and meta information of one page
- **IndexFileEvent** - Index event for files incl. title, content and meta information of one file
- **FinishIndexProcessEvent** - End event incl. meta information like technology, type

*Please keep in mind, that the messages are pushed directly, one after another, into the message bus.
If there are any reasons that the FIFO (first in, first out) is not consistent, we cannot guarantee
that the events (especially Start and Finish) are in the right order*

There are additional events to customize the index process:

- **ContentType\HandleContentTypeEvent** - Customize or add the rendering of content for database indexing.
- **Extractor\CustomFileExtraction** - Event based file extraction (if not flexible enough, use the DI tag)
- **ModifyContentEvent** - Dispatched by the `EventContentProcessor` to modify the HTML content before indexing.

### Symfony DI Tags

There are several Symfony DI tags, that create iterables for internal functions. You can use this to add your own
integrations. These are very "internal" options to extend the index process:

- **index.content_type** - Rendering definitions for database indexing.
- **index.file_extractor** - Extract content from files in the index process.
- **index.extender** - Extend the URI queue in the Page traversing.
- **index.content_processor** - Modify the HTML content before indexing (selectable per index configuration).

### Webhooks / Reactions

All four events are available as webhooks. You can use the webhook functions of the core to move the indexed
information to external services. It is also possible to add external resources to the internal index process. There are
two reactions that create indexed pages and files. Feel free to connect instances ;)

### File extraction

The file extraction is based on different third party packages. Please take care to install the packages, so that the
process to fetch content out of files is working. Check out the composer.json `suggest` section.

## Extension based on EXT:index

- [EXT:seal](https://github.com/lochmueller/seal) - Search Engine Abstraction Layer
- (more to come - please create a PR to extend this list)
