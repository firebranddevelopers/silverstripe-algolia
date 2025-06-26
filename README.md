# :mag: Silverstripe Algolia Module

[![codecov](https://codecov.io/gh/wilr/silverstripe-algolia/branch/master/graph/badge.svg)](https://codecov.io/gh/wilr/silverstripe-algolia)
[![Version](http://img.shields.io/packagist/v/wilr/silverstripe-algolia.svg?style=flat-square)](https://packagist.org/packages/wilr/silverstripe-algolia)
[![License](http://img.shields.io/packagist/l/wilr/silverstripe-algolia.svg?style=flat-square)](LICENSE)

## Maintainer Contact

-   Will Rossiter (@wilr) <will@fullscreen.io>

## Installation

```sh
composer require "wilr/silverstripe-algolia"
```

## Features

:ballot_box_with_check: Supports multiple indexes and saving records into
multiple indexes.

:ballot_box_with_check: Integrates into existing versioned workflow.

:ballot_box_with_check: No dependencies on the CMS, supports any DataObject
subclass.

:ballot_box_with_check: Queued job support for offloading operations to Algolia.

:ballot_box_with_check: Easily configure search configuration and indexes via
YAML and PHP.

:ballot_box_with_check: Indexes your webpage template so supports Elemental and
custom fields out of the box

## Documentation

Algolia’s search-as-a-service and full suite of APIs allow teams to easily
develop tailored, fast Search and Discovery experiences that delight and
convert.

This module adds the ability to sync Silverstripe pages to a Algolia Index.

Indexing and removing documents is done transparently for any objects which
subclass `SiteTree` or by applying the
`Wilr\SilverStripe\Algolia\Extensions\AlgoliaObjectExtension` to your
DataObjects.

## :hammer_and_wrench: Setting Up

First, sign up for Algolia.com account and install this module. Once installed,
Configure the API keys via YAML (environment variables recommended).

_app/\_config/algolia.yml_

```yml
---
Name: algolia
After: silverstripe-algolia
---
SilverStripe\Core\Injector\Injector:
    Wilr\SilverStripe\Algolia\Service\AlgoliaService:
        properties:
            adminApiKey: "`ALGOLIA_ADMIN_API_KEY`"
            searchApiKey: "`ALGOLIA_SEARCH_API_KEY`"
            applicationId: "`ALGOLIA_SEARCH_APP_ID`"
            indexes:
                IndexName:
                    includeClasses:
                        - SilverStripe\CMS\Model\SiteTree
                    indexSettings:
                        attributesForFaceting:
                            - "filterOnly(objectClassName)"
```

Once the indexes and API keys are configured, run a `dev/build` to update the
database and refresh the indexSettings. Alternatively you can run
`AlgoliaConfigure` to manually rebuild the indexSettings.

### Configuring the index names

This module will assume your indexes are setup as `dev_{IndexName}`,
`test_{IndexName}` and `live_{IndexName}` where the result of your environment
type is prefixed to the names listed in the main YAML config.

If you explictly want to disable the environment prefix (or use a custom
approach) use the `ALGOLIA_PREFIX_INDEX_NAME` environment variable.

```yml
ALGOLIA_PREFIX_INDEX_NAME='dev_will'
```

Or for testing with live data on dev use `ALGOLIA_PREFIX_INDEX_NAME='live'`

### Defining Replica Indexes

If your search form provides a sort option (e.g latest or relevance) then you
will be using replica indexes
(https://www.algolia.com/doc/guides/managing-results/refine-results/sorting/how-to/creating-replicas/)

These can be defined using the same YAML configuration.

```yml
---
Name: algolia
After: silverstripe-algolia
---
SilverStripe\Core\Injector\Injector:
    Wilr\SilverStripe\Algolia\Service\AlgoliaService:
        properties:
            adminApiKey: "`ALGOLIA_ADMIN_API_KEY`"
            searchApiKey: "`ALGOLIA_SEARCH_API_KEY`"
            applicationId: "`ALGOLIA_SEARCH_APP_ID`"
            indexes:
                IndexName:
                    includeClasses:
                        - SilverStripe\CMS\Model\SiteTree
                    indexSettings:
                        attributesForFaceting:
                            - "filterOnly(ObjectClassName)"
                        replicas:
                            - IndexName_Latest
                IndexName_Latest:
                    indexSettings:
                        ranking:
                            - "desc(objectCreated)"
                            - "typo"
                            - "words"
                            - "filters"
                            - "proximity"
                            - "attribute"
                            - "exact"
                            - "custom"
```

## Indexing

If installing on a existing website run the `AlgoliaReindex` task (via CLI) to
import existing data. This will batch import all the records from your database
into the indexes configured above.

```sh
./vendor/bin/sake algolia:configure
./vendor/bin/sake algolia:index
```

Individually records will be indexed automatically going forward via the
`onAfterPublish` hook and removed via the `onAfterUnpublish` hook which is
called when publishing or unpublishing a document. If your DataObject does not
implement the `Versioned` extension you'll need to manage this state yourself by
calling `$item->indexInAlgolia()` and `$item->removeFromAlgolia()`.

`AlgoliaReindex` takes a number of arguments to allow for customisation of bulk
indexing. For instance, if you have a large amount of JobVacancies to bulk
import but only need the active ones you can trigger the task as follows:

```sh
/vendor/bin/sake algolia:index --onlyClass="onlyClass=Vacancy" --filter="ExpiryDate>NOW()"
```

If you do not have access to a CLI (i.e Silverstripe Cloud) then you can also
bulk reindex via a queued job `AlgoliaReindexAllJob`.

### Optional

`force` forces every Silverstripe record to be re-synced.

```sh
./vendor/bin/sake algolia:index --force
```

`clear` truncates the search index before re-indexing.

```sh
./vendor/bin/sake algolia:index --clear
```

### Customising the indexed attributes (fields)

By default only `ID`, `Title` and `Link`, `LastEdited` will be indexed from each
record. To specify additional fields, define a `algolia_index_fields` config
variable.

```php
class MyPage extends Page {
    // ..
    private static $algolia_index_fields = [
        'Content',
        'MyCustomColumn',
        'RelationshipName'
    ];
}
```

Or, you can define a `exportObjectToAlgolia` method on your object. This
receives the default index fields and then allows you to add or remove fields as
required

```php
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\Map;

class MyPage extends Page {

    public function exportObjectToAlgolia($data)
    {
        $data = array_merge($data, [
            'MyCustomField' => $this->MyCustomField()
        ]);

        $map = new Map(ArrayList::create());

        foreach ($data as $k => $v) {
            $map->push($k, $v);
        }

        return $map;
    }
}
```

### Customizing the indexed relationships

Out of the box, the default is to push the ID and Title fields of any
relationships (`$has_one`, `$has_many`, `$many_many`) into a field
`relation{name}` with the record `ID` and `Title` as per the behaviour with
records.

Additional fields from the relationship can be indexed via a PHP function

```php
public function updateAlgoliaRelationshipAttributes(\SilverStripe\ORM\Map $attributes, $related)
{
    $attributes->push('CategoryName', $related->CategoryName);
}
```

### Excluding an object from indexing

Objects can define a `canIndexInAlgolia` method which should return false if the
object should not be indexed in algolia.

```php
public function canIndexInAlgolia(): bool
{
    return ($this->Expired) ? false : true;
}
```

### Queued Indexing

To reduce the impact of waiting on a third-party service while publishing
changes, this module utilizes the `queued-jobs` module for uploading index
operations. The queuing feature can be disabled via the Config YAML.

```yaml
Wilr\SilverStripe\Algolia\Extensions\AlgoliaObjectExtension:
    use_queued_indexing: false
```

## Displaying and fetching results

For your website front-end you can use InstantSearch.js libraries if you wish,
or to fetch a `PaginatedList` of results from Algolia, create a method on your
`Controller` subclass to call `Wilr\SilverStripe\Algolia\Service\AlgoliaQuerier`

```php
<?php

use SilverStripe\Core\Injector\Injector;
use Wilr\SilverStripe\Algolia\Service\AlgoliaQuerier;

class PageController extends ContentController
{
    public function results()
    {
        $hitsPerPage = 25;
        $paginatedPageNum = floor($this->request->getVar('start') / $hitsPerPage);

        $results = Injector::inst()->get(AlgoliaQuerier::class)->fetchResults(
            'indexName',
            $this->request->getVar('search'), [
                'page' => $this->request->getVar('start') ? $paginatedPageNum : 0,
                'hitsPerPage' => $hitsPerPage
            ]
        );

        return [
            'Title' => 'Search Results',
            'Results' => $results
        ];
    }
}
```

Or alternatively you can make use of JS Search SDK
(https://www.algolia.com/doc/api-client/getting-started/install/javascript/)

## :mag: Inspect Object Fields

To assist with debugging what fields will be pushed into Algolia and see what
information is already in Algolia use the `AlgoliaInspect` BuildTask. This can
be run via CLI

```
./vendor/bin/sake dev/tasks/AlgoliaInspect "class=Page&id=1"
```

Will output the Algolia data structure for the Page with the ID of '1'.

## Elemental Support

Out of the box this module scrapes the webpage's `main` HTML section and stores
it in a `objectForTemplate` field in Algolia. This content is parsed via the
`AlgoliaPageCrawler` class.

```html
<main>
    $ElementalArea
    <!-- will be indexed via Algolia -->
</main>
```

If this behaviour is undesirable then it can be disabled via YAML.

```
Wilr\SilverStripe\Algolia\Service\AlgoliaIndexer:
  include_page_content: false
```

Or you can specify the HTML selector you do want to index using YAML. For
instance to index any elements with a `data-index` attribute.

```
Wilr\SilverStripe\Algolia\Service\AlgoliaPageCrawler:
  content_xpath_selector: '//[data-index]'
```

## Subsite support

If you use the Silverstripe Subsite module to run multiple websites you can
handle indexing in a couple ways:

-   Use separate indexes per site.
-   Use a single index, but add a `SubsiteID` field in Algolia.

The decision to go either way depends on the nature of the websites and how
related they are but separate indexes are highly recommended to prevent leaking
information between websites and mucking up analytics and query suggestions.

### Subsite support with a single index

If subsites are frequently being created then you may choose to prefer a single
index since index names need to be controlled via YAML so any new subsite would
require a code change.

The key to this approach is added `SubsiteID` to the attributes for faceting
and at the query time.

Step 1. Add the field to Algolia

```yml
SilverStripe\Core\Injector\Injector:
  Wilr\SilverStripe\Algolia\Service\AlgoliaService:
    properties:
      adminApiKey: "`ALGOLIA_ADMIN_API_KEY`"
      searchApiKey: "`ALGOLIA_SEARCH_API_KEY`"
      applicationId: "`ALGOLIA_SEARCH_APP_ID`"
      indexes:
        index_main_site:
          includeClasses:
            - SilverStripe\CMS\Model\SiteTree
          indexSettings:
            distinct: true
            attributeForDistinct: "objectLink"
            searchableAttributes:
              - objectTitle
              - objectContent
              - objectLink
              - Summary
              - objectForTemplate
            attributesForFaceting:
              - "filterOnly(objectClassName)"
              ***- "filterOnly(SubsiteID)"***
```

Step 2. Expose the field on `SiteTree` via a DataExtension (make sure to apply the extension)

```php
class SiteTreeExtension extends DataExtension
{
    private static $algolia_index_fields = [
        'SubsiteID'
    ];
}
```

Step 3. Filter by the Subsite ID in your results

```php
use SilverStripe\Core\Injector\Injector;
use Wilr\SilverStripe\Algolia\Service\AlgoliaQuerier;

class PageController extends ContentController
{
    public function results()
    {
        $hitsPerPage = 25;
        $paginatedPageNum = floor($this->request->getVar('start') / $hitsPerPage);

        $results = Injector::inst()->get(AlgoliaQuerier::class)->fetchResults(
            'indexName',
            $this->request->getVar('search'), [
                'page' => $this->request->getVar('start') ? $paginatedPageNum : 0,
                'hitsPerPage' => $hitsPerPage,
                'facetFilters' => [
                    'SubsiteID' => SubsiteState::singleton()->getSubsiteId()
                ]
            ]
        );

        return [
            'Title' => 'Search Results',
            'Results' => $results
        ];
    }
}
```

### Subsite support with separate indexes

Create multiple indexes in your config and use the `includeFilter` parameter to
filter the records per index.

The `includeFilter` should be in the format `{$Class}`: `{$WhereQuery}` where
the `$WhereQuery` is a basic SQL statement performed by the ORM on the given
class.

```yml
SilverStripe\Core\Injector\Injector:
    Wilr\SilverStripe\Algolia\Service\AlgoliaService:
        properties:
            adminApiKey: "`ALGOLIA_ADMIN_API_KEY`"
            searchApiKey: "`ALGOLIA_SEARCH_API_KEY`"
            applicationId: "`ALGOLIA_SEARCH_APP_ID`"
            indexes:
                index_main_site:
                    includeClasses:
                        - SilverStripe\CMS\Model\SiteTree
                    includeFilter:
                        "SilverStripe\\CMS\\Model\\SiteTree": "SubsiteID = 0"
                    indexSettings:
                        distinct: true
                        attributeForDistinct: "objectLink"
                        searchableAttributes:
                            - objectTitle
                            - objectContent
                            - objectLink
                            - Summary
                            - objectForTemplate
                        attributesForFaceting:
                            - "filterOnly(objectClassName)"
                index_subsite_pages:
                    includeClasses:
                        - SilverStripe\CMS\Model\SiteTree
                    includeFilter:
                        "SilverStripe\\CMS\\Model\\SiteTree": "SubsiteID > 0"
                    indexSettings:
                        distinct: true
                        attributeForDistinct: "objectLink"
                        searchableAttributes:
                            - objectTitle
                            - objectContent
                            - objectLink
                            - Summary
                            - objectForTemplate
                        attributesForFaceting:
                            - "filterOnly(objectClassName)"
```
