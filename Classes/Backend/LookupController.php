<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\Backend;

use CmsProvenance\ProvenanceWikidata\Wikidata\StatementPresenter;
use CmsProvenance\ProvenanceWikidata\Wikidata\WikidataClient;
use CmsProvenance\ProvenanceWikidata\Wikidata\WikidataUnavailable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * Backend AJAX routes behind the Wikidata panel of the fact dialog. Only
 * people who may edit content elements, and so open the dialog, may use
 * them; the browser never talks to Wikidata itself.
 */
#[AsController]
final class LookupController
{
    private const MAX_QUERY_LENGTH = 200;
    private const ITEM_ID = '/^Q[1-9]\d{0,11}$/';

    public function __construct(
        private readonly WikidataClient $client,
        private readonly StatementPresenter $presenter,
    ) {}

    public function searchAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->mayEditContent()) {
            return $this->error('You may not edit content elements.', 403);
        }
        $query = trim((string)($request->getQueryParams()['query'] ?? ''));
        if ($query === '' || mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            return $this->error('Enter between 1 and ' . self::MAX_QUERY_LENGTH . ' characters.', 400);
        }

        return $this->ask(fn(): array => ['items' => $this->client->searchItems($query, $this->language())]);
    }

    public function statementsAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->mayEditContent()) {
            return $this->error('You may not edit content elements.', 403);
        }
        $item = (string)($request->getQueryParams()['item'] ?? '');
        if (preg_match(self::ITEM_ID, $item) !== 1) {
            return $this->error('Not a Wikidata item, for example Q636400.', 400);
        }

        return $this->ask(fn(): ?array => $this->presenter->rows($item, $this->language()), 'Wikidata has no item ' . $item . '.');
    }

    /**
     * @param \Closure(): ?array<string, mixed> $question
     */
    private function ask(\Closure $question, string $notFound = ''): ResponseInterface
    {
        try {
            $answer = $question();
        } catch (WikidataUnavailable) {
            return $this->error('Wikidata is not reachable right now.', 503);
        }

        return $answer === null ? $this->error($notFound, 404) : new JsonResponse($answer);
    }

    private function mayEditContent(): bool
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        return $backendUser instanceof BackendUserAuthentication && $backendUser->check('tables_modify', 'tt_content');
    }

    /**
     * The backend user's language as Wikidata names it; TYPO3's "default" is English.
     */
    private function language(): string
    {
        $language = (string)($GLOBALS['BE_USER']->user['lang'] ?? '');

        return preg_match('/^[a-z]{2,3}(-[a-z]+)?$/i', $language) === 1 ? strtolower($language) : 'en';
    }

    private function error(string $message, int $status): ResponseInterface
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
