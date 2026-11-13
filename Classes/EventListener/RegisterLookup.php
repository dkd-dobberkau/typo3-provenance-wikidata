<?php

declare(strict_types=1);

namespace CmsProvenance\ProvenanceWikidata\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;

/**
 * Adds the Wikidata lookup to the fact dialog of every rich text editor, so
 * no RTE preset has to be changed. The dialog only exists where the Fact
 * button of provenance_facts is configured.
 */
#[AsEventListener('provenance-wikidata/register-lookup')]
final class RegisterLookup
{
    private const MODULE = '@cms-provenance/provenance-wikidata/wikidata-lookup.js';

    public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
    {
        $configuration = $event->getConfiguration();
        $lookups = $configuration['provenanceFact']['lookups'] ?? [];
        if (!in_array(self::MODULE, $lookups, true)) {
            $lookups[] = self::MODULE;
        }
        $configuration['provenanceFact']['lookups'] = $lookups;
        $event->setConfiguration($configuration);
    }
}
