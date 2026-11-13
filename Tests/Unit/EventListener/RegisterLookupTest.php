<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Tests\Unit\EventListener;

use CmsProvenance\ProvenanceWikidata\EventListener\RegisterLookup;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class RegisterLookupTest extends UnitTestCase
{
    private const MODULE = '@cms-provenance/provenance-wikidata/wikidata-lookup.js';

    #[Test]
    public function addsModuleToEditorConfiguration(): void
    {
        $event = new AfterPrepareConfigurationForEditorEvent([], []);

        (new RegisterLookup())($event);

        self::assertSame([self::MODULE], $event->getConfiguration()['provenanceFact']['lookups']);
    }

    #[Test]
    public function keepsExistingLookups(): void
    {
        $event = new AfterPrepareConfigurationForEditorEvent(['provenanceFact' => ['lookups' => ['other.js']]], []);

        (new RegisterLookup())($event);
        (new RegisterLookup())($event);

        self::assertSame(['other.js', self::MODULE], $event->getConfiguration()['provenanceFact']['lookups']);
    }
}
