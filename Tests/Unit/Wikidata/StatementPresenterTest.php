<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Tests\Unit\Wikidata;

use CmsProvenance\ProvenanceWikidata\Wikidata\StatementPresenter;
use CmsProvenance\ProvenanceWikidata\Tests\Support\FakeWikidata;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class StatementPresenterTest extends UnitTestCase
{
    private FakeWikidata $wikidata;

    #[Test]
    public function describesTheItem(): void
    {
        $result = $this->ashmolean('en');

        self::assertSame(
            ['id' => 'Q636400', 'label' => 'Ashmolean Museum', 'description' => 'museum in Oxford, England, UK', 'uri' => 'http://www.wikidata.org/entity/Q636400'],
            $result['item'],
        );
    }

    #[Test]
    public function dateStatementFillsTheValue(): void
    {
        $row = $this->row($this->ashmolean('en'), 'P571');

        self::assertSame('inception', $row['propertyLabel']);
        self::assertSame('1683', $row['value']);
        self::assertSame(['predicate' => 'foundingDate', 'objectLiteral' => '1683', 'objectUri' => '', 'sourceUrl' => ''], $row['fill']);
    }

    #[Test]
    public function itemStatementFillsTheValueAsUri(): void
    {
        $row = $this->row($this->ashmolean('en'), 'P112');

        self::assertSame('Elias Ashmole', $row['value']);
        self::assertSame('http://www.wikidata.org/entity/Q471406', $row['fill']['objectUri']);
        self::assertSame('Elias Ashmole', $row['fill']['objectLiteral']);
        self::assertSame('founder', $row['fill']['predicate']);
    }

    #[Test]
    public function referenceUrlBecomesTheEvidence(): void
    {
        $row = $this->row($this->ashmolean('en'), 'P112');

        self::assertStringStartsWith('http', $row['reference']);
        self::assertSame($row['reference'], $row['fill']['sourceUrl']);
    }

    #[Test]
    public function referenceWithoutUrlLeavesTheEvidenceEmpty(): void
    {
        // inception is only "imported from Wikimedia project" (P143)
        $row = $this->row($this->ashmolean('en'), 'P571');

        self::assertSame('', $row['reference']);
        self::assertSame('', $row['fill']['sourceUrl']);
    }

    #[Test]
    public function unmappedPropertyFillsThePropertyUri(): void
    {
        $row = $this->row($this->ashmolean('en'), 'P1436');

        self::assertSame('1000000', $row['value']);
        self::assertSame('http://www.wikidata.org/prop/direct/P1436', $row['fill']['predicate']);
    }

    #[Test]
    public function labelsComeInTheEditorsLanguage(): void
    {
        $result = $this->ashmolean('de');

        self::assertSame('Gründer', $this->row($result, 'P112')['propertyLabel']);
        self::assertSame('Oxford', $this->row($result, 'P131')['value'], 'no German label, so English');
    }

    #[Test]
    public function allLabelsAreFetchedInOneRequest(): void
    {
        $this->ashmolean('en');

        self::assertCount(2, $this->wikidata->requests);
    }

    #[Test]
    public function deprecatedStatementsAreLeftOutAndPreferredOnesComeFirst(): void
    {
        $rows = array_values(array_filter($this->edgeCases()['rows'], static fn(array $row): bool => $row['property'] === 'P571'));

        self::assertSame(['1683', '1684'], array_column($rows, 'value'));
        self::assertSame('https://example.org/history', $rows[0]['fill']['sourceUrl']);
    }

    #[Test]
    public function statementsWithoutAValueOrOfAnUnknownTypeAreLeftOut(): void
    {
        $properties = array_column($this->edgeCases()['rows'], 'property');

        self::assertNotContains('P112', $properties, 'somevalue and novalue');
        self::assertNotContains('P625', $properties, 'globe coordinate');
    }

    #[Test]
    public function identifiersAndMediaFilesAreLeftOut(): void
    {
        $properties = array_column($this->edgeCases()['rows'], 'property');

        self::assertNotContains('P214', $properties, 'external identifier');
        self::assertNotContains('P18', $properties, 'Commons media file');
        self::assertContains('P856', $properties, 'URLs stay');
    }

    #[Test]
    public function otherValuesAreShownAsText(): void
    {
        $result = $this->edgeCases();

        self::assertSame('12.5 metre', $this->row($result, 'P2048')['value']);
        self::assertSame('12.5 metre', $this->row($result, 'P2048')['fill']['objectLiteral']);
        self::assertSame('Ash', $this->row($result, 'P1813')['value']);
        self::assertSame('https://example.org/', $this->row($result, 'P856')['value']);
    }

    #[Test]
    public function missingLabelsFallBackToTheId(): void
    {
        $row = $this->row($this->edgeCases(), 'P1');

        self::assertSame('P1', $row['propertyLabel']);
        self::assertSame('Q2', $row['value']);
    }

    #[Test]
    public function itemWithoutDescriptionGetsAnEmptyOne(): void
    {
        self::assertSame('', $this->edgeCases()['item']['description']);
    }

    #[Test]
    public function unknownItemGivesNothing(): void
    {
        $this->wikidata = new FakeWikidata([new \GuzzleHttp\Psr7\Response(200, [], '{"entities": {"Q99999999": {"id": "Q99999999", "missing": ""}}}')]);

        self::assertNull((new StatementPresenter($this->wikidata->client($this)))->rows('Q99999999', 'en'));
    }

    /**
     * @return array{item: array<string, string>, rows: list<array<string, mixed>>}
     */
    private function ashmolean(string $language): array
    {
        $this->wikidata = new FakeWikidata([FakeWikidata::fixture('entities-Q636400.json'), FakeWikidata::fixture('labels.json')]);
        return (new StatementPresenter($this->wikidata->client($this)))->rows('Q636400', $language);
    }

    /**
     * @return array{item: array<string, string>, rows: list<array<string, mixed>>}
     */
    private function edgeCases(): array
    {
        $this->wikidata = new FakeWikidata([FakeWikidata::fixture('entities-edge-cases.json'), FakeWikidata::fixture('labels-edge-cases.json')]);
        return (new StatementPresenter($this->wikidata->client($this)))->rows('Q1', 'en');
    }

    /**
     * @param array{rows: list<array<string, mixed>>} $result
     * @return array<string, mixed> the first row of the property
     */
    private function row(array $result, string $property): array
    {
        foreach ($result['rows'] as $row) {
            if ($row['property'] === $property) {
                return $row;
            }
        }
        self::fail('No row for ' . $property);
    }
}
