<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Tests\Unit\Value;

use CmsProvenance\ProvenanceWikidata\Value\WikidataTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class WikidataTimeTest extends UnitTestCase
{
    /**
     * @return array<string, array{string, int, string, string}>
     */
    public static function times(): array
    {
        return [
            'year' => ['+1683-01-01T00:00:00Z', 9, '1683', '1683'],
            'day' => ['+1793-08-10T00:00:00Z', 11, '10 August 1793', '1793-08-10'],
            'month' => ['+1793-08-00T00:00:00Z', 10, 'August 1793', '1793-08'],
            'year BCE' => ['-0530-01-01T00:00:00Z', 9, '530 BCE', '530 BCE'],
            'year BCE without month' => ['-0550-00-00T00:00:00Z', 9, '550 BCE', '550 BCE'],
            'century, as Wikidata formats it' => ['+1700-00-00T00:00:00Z', 7, '17th century', '17th century'],
            'century from a year inside it' => ['+1750-00-00T00:00:00Z', 7, '18th century', '18th century'],
            'decade' => ['+1650-00-00T00:00:00Z', 8, '1650s', '1650s'],
            'first century' => ['+0050-00-00T00:00:00Z', 7, '1st century', '1st century'],
            'twenty-first century' => ['+2001-00-00T00:00:00Z', 7, '21st century', '21st century'],
            'century BCE' => ['-0250-00-00T00:00:00Z', 7, '3rd century BCE', '3rd century BCE'],
            'millennium, as Wikidata formats it' => ['+2000-00-00T00:00:00Z', 6, '2nd millennium', '2nd millennium'],
            'millennium BCE' => ['-3800-00-00T00:00:00Z', 6, '4th millennium BCE', '4th millennium BCE'],
            'ten thousand years' => ['-10000-00-00T00:00:00Z', 5, '10000 years BCE', '10000 years BCE'],
            'billions of years' => ['-13798000000-00-00T00:00:00Z', 0, '14 billion years BCE', '14 billion years BCE'],
        ];
    }

    #[Test]
    #[DataProvider('times')]
    public function formatsAtItsPrecision(string $time, int $precision, string $display, string $factValue): void
    {
        $value = WikidataTime::fromValue(['time' => $time, 'precision' => $precision]);

        self::assertSame($display, $value->display());
        self::assertSame($factValue, $value->factValue());
    }

    #[Test]
    public function rejectsValuesThatAreNoTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WikidataTime::fromValue(['time' => 'yesterday', 'precision' => 11]);
    }
}
