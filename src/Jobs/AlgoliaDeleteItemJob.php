<?php

namespace Wilr\Silverstripe\Algolia\Jobs;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Throwable;
use Wilr\SilverStripe\Algolia\Service\AlgoliaIndexer;

/**
 * Remove an item from Algolia async. This method works well
 * for performance and batching large operations
 */
class AlgoliaDeleteItemJob extends AbstractQueuedJob implements QueuedJob
{
    /**
     * @param string $itemClass
     * @param int    $itemUUID
     */
    public function __construct($itemClass = null, $itemUUID = null)
    {
        $this->itemClass = $itemClass;
        $this->itemUUID = $itemUUID;
    }


    /**
     * Defines the title of the job.
     *
     * @return string
     */
    public function getTitle()
    {
        return sprintf(
            'Algolia remove object %s',
            $this->itemUUID
        );
    }

    /**
     * @return int
     */
    public function getJobType()
    {
        $this->totalSteps = 1;

        return QueuedJob::IMMEDIATE;
    }

    public function process()
    {
        try {
            $indexer = Injector::inst()->create(AlgoliaIndexer::class);
            $indexer->deleteItem($this->itemClass, $this->itemUUID);
        } catch (Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->error($e);
        }

        $this->isComplete = true;

        return;
    }
}
