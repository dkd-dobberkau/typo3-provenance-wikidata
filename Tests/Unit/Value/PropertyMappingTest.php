<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Tests\Unit\Value;

use CmsProvenance\ProvenanceWikidata\Value\PropertyMapping;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class PropertyMappingTest extends UnitTestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function properties(): array
    {
        return [
            'inception' => ['P571', 'foundingDate'],
            'publication date' => ['P577', 'datePublished'],
            'date of birth' => ['P569', 'birthDate'],
            'date of death' => ['P570', 'deathDate'],
            'founded by' => ['P112', 'founder'],
            'author' => ['P50', 'author'],
            'located in the administrative territorial entity' => ['P131', 'containedInPlace'],
            'location' => ['P276', 'location'],
            'father' => ['P22', 'parent'],
            'mother' => ['P25', 'parent'],
            'unmapped' => ['P1082', 'http://www.wikidata.org/prop/direct/P1082'],
        ];
    }

    #[Test]
    #[DataProvider('properties')]
    public function predicateForProperty(string $propertyId, string $predicate): void
    {
        self::assertSame($predicate, PropertyMapping::predicateFor($propertyId));
    }
}
