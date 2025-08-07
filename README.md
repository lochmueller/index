# EXT:index

EXT:index is a TYPO3 Index framework to handle the content generation of a TYPO3 website related to different index
configurations. This could be used for search engine fill-up processes or other content engines that need the generated
content of typo3 pages and files. To sped up the indexing the databse and web indexing use the TYPO3 internal message
bus.

You can use the PSR-14 Events to get the index information or the webhook functions of the core, to move the indexed
information to external services.

## Installation & Configuration

1. Run `composer require lochmueller/index`
2. Create at least two scheduler task and take care that the scheduler is executed:
    - `index:queue` (example: every two days in midnight) fill up the message queue with the indexed pages
    - `messenger:consume doctrine` (base on
      the [documentation](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/MessageBus/Index.html#message-bus-consume-command))
      to handle the index processes of the queue.
3. Create index configurations on pages (example: on the root page of your site)
4. Use the extension, that is based on EXT:index and have fun.

Feel free to create an [issue](https://github.com/lochmueller/indexing/issues) if you have some more ideas or found a
bug.

## Developer information

The extension provide a framework for easy indexing pages and files. Use this documentation to get the right information
for your extension.

### Events

There are four 'public' events that you can use in your extension. EXT:index take care of the index processing and
the async handling of the queue. So you can directly consume the event and add your business logic. Please *DO NOT*
usage the internal messages that are part of the intx process.

- **StartIndexProcess** - Start event incl. meta information like technology, type
- **IndexPageEvent** - Index event for pages incl. title, content and meta information of one page
- **IndexFileEvent** - Index event for files incl. title, content and meta information of one file
- **EndIndexProcess** - End event incl. meta information like technology, type