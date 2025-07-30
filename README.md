# EXT:index

TYPO3 Index framework to handle the content generation of a TYPO3 website related to different index configurations.
This could be used for search engine fill-up processes or other content extraction engines. To sped up the indexing 2/3
of the Index types use the TYPO3 internal message bus.

## Installation & Configuration

1. Run `composer require lochmueller/index`
2. Create at least two scheduler task and take care that the scheduler is executed:
    - `index:queue` (every two days in midnight) fill up the message queue with the indexed pages
    - `messenger:consume` (base on
      the [documentation](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/MessageBus/Index.html#message-bus-consume-command))
      to handle the index processes of the queue.
3. Create index configurations on pages (for example on the root page of your site)
4. Use the extension, that is based on EXT:index and have fun.

Feel free to create an [issue](https://github.com/lochmueller/indexing/issues) if you have some more ideas or found a
bug.

## Developer information

The extension provide a framework for easy indexing pages and files. Use this documentation to get the right information
for your extension.

### Process

### Events

There are four 'public' events that you can use in your extension. EXT:index take care of the index processing and
the async handling of the queue. So you can directly consume the event and add your business logic.

- StartIndexProcess - Start event incl. meta information like technology, type
- IndexPageEvent - Index event for pages incl. title, content and meta information of one page
- IndexFileEvent - Index event for files incl. title, content and meta information of one file
- EndIndexProcess - End event incl. meta information like technology, type