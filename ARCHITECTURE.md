# Architecture Notes — EnergyLogix Commission Engine

---

## Formula Engine Design

### Why AST instead of eval()

The most important architectural decision in the system is how formula expressions are evaluated. The obvious shortcut — passing the expression string to PHP's `eval()` — was ruled out immediately for three reasons:

- **Security.** `eval()` executes arbitrary PHP code. An expression like `system('rm -rf /')` would run with the same privileges as the application. There is no safe way to sanitise arbitrary user input before passing it to `eval()`.
- **No validation before saving.** With `eval()` you cannot know whether a formula is valid until you actually run it. The system needs to validate at save time and report precise errors.
- **No audit trail.** `eval()` is a black box — you get a result, not a trace of how that result was computed.

The AST approach solves all three problems.

### Tokenizer

`FormulaParserService` first passes the raw expression string through a tokenizer that reads character by character and emits a flat list of typed tokens:

```
"(AnnualUsage * 0.05) + (ContractLength * 100)"

→ LPAREN, VARIABLE(AnnualUsage), OPERATOR(*), NUMBER(0.05), RPAREN,
   OPERATOR(+), LPAREN, VARIABLE(ContractLength), OPERATOR(*), NUMBER(100), RPAREN
```

Each token has a `type` and a `value`. Whitespace is discarded. Any character outside the allowed set throws an `InvalidArgumentException` immediately.

### Recursive Descent Parser

The token list is consumed by a recursive descent parser that builds a nested tree structure using a three-level grammar that naturally enforces operator precedence:

```
expression → term   ( ('+' | '-') term   )*   ← handles + and −
term       → factor ( ('*' | '/') factor )*   ← handles × and ÷
factor     → NUMBER | VARIABLE | '(' expression ')'
```

Because `term` is called inside `expression`, multiplication and division always become deeper (earlier evaluated) nodes in the tree. The parser handles parentheses by recursively re-entering `parseExpression` from `parseFactor`, so `(AnnualUsage + ContractLength) * 100` correctly places the addition at a deeper level than the multiplication.

The resulting AST looks like this for `(AnnualUsage * 0.05) + (ContractLength * 100)`:

```json
{
  "type": "BinaryOperation",
  "operator": "+",
  "left": {
    "type": "BinaryOperation",
    "operator": "*",
    "left":  { "type": "Variable", "name": "AnnualUsage" },
    "right": { "type": "Number",   "value": 0.05 }
  },
  "right": {
    "type": "BinaryOperation",
    "operator": "*",
    "left":  { "type": "Variable", "name": "ContractLength" },
    "right": { "type": "Number",   "value": 100 }
  }
}
```

After building the tree, `validateVariables` walks every node and checks that each `Variable` name is in the allowed list (the four system variables plus any calculated variable names for this formula). An unknown name throws a descriptive exception naming the offending identifier.

### Storage and evaluation

The AST is stored as JSON in the `formulas.ast_json` column alongside the human-readable expression. Storing both means the expression stays readable in the UI while the AST is always ready to evaluate without re-parsing.

`FormulaEvaluatorService` walks the stored AST recursively. Each `BinaryOperation` node evaluates its left and right subtrees first, applies the operator, records a human-readable step string, and returns both the numeric result and a label for use in parent nodes:

```
AnnualUsage * 0.05 = 14200.00
ContractLength * 100 = 3600.00
(14200.00) + (3600.00) = 17800.00
```

Sub-expression labels are wrapped in parentheses so the audit trail makes the order of operations explicit without the reader needing to understand the original expression.

---

## Service Layer Pattern

Controllers in this codebase are intentionally thin. Each controller method does exactly three things: validate the HTTP request, call a service, return a response. All business logic lives in `app/Services/`.

```
HTTP Request
    ↓
Controller — validates input, calls service, shapes response
    ↓
Service — owns the business logic
    ↓
Eloquent Models / other services
```

This separation has concrete benefits:

- **Testability.** A service can be instantiated directly in a test with `new FormulaEvaluatorService()` and called with plain PHP arrays. There is no HTTP layer to simulate, no request object to mock.
- **Reusability.** The same `CommissionService::calculate()` can be called from an HTTP controller, a queue job, an Artisan command, or a future webhook handler without any modification.
- **Readability.** A controller method that is ten lines long is immediately understandable. The complexity is in the service where it belongs and can be found by name.

---

## Input Agnosticism

The three formula engine services — `FormulaParserService`, `FormulaEvaluatorService`, and `DependencyResolverService` — accept only plain PHP primitives and arrays. None of them import or reference any Eloquent model.

```php
// Correct — plain string and array
$ast = $parser->parse('AnnualUsage * 0.05', ['BaseCommission']);

// Correct — plain arrays
$result = $evaluator->evaluate($ast, ['AnnualUsage' => 284000, 'ContractLength' => 36]);

// Correct — plain array of definitions
$sorted = $resolver->resolve([
    ['name' => 'BonusCommission', 'expression' => 'BaseCommission * 0.10'],
    ['name' => 'BaseCommission',  'expression' => 'AnnualUsage * 0.05'],
]);
```

The database boundary sits entirely in `CommissionService` and `SimulationService`. They fetch Eloquent models, extract the plain values, and pass those values to the formula engine. This means:

- The formula engine can be unit tested without a database connection at all.
- The services can accept input from a CSV import, a REST webhook, or a test fixture without any change to the engine code.
- If the ORM is ever swapped, only the two boundary services change — the formula engine is untouched.

---

## Dependency Resolution

Formulas can define intermediate calculated variables that reference each other. For example:

```
BaseCommission  = AnnualUsage * 0.05
BonusCommission = BaseCommission * 0.10
FinalCommission = BaseCommission + BonusCommission
```

`BonusCommission` depends on `BaseCommission`. `FinalCommission` depends on both. These must be evaluated in the right order — evaluating `FinalCommission` before `BaseCommission` would fail because `BaseCommission` is not yet in the values map.

`DependencyResolverService` models this as a directed graph where an edge from A to B means "A must be evaluated before B". It then runs **Kahn's algorithm** for topological sort:

1. Calculate the in-degree (number of unsatisfied dependencies) for every variable.
2. Add all zero-in-degree nodes to a queue — these have no dependencies and can run first.
3. Process the queue: take a node, add it to the sorted output, and decrement the in-degree of everything that depended on it. Any node whose in-degree reaches zero joins the queue.
4. Repeat until the queue is empty.

For the example above, the algorithm produces: `BaseCommission → BonusCommission → FinalCommission`.

**Cycle detection** falls out naturally: if the sorted output contains fewer nodes than the input, some nodes never reached in-degree zero — they are part of a cycle and the resolver throws a `RuntimeException` naming every variable involved. This check happens at formula-save time so a circular formula is rejected before it can be stored, and before it could ever cause an infinite loop at evaluation time.

---

## Immutable Audit Trail

`commission_calculations` records are designed to be append-only. Several layers enforce this:

- **Database.** The table has no `updated_at` column. There is no timestamp for a modification to land in.
- **Model.** `CommissionCalculation` sets `public $timestamps = false`, so Eloquent never tries to manage timestamps. There is no `update()` call anywhere in the codebase that targets this table.
- **Semantics.** A calculation record is a historical fact — it records what the formula was, what the contract values were, and what result was produced at a specific point in time. Changing it would falsify the audit trail.

**Input snapshot.** `input_values` stores the four raw contract values at calculation time, not a foreign key reference to the contract. If the contract is later amended — usage revised, risk score updated — the historical calculation is unaffected. The snapshot preserves what actually drove the commission decision.

**Calculation steps.** Each step is a human-readable string produced by the AST evaluator as it walks the tree:

```
AnnualUsage * 0.05 = 14200.00
ContractLength * 100 = 3600.00
(14200.00) + (3600.00) = 17800.00
```

A reviewer can follow the arithmetic from raw inputs to final result without understanding the formula expression or the AST. If an agent ever questions a commission figure, every number used in the calculation is traceable.

---

## Queue Architecture

Impact analysis simulates a formula against every contract in the database and computes aggregated totals. With tens of thousands of contracts, this could take several seconds or more — far too long for a synchronous HTTP response.

The simulation endpoint (`POST /api/v1/simulation/run`) does two things immediately and returns:

1. Creates an `impact_analyses` record with `status: pending`.
2. Dispatches `RunImpactAnalysisJob` to the queue.

The response is `202 Accepted` with the analysis ID. The HTTP cycle is complete in milliseconds regardless of how many contracts exist.

The frontend then polls `GET /api/v1/simulation/{id}` every two seconds. The polling endpoint reads the current state of the `impact_analyses` record and returns it — cheap, read-only. When the background job completes it updates the record to `complete` with the aggregated totals, and the next poll cycle picks that up.

If the job fails for any reason it catches the exception, marks the record `failed`, and re-throws so the queue worker logs the root cause. The next poll cycle returns `status: failed` and the UI shows an appropriate error.

The job is idempotent with respect to commission records — `SimulationService` never writes to `commission_calculations`. The simulation is always a pure read-and-compute operation.

---

## Role-Based Access Control

The system has two roles:

| Role | Can do |
|---|---|
| **admin** | Everything — create formulas, activate formulas, run simulations, calculate commission, view audit trail |
| **viewer** | Read-only — view formula list, view audit trail, calculate commission, poll simulation status |

**The backend is the real enforcement boundary.** Two Laravel Gates are defined in `AuthServiceProvider`:

- `manage-formulas` — passes only when `$user->role === 'admin'`
- `view-only` — passes for both roles

Every write operation in `FormulaController` and `SimulationController` calls `$this->authorize('manage-formulas')` as the first line of the method, before any validation or database work. A viewer hitting a write endpoint receives a `403 Forbidden` immediately.

**Vue UI restrictions are cosmetic.** The frontend hides the "Create New Formula", "Activate", and "Run Simulation" buttons from viewer-role users by checking `authStore.user?.role === 'admin'`. This improves the experience — a viewer doesn't see buttons that would fail — but it provides no security. A determined viewer could call the API directly and would still receive a 403 from the Gate check on the server.

This two-layer approach is intentional: the backend enforces security, the frontend enforces usability.
