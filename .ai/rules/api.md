---
paths:
  - 'app/Http/Controllers/Api/**'
---

# Api

## All API list responses go through App\Support\ApiResponse — never hand-build an envelope
Paginated lists MUST return ApiResponse::paginated($paginator) and non-paginated/bounded lists ApiResponse::collection($items). The envelope is {data, links, meta} (Laravel Resource Collection shape) so integrators write one handler. Do NOT return a paginator directly (leaks first_page_url/links/current_page at the top level) and do NOT hand-build {data, pagination} — the API previously shipped all three shapes. Extra top-level keys go via the $extra arg. ApiResponse::paginated is @template'd on the item type because psalm's paginator/Collection templates are INVARIANT: a LengthAwarePaginator<int, Machine> will not satisfy a <int, mixed> param. Frozen by tests/Feature/Api/ListEnvelopeContractTest — add every new list endpoint to its data provider.

## One page size, one error shape
Every list endpoint paginates with `PageSize::from($request)` (default 15, max 100) and validates `'per_page' => 'nullable|integer|min:1|max:100'` plus `'page' => 'nullable|integer|min:1'`. Do NOT hardcode a page size or read per_page by hand: seven of fifteen list endpoints once ignored per_page entirely while the docs promised every list accepted it. The rule is written as a LITERAL at each call site, not as PageSize::RULE_LITERAL — OpenApiGenerator builds the published parameter list by regex-reading rule strings out of controller source, so a constant would parse to nothing and per_page would vanish from the docs; PageSizeContractTest compares the literals against PageSize::MAX so the duplication cannot drift.

Validation always goes through `$request->validate([...])`, never `Validator::make()` + a hand-built response. The API previously answered an invalid body in three shapes ({message,errors}, {errors}, and {success:false,errors}), so a client needed three error handlers for one failure. `validate()` throws ValidationException, which Laravel renders as {message, errors} — no `success` flag, the status code is the signal. Frozen by tests/Feature/Api/ValidationErrorShapeTest and PageSizeContractTest.
