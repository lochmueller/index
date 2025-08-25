# EXT:index

EXT:index is a TYPO3 indexing framework to handle the content generation of a TYPO3 website related to different index
configurations. This could be used for search engine fill-up processes or other content engines that need the generated
content of typo3 pages and files (AI provider). To sped up the indexing the TYPO3 internal message bus is used.

You can use the PSR-14 Events to get the index information or the webhook functions of the core, to move the indexed
information to external services.

## Installation & Configuration

1. Run `composer require lochmueller/index`
2. Create at least two scheduler task and take care that the scheduler is executed:
    - `index:queue` (example: every two days in midnight) fill up the message queue with the indexed pages
    - `messenger:consume index` (base on
      the [documentation](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/MessageBus/Index.html#message-bus-consume-command))
      to handle the index processes of the queue.
3. Create index configurations on pages (example: on the root page of your site)
4. Use the extension, that is based on EXT:index and have fun.

Feel free to create an [issue](https://github.com/lochmueller/indexing/issues) if you have some more ideas or found a
bug.

### Configuration

The traversing configuration of the database and frontend indexing is configured via JSON. This is an example
configuration for content indexing on the pages (database indexing online). and the url build process for news. Only the
first extender is executed for every page.

Possible types are:

- news (Lochmueller\Index\Traversing\Extender\News)
- address (Lochmueller\Index\Traversing\Extender\Address)

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

### Index mechanisms

Same information about the different index mechanisms + advantages or disadvantages.

- *Cache* - Easy in the configuration. No active full index process. Indexing is done in the regular cache fill process.
  No hard impact to the page performance.
- *Database* - Very fast build process of the page content with separated PHP integrations. Custom content elements need
  separated class integrations.
- *External* - Not direct selectable in the backend! Used for content that is send via webhook to the EXT:reactions
  endpoint. This content use the same path in the index workflow.
- *File* - Not direct selectable in the backend! Is used in the regular index process to select additional files in the
  index process.
- *Frontend* - Use a internal subrequest and execute the regular frontend middleware stack. The integration have to use
  the middleware in the right way. Fast then "real frontend requests" and create the real HTML markup.
- *Http* - If there are problems with "Frontend" you can select Http. This create real frontend requests that are send
  via network. Please keep password protection in mind. Very slow and have load on the server.
- *None* - Use it to exclude the current page(tree) in the traversing process of the parent configuration.

## Developer information

The extension provides a framework for easy indexing pages and files. Use this documentation to get the right
information
for your extension.

### Events

There are four 'public' main events that you can use in your extension. EXT:index take care of the index processing and
the async handling of the queue. So you can directly consume the event, add your business logic and run more or less
complex processes in the event listener. Please *DO NOT* usage the internal messages that are part of the internal
process.

- **StartIndexProcess** - Start event incl. meta information like technology, type
- **IndexPageEvent** - Index event for pages incl. title, content and meta information of one page
- **IndexFileEvent** - Index event for files incl. title, content and meta information of one file
- **EndIndexProcess** - End event incl. meta information like technology, type

*Please keep in mind, that the events are pushed directly after another into the message bus. If there are any reasons
that the
FIFO is not consistent, we cannot guarantee that the events (especially Start and Finish) are in the right order*

There are additional events to customize the index process:

- **ContentType\HandleContentTypeEvent** - Customize or add the rendering of content for database indexing.
- **Extractor\CustomFileExtraction** - Event based file extraction (if not flexible enough, use the DI tag)

### Symfony DI Tags

There are several Symfony DI tags, that create iterables for internal functions. You can use this to add your own
integrations. These are very "internal" options to extend the index process:

- **index.content_type** - Rendering definitions for database indexing.
- **index.file_extractor** - Extract content from files in the index process.
- **index.extender** - Extend the URI queue in the Page traversing.

### Webhooks / Reactions

All four events are available as webhooks. You can use the webhook functions of the core to move the indexed
information to external services. It is also possible to add external resources to the internal index process. There are
two reactions that create indexed pages and files. Feel free to connection instances ;)

### File extraction

The file extraction is based on different third party packages. Please take care to install the packages, that the
content fetch process out of the files is working.

## Extension based on EXT:index

- EXT:seal - Search Engine Abstraction Layer
- (more to come - please create a PR to extend this list)
