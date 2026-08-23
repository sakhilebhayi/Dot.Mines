---
paths:
  - 'app/Http/Controllers/Api/**'
---

# Api

## All API list responses go through App\Support\ApiResponse — never hand-build an envelope
Paginated lists MUST return ApiResponse::paginated($paginator) and non-paginated/bounded lists ApiResponse::collection($items). The envelope is {data, links, meta} (Laravel Resource Collection shape) so integrators write one handler. Do NOT return a paginator directly (leaks first_page_url/links/current_page at the top level) and do NOT hand-build {data, pagination} — the API previously shipped all three shapes. Extra top-level keys go via the $extra arg. ApiResponse::paginated is @template'd on the item type because psalm's paginator/Collection templates are INVARIANT: a LengthAwarePaginator<int, Machine> will not satisfy a <int, mixed> param. Frozen by tests/Feature/Api/ListEnvelopeContractTest — add every new list endpoint to its data provider.
