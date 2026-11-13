<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Tests\Unit\Wikidata;

use CmsProvenance\ProvenanceWikidata\Wikidata\WikidataClient;
use CmsProvenance\ProvenanceWikidata\Wikidata\WikidataUnavailable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use CmsProvenance\ProvenanceWikidata\Tests\Support\FakeWikidata;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class WikidataClientTest extends UnitTestCase
{
    private FakeWikidata $wikidata;

    #[Test]
    public function searchReturnsItemsWithUri(): void
    {
        $client = $this->client([$this->fixture('search-ashmolean.json')]);

        $items = $client->searchItems('Ashmolean', 'en');

        self::assertSame(
            ['id' => 'Q636400', 'label' => 'Ashmolean Museum', 'description' => 'museum in Oxford, England, UK', 'uri' => 'http://www.wikidata.org/entity/Q636400'],
            $items[0],
        );
        self::assertStringContainsString('action=wbsearchentities', $this->wikidata->requests[0]['uri']);
        self::assertStringContainsString('search=Ashmolean', $this->wikidata->requests[0]['uri']);
    }

    #[Test]
    public function itemWithoutDescriptionGetsAnEmptyOne(): void
    {
        $items = $this->client([$this->fixture('search-ashmolean.json')])->searchItems('Ashmolean', 'en');

        self::assertSame('', $items[1]['description']);
    }

    #[Test]
    public function requestsNameTheContactUrlAndHaveATimeout(): void
    {
        $this->client([$this->fixture('search-ashmolean.json')])->searchItems('Ashmolean', 'en');

        self::assertSame(
            'provenance-wikidata/0.1 (https://github.com/dkd-dobberkau/typo3-provenance-wikidata)',
            $this->wikidata->requests[0]['options']['headers']['User-Agent'],
        );
        self::assertSame(5.0, $this->wikidata->requests[0]['options']['timeout']);
    }

    #[Test]
    public function answersAreCached(): void
    {
        $client = $this->client([$this->fixture('search-ashmolean.json')]);

        $client->searchItems('Ashmolean', 'en');
        $client->searchItems('Ashmolean', 'en');

        self::assertCount(1, $this->wikidata->requests);
    }

    #[Test]
    public function entitiesAreFetchedFiftyAtATime(): void
    {
        $empty = new Response(200, [], '{"entities": {}}');
        $client = $this->client([$empty, $empty, $empty]);

        $client->getEntities(array_map(static fn(int $n): string => 'Q' . $n, range(1, 120)), 'en');

        self::assertCount(3, $this->wikidata->requests);
        self::assertStringContainsString('languages=en', $this->wikidata->requests[0]['uri']);
    }

    #[Test]
    public function entitiesAreKeyedById(): void
    {
        $entities = $this->client([$this->fixture('entities-Q636400.json')])->getEntities(['Q636400'], 'en');

        self::assertSame('+1683-01-01T00:00:00Z', $entities['Q636400']['claims']['P571'][0]['mainsnak']['datavalue']['value']['time']);
    }

    #[Test]
    public function labelsFallBackToEnglishThenToTheId(): void
    {
        $labels = $this->client([$this->fixture('labels.json')])->getLabels(['Q471406', 'Q20986484', 'Q99999999'], 'de');

        self::assertSame(['Q471406' => 'Elias Ashmole', 'Q20986484' => 'Oxford', 'Q99999999' => 'Q99999999'], $labels);
        self::assertStringContainsString('languages=de%7Cen', $this->wikidata->requests[0]['uri']);
    }

    #[Test]
    public function serverErrorMeansUnavailable(): void
    {
        $this->expectException(WikidataUnavailable::class);

        $this->client([new Response(503, [], 'Service Unavailable')])->searchItems('Ashmolean', 'en');
    }

    #[Test]
    public function invalidJsonMeansUnavailable(): void
    {
        $this->expectException(WikidataUnavailable::class);

        $this->client([new Response(200, [], 'not json')])->searchItems('Ashmolean', 'en');
    }

    #[Test]
    public function apiErrorMeansUnavailable(): void
    {
        $this->expectException(WikidataUnavailable::class);

        $this->client([new Response(200, [], '{"error": {"code": "maxlag"}}')])->searchItems('Ashmolean', 'en');
    }

    #[Test]
    public function timeoutMeansUnavailable(): void
    {
        $this->expectException(WikidataUnavailable::class);

        $this->client([new ConnectException('timed out', new Request('GET', 'https://www.wikidata.org/'))])->searchItems('Ashmolean', 'en');
    }

    #[Test]
    public function failedAnswersAreNotCached(): void
    {
        $client = $this->client([new Response(503), $this->fixture('search-ashmolean.json')]);
        try {
            $client->searchItems('Ashmolean', 'en');
        } catch (WikidataUnavailable) {
        }

        self::assertSame('Q636400', $client->searchItems('Ashmolean', 'en')[0]['id']);
    }

    /**
     * @param list<ResponseInterface|\Throwable> $answers in the order of the requests
     */
    private function client(array $answers): WikidataClient
    {
        $this->wikidata = new FakeWikidata($answers);
        return $this->wikidata->client($this);
    }

    private function fixture(string $name): Response
    {
        return FakeWikidata::fixture($name);
    }
}
