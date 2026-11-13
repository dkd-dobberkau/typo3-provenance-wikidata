# Content Provenance: Wikidata

Research Content Provenance facts in Wikidata from the fact dialog of the rich
text editor.

Optional editorial aid for the TYPO3 implementation of the
[Content Provenance Specification](https://github.com/dkd-dobberkau/content-provenance-spec).
It does not add to any conformance level. Requires the facts extension
[`provenance_facts`](https://github.com/dkd-dobberkau/typo3-provenance-facts).

> **Status: experimental, pre-release.** Nothing here is ready for production
> use.

## How it works

Editors select words in the rich text editor and press **Fact**. With this
extension the dialog gets a panel **Look up in Wikidata**:

1. Search for the item the fact is about, for example "Ashmolean". Picking a
   result sets the subject to its Wikidata URI
   (`http://www.wikidata.org/entity/Q636400`).
2. The item's statements are listed with their values and references.
3. **Use** on a statement fills predicate, value and evidence. Properties with
   a schema.org counterpart get the schema.org term (`foundingDate`), others
   the Wikidata property URI. Evidence is the reference URL Wikidata gives for
   the statement, if any.

Every field stays editable. The editor decides what goes into the fact.

What the extension does not do:

- It never contacts Wikidata while a page is rendered or signed. Pages, the
  signature and the audit endpoint do not depend on Wikidata.
- It writes nothing into the provenance record beyond the fields the editor
  accepts.
- The browser never talks to Wikidata. TYPO3 asks Wikidata on the editor's
  behalf, with a User-Agent that names the site's contact URL, and caches the
  answers.

## Requirements

- TYPO3 13.4 LTS or 14
- PHP 8.2 or later
- `provenance_facts` with the RTE preset `provenance` (or the Fact button in
  your own preset)
- Outgoing HTTPS from the TYPO3 server to `www.wikidata.org`

## Installation

Not yet released. Once a first version is tagged:

```bash
composer require cms-provenance/provenance-wikidata
```

Extension key: [`provenance_wikidata`](https://extensions.typo3.org/extension/provenance_wikidata)

## Configuration

Extension settings (Admin Tools > Settings > Extension Configuration):

- `contactUrl`: sent in the User-Agent of every request, as the
  [Wikimedia User-Agent policy](https://foundation.wikimedia.org/wiki/Policy:User-Agent_policy)
  asks. Set it to a page or address where you can be reached.
- `cacheLifetime`: seconds Wikidata answers are kept (default one day).
- `timeout`: seconds to wait for Wikidata (default 5).

## Links

- Project site: <https://cms-provenance.org>
- Specification: <https://github.com/dkd-dobberkau/content-provenance-spec>
- Conformance test suite, including `check_wikidata.py`: <https://github.com/dkd-dobberkau/content-provenance-conformance>

## Documentation

Installation, configuration, the editorial guide and verification are
documented in the [manual of the core extension](https://github.com/dkd-dobberkau/typo3-provenance/blob/main/Documentation/Index.rst).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
