<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Tests\Support;

use CmsProvenance\ProvenanceWikidata\Wikidata\WikidataClient;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * A WikidataClient whose HTTP requests get prepared answers instead of
 * reaching Wikidata. Tests using it set $resetSingletonInstances, because
 * the cache frontend creates a logger.
 */
final class FakeWikidata
{
    /** @var list<array{uri: string, options: array<string, mixed>}> */
    public array $requests = [];

    /**
     * @param list<ResponseInterface|\Throwable> $answers in the order of the requests
     */
    public function __construct(private array $answers) {}

    public static function fixture(string $name): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], (string)file_get_contents(__DIR__ . '/../Unit/Fixtures/' . $name));
    }

    public function client(TestCase $test): WikidataClient
    {
        $requestFactory = (fn() => $this->createStub(RequestFactory::class))->call($test);
        $requestFactory->method('request')->willReturnCallback(function (string $uri, string $method = 'GET', array $options = []): ResponseInterface {
            $this->requests[] = ['uri' => $uri, 'options' => $options];
            $answer = array_shift($this->answers) ?? throw new \LogicException('Unexpected request ' . $uri);
            if ($answer instanceof \Throwable) {
                throw $answer;
            }
            return $answer;
        });
        $extensionConfiguration = (fn() => $this->createStub(ExtensionConfiguration::class))->call($test);
        $extensionConfiguration->method('get')->willReturn([
            'contactUrl' => 'https://github.com/dkd-dobberkau/typo3-provenance-wikidata',
            'cacheLifetime' => '86400',
            'timeout' => '5',
        ]);

        return new WikidataClient($requestFactory, new VariableFrontend('test', new TransientMemoryBackend()), $extensionConfiguration);
    }
}
