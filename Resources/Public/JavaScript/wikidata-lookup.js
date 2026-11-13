/**
 * Lookup for the fact dialog of provenance_facts: research the fact in
 * Wikidata. Search for the item the fact is about, read its statements and
 * fill the fact from one of them. TYPO3 asks Wikidata (backend AJAX routes);
 * this module only renders the answers.
 */
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';

const MIN_QUERY_LENGTH = 2;
const DEBOUNCE_MS = 300;
const VALUES_PER_PROPERTY = 5;

export default function wikidataLookup({ panel, fill }) {
  // The dialog lives in the top frame of the backend; build elements there.
  const doc = panel.ownerDocument;
  const element = (tag, className = '', text = '') => {
    const node = doc.createElement(tag);
    if (className) node.className = className;
    if (text) node.textContent = text;
    return node;
  };

  const box = element('div', 'provenance-wikidata mb-3');
  const label = element('label', 'form-label', 'Look up in Wikidata');
  const input = element('input', 'form-control');
  input.type = 'search';
  input.placeholder = 'What is the fact about? For example Ashmolean Museum';
  input.id = label.htmlFor = 'provenance-wikidata-query';
  input.autocomplete = 'off';
  const message = element('div');
  const results = element('div', 'list-group mt-1');
  const statements = element('div', 'mt-2');
  box.append(label, input, message, results, statements);
  panel.append(box);

  const showMessage = (text, kind = 'warning') => {
    message.replaceChildren();
    if (text) message.append(element('div', `alert alert-${kind} mt-2 mb-0 py-2`, text));
  };

  const request = async (route, query) => {
    try {
      const response = await new AjaxRequest(TYPO3.settings.ajaxUrls[route]).withQueryArguments(query).get();
      return await response.resolve();
    } catch (error) {
      const answer = error.response ? await error.response.json().catch(() => ({})) : {};
      showMessage(answer.error ?? 'Wikidata is not reachable right now.');
      return null;
    }
  };

  let searchTimer = null;
  let latestSearch = 0;
  input.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const query = input.value.trim();
    if (query.length < MIN_QUERY_LENGTH) {
      results.replaceChildren();
      return;
    }
    searchTimer = setTimeout(() => search(query), DEBOUNCE_MS);
  });
  // Enter in the search field must not submit the fact form.
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') event.preventDefault();
  });

  const search = async (query) => {
    const current = ++latestSearch;
    const answer = await request('provenance_wikidata_search', { query });
    if (answer === null || current !== latestSearch) return;
    showMessage('');
    results.replaceChildren();
    if (answer.items.length === 0) {
      showMessage(`Wikidata has no item for "${query}".`, 'info');
      return;
    }
    for (const item of answer.items) {
      const button = element('button', 'list-group-item list-group-item-action py-1');
      button.type = 'button';
      button.append(element('strong', '', item.label), doc.createTextNode(` (${item.id})`));
      if (item.description) button.append(element('div', 'small text-body-secondary', item.description));
      button.addEventListener('click', () => pick(item));
      results.append(button);
    }
  };

  let latestPick = 0;
  const pick = async (item) => {
    const current = ++latestPick;
    results.replaceChildren();
    input.value = item.label;
    fill({ subject: item.uri });
    statements.replaceChildren(element('div', 'text-body-secondary', `Loading statements of ${item.label} …`));
    const answer = await request('provenance_wikidata_statements', { item: item.id });
    // A later pick wins, so statements always belong to the subject in the form.
    if (current !== latestPick) return;
    statements.replaceChildren();
    if (answer === null) return;
    showMessage('');
    renderStatements(answer);
  };

  const renderStatements = ({ item, rows }) => {
    const heading = element('div', 'fw-bold mb-1');
    const link = element('a', '', `${item.label} (${item.id})`);
    link.href = `https://www.wikidata.org/wiki/${item.id}`;
    link.target = '_blank';
    link.rel = 'noopener';
    heading.append(doc.createTextNode('Statements of '), link);
    statements.append(heading);
    if (rows.length === 0) {
      statements.append(element('div', 'text-body-secondary', 'Wikidata states nothing about this item that can become a fact.'));
      return;
    }

    const scroller = element('div', 'table-responsive');
    scroller.style.maxHeight = '16rem';
    scroller.style.overflowY = 'auto';
    const table = element('table', 'table table-sm table-hover mb-0');
    const body = element('tbody');
    const shown = new Map();
    for (const row of rows) {
      const count = (shown.get(row.property) ?? 0) + 1;
      shown.set(row.property, count);
      const tr = element('tr');
      if (count > VALUES_PER_PROPERTY) {
        tr.hidden = true;
        tr.dataset.more = row.property;
      }
      const reference = element('td', 'small');
      if (row.reference) {
        const source = element('a', '', new URL(row.reference).hostname);
        source.href = row.reference;
        source.target = '_blank';
        source.rel = 'noopener';
        source.title = row.reference;
        reference.append(source);
      }
      const use = element('button', 'btn btn-sm btn-default', 'Use');
      use.type = 'button';
      use.title = 'Fill predicate, value and evidence from this statement';
      // All four fields, so a value as URI from an earlier choice does not stay behind.
      use.addEventListener('click', () => fill(row.fill));
      const action = element('td', 'text-end');
      action.append(use);
      tr.append(element('td', 'small', row.propertyLabel), element('td', '', row.value), reference, action);
      body.append(tr);
    }
    table.append(body);
    scroller.append(table);
    statements.append(scroller);

    for (const [property, count] of shown) {
      if (count <= VALUES_PER_PROPERTY) continue;
      const more = element('button', 'btn btn-link btn-sm p-0', `Show all ${count} values of ${rows.find((row) => row.property === property).propertyLabel}`);
      more.type = 'button';
      more.addEventListener('click', () => {
        body.querySelectorAll(`[data-more="${property}"]`).forEach((tr) => { tr.hidden = false; });
        more.remove();
      });
      statements.append(more);
    }
  };
}
