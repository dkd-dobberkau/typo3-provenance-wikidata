<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Wikidata;

use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * The Wikidata Action API, as the Wikimedia API etiquette asks: a User-Agent
 * with a contact, a short timeout, and every answer cached. Only backend
 * requests use it; nothing in rendering or signing depends on Wikidata.
 */
final class WikidataClient
{
    private const API = 'https://www.wikidata.org/w/api.php';
    private const ENTITY = 'http://www.wikidata.org/entity/';
    // wbgetentities accepts at most 50 ids per request.
    private const IDS_PER_REQUEST = 50;

    public function __construct(
        private readonly RequestFactory $requestFactory,
        #[Autowire(service: 'cache.provenance_wikidata')]
        private readonly FrontendInterface $cache,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * @return list<array{id: string, label: string, description: string, uri: string}>
     */
    public function searchItems(string $query, string $language): array
    {
        $answer = $this->query([
            'action' => 'wbsearchentities',
            'search' => $query,
            'language' => $language,
            'uselang' => $language,
            'type' => 'item',
            'limit' => 10,
        ]);

        return array_map(static fn(array $item): array => [
            'id' => (string)$item['id'],
            'label' => (string)($item['label'] ?? $item['id']),
            'description' => (string)($item['description'] ?? ''),
            'uri' => self::ENTITY . $item['id'],
        ], array_values(array_filter($answer['search'] ?? [], 'is_array')));
    }

    /**
     * Labels, descriptions and statements, with labels in $language and English.
     *
     * @param list<string> $ids
     * @return array<string, array<string, mixed>> by id
     */
    public function getEntities(array $ids, string $language): array
    {
        return $this->entities($ids, 'labels|descriptions|claims', $language);
    }

    /**
     * @param list<string> $ids items (Q…) or properties (P…)
     * @return array<string, string> the label in $language, else in English, else the id
     */
    public function getLabels(array $ids, string $language): array
    {
        $entities = $this->entities($ids, 'labels', $language);
        $labels = [];
        foreach ($ids as $id) {
            $entityLabels = $entities[$id]['labels'] ?? [];
            $labels[$id] = (string)($entityLabels[$language]['value'] ?? $entityLabels['en']['value'] ?? $id);
        }

        return $labels;
    }

    /**
     * @param list<string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function entities(array $ids, string $props, string $language): array
    {
        $entities = [];
        foreach (array_chunk(array_values(array_unique($ids)), self::IDS_PER_REQUEST) as $chunk) {
            $answer = $this->query([
                'action' => 'wbgetentities',
                'ids' => implode('|', $chunk),
                'props' => $props,
                'languages' => implode('|', array_unique([$language, 'en'])),
            ]);
            foreach ($answer['entities'] ?? [] as $id => $entity) {
                if (is_array($entity) && !isset($entity['missing'])) {
                    $entities[(string)$id] = $entity;
                }
            }
        }

        return $entities;
    }

    /**
     * @param array<string, string|int> $parameters
     * @return array<string, mixed>
     */
    private function query(array $parameters): array
    {
        $uri = self::API . '?' . http_build_query($parameters + ['format' => 'json'], '', '&', PHP_QUERY_RFC3986);
        $cacheIdentifier = sha1($uri);
        $cached = $this->cache->get($cacheIdentifier);
        if (is_array($cached)) {
            return $cached;
        }

        $settings = (array)$this->extensionConfiguration->get('provenance_wikidata');
        try {
            $response = $this->requestFactory->request($uri, 'GET', [
                'headers' => ['User-Agent' => 'provenance-wikidata/0.1 (' . ($settings['contactUrl'] ?? '') . ')', 'Accept' => 'application/json'],
                'timeout' => (float)($settings['timeout'] ?? 5),
                'http_errors' => false,
            ]);
        } catch (GuzzleException $exception) {
            throw new WikidataUnavailable('Wikidata did not answer: ' . $exception->getMessage(), 1794700101, $exception);
        }
        if ($response->getStatusCode() !== 200) {
            throw new WikidataUnavailable('Wikidata answered with HTTP ' . $response->getStatusCode(), 1794700102);
        }
        try {
            $answer = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new WikidataUnavailable('Wikidata did not answer with JSON', 1794700103, $exception);
        }
        if (!is_array($answer) || isset($answer['error'])) {
            throw new WikidataUnavailable('Wikidata reported an error: ' . ($answer['error']['code'] ?? 'unknown'), 1794700104);
        }

        $this->cache->set($cacheIdentifier, $answer, [], (int)($settings['cacheLifetime'] ?? 86400));
        return $answer;
    }
}
