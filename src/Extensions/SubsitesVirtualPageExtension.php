<?php

namespace Wilr\SilverStripe\Algolia\Extensions;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\Map;
use SilverStripe\Subsites\Model\Subsite;
use Wilr\SilverStripe\Algolia\Service\AlgoliaIndexer;

class SubsitesVirtualPageExtension extends \SilverStripe\ORM\DataExtension
{
    public function exportObjectToAlgolia($toIndex)
    {
        $attributes = new Map(ArrayList::create());

        foreach ($toIndex as $k => $v) {
            if ($k === 'objectClassName') {
                continue;
            }

            $attributes->push($k, $v);
        }

        /** @var AlgoliaIndexer  */
        $indexer = Injector::inst()->get(AlgoliaIndexer::class);
        $owner = $this->owner;

        // get original object
        $result = Subsite::withDisabledSubsiteFilter(function () use ($owner, $attributes, $indexer) {
            $originalObject = $owner->CopyContentFrom();

            if (!$originalObject) {
                return $attributes;
            }

            $attributes->push('objectClassName', $originalObject->ClassName);

            $specs = $originalObject->config()->get('algolia_index_fields');
            $attributes = $indexer->addSpecsToAttributes($originalObject, $attributes, $specs);

            $originalObject->invokeWithExtensions('updateAlgoliaAttributes', $attributes);

            return $attributes;
        });

        $attributes->push('SubsiteID', $this->owner->SubsiteID);

        return $result;
    }
}
