<?php

namespace Wilr\SilverStripe\Algolia\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DataObjectSchema;
use Wilr\SilverStripe\Algolia\Service\AlgoliaQuerier;
use Wilr\SilverStripe\Algolia\Service\AlgoliaService;
use Wilr\SilverStripe\Algolia\Extensions\AlgoliaObjectExtension;

class AlgoliaQuerierTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        AlgoliaTestObject::class
    ];

    protected static $required_extensions = [
        AlgoliaTestObject::class => [
            AlgoliaObjectExtension::class
        ]
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // mock AlgoliaService
        Injector::inst()->get(DataObjectSchema::class)->reset();
        Injector::inst()->registerService(new TestAlgoliaService(), AlgoliaService::class);
    }

    public function testFetchResults()
    {
        $results = Injector::inst()->get(AlgoliaQuerier::class)->fetchResults('indexName', 'search keywords');

        $this->assertInstanceOf(PaginatedList::class, $results);
    }
}
