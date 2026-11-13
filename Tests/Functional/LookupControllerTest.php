<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Tests\Functional;

use CmsProvenance\ProvenanceWikidata\Backend\LookupController;
use CmsProvenance\ProvenanceWikidata\Tests\Support\FakeWikidata;
use CmsProvenance\ProvenanceWikidata\Wikidata\StatementPresenter;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class LookupControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cms-provenance/provenance', 'cms-provenance/provenance-facts', 'cms-provenance/provenance-wikidata'];
    protected array $coreExtensionsToLoad = ['rte_ckeditor'];

    private FakeWikidata $wikidata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BackendUsers.csv');
    }

    #[Test]
    public function routesReachTheControllerFromTheContainer(): void
    {
        self::assertInstanceOf(LookupController::class, $this->get(LookupController::class));
    }

    #[Test]
    public function searchFindsItems(): void
    {
        $this->setUpBackendUser(1);
        $response = $this->search('Ashmolean', [FakeWikidata::fixture('search-ashmolean.json')]);

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        self::assertSame('Q636400', $this->json($response)['items'][0]['id']);
        self::assertStringContainsString('language=en', $this->wikidata->requests[0]['uri'], 'backend language "default" is English');
    }

    #[Test]
    public function searchUsesTheEditorsLanguage(): void
    {
        $this->setUpBackendUser(3);
        $this->search('Ashmolean', [FakeWikidata::fixture('search-ashmolean.json')]);

        self::assertStringContainsString('language=de', $this->wikidata->requests[0]['uri']);
    }

    #[Test]
    public function usersWhoMayNotEditContentAreRefused(): void
    {
        $this->setUpBackendUser(2);
        $response = $this->search('Ashmolean', []);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame([], $this->wikidata->requests);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'too long' => [str_repeat('a', 300)],
        ];
    }

    #[Test]
    #[DataProvider('invalidQueries')]
    public function invalidQueriesAreRejectedWithoutAskingWikidata(string $query): void
    {
        $this->setUpBackendUser(1);
        $response = $this->search($query, []);

        self::assertSame(400, $response->getStatusCode());
        self::assertNotSame('', $this->json($response)['error']);
        self::assertSame([], $this->wikidata->requests);
    }

    #[Test]
    public function statementsOfAnItem(): void
    {
        $this->setUpBackendUser(1);
        $response = $this->statements('Q636400', [FakeWikidata::fixture('entities-Q636400.json'), FakeWikidata::fixture('labels.json')]);

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $result = $this->json($response);
        self::assertSame('Ashmolean Museum', $result['item']['label']);
        self::assertContains('foundingDate', array_column(array_column($result['rows'], 'fill'), 'predicate'));
    }

    #[Test]
    public function invalidItemIsRejectedWithoutAskingWikidata(): void
    {
        $this->setUpBackendUser(1);
        $response = $this->statements('Q12; DROP', []);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([], $this->wikidata->requests);
    }

    #[Test]
    public function unknownItemIsNotFound(): void
    {
        $this->setUpBackendUser(1);
        $response = $this->statements('Q99999999', [new Response(200, [], '{"entities": {"Q99999999": {"id": "Q99999999", "missing": ""}}}')]);

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function unreachableWikidataIsReported(): void
    {
        $this->setUpBackendUser(1);
        $response = $this->search('Ashmolean', [new Response(503)]);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('Wikidata is not reachable right now.', $this->json($response)['error']);
    }

    /**
     * @param list<ResponseInterface|\Throwable> $answers
     */
    private function search(string $query, array $answers): ResponseInterface
    {
        return $this->controller($answers)->searchAction(
            (new ServerRequest('https://example.org/typo3/ajax/provenance-wikidata/search'))->withQueryParams(['query' => $query]),
        );
    }

    /**
     * @param list<ResponseInterface|\Throwable> $answers
     */
    private function statements(string $item, array $answers): ResponseInterface
    {
        return $this->controller($answers)->statementsAction(
            (new ServerRequest('https://example.org/typo3/ajax/provenance-wikidata/statements'))->withQueryParams(['item' => $item]),
        );
    }

    /**
     * @param list<ResponseInterface|\Throwable> $answers
     */
    private function controller(array $answers): LookupController
    {
        $this->wikidata = new FakeWikidata($answers);
        $client = $this->wikidata->client($this);

        return new LookupController($client, new StatementPresenter($client));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
