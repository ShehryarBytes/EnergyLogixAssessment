# EnergyLogix — Dynamic Commission Engine

## What this project is

A technical assessment submission for EnergyLogix (Senior Laravel + Vue Developer role).
The system allows administrators to create and manage commission formulas without changing
application code. Energy brokers earn commission based on customer contracts, and the
formulas must be configurable, versioned, and auditable.

This is an assessment — code quality, architecture decisions, and test coverage are all
being evaluated. Favour clarity, correctness, and well-structured code over shortcuts.
Add brief inline comments on any non-obvious decisions.

---

## Tech stack

- **Backend:** Laravel 11 (PHP 8.3+)
- **Frontend:** Vue 3 (Composition API, no Options API)
- **State management:** Pinia
- **Routing:** Vue Router 4 (SPA, catch-all in web.php)
- **HTTP client:** Axios, abstracted behind a service layer at resources/js/services/api.js
- **Database:** MySQL — database name: `energylogix_commission`
- **Queue:** Laravel database queue (QUEUE_CONNECTION=database)
- **Testing:** PHPUnit — write tests as standard PHPUnit test classes
- **API style:** REST, versioned under /api/v1
- **Build tool:** Vite

---

## Architecture decisions — read carefully before writing any code

### 1. Formula evaluation uses an AST (Abstract Syntax Tree) — never eval()

This is the most important decision in the entire system. When an administrator
saves a formula like `(AnnualUsage * 0.05) + (ContractLength * 100)`, the system
must NOT evaluate it with PHP's eval(). Instead:

- Parse the formula string into a token list (tokenizer)
- Build the token list into an AST (recursive descent parser)
- Store the AST as JSON in the database alongside the human-readable expression
- Evaluate the formula by walking the AST tree node by node with input values
- Each node walk step is recorded as a calculation step for the audit trail

This approach gives us: safe evaluation, validation before saving, step-by-step
audit trail, and a foundation for circular dependency detection.

### 2. Service layer — thin controllers, fat services

Controllers handle only HTTP concerns: validate the request, call the right service,
return the response. All business logic lives in services under app/Services/.

Service structure:
- app/Services/Formula/FormulaParserService.php — tokenise + build AST
- app/Services/Formula/FormulaEvaluatorService.php — walk AST with input values
- app/Services/Formula/DependencyResolverService.php — topological sort, cycle detection
- app/Services/CommissionService.php — fetch active formula, run evaluator, store result
- app/Services/SimulationService.php — dry-run formula against all contracts

### 3. Formulas are immutable and versioned

Once a formula version is saved, its expression and AST are never modified.
Editing creates a new version. Only one version can be active at a time.
Status values: draft, active, archived.

### 4. Commission calculations are immutable once written

A commission_calculations record is a permanent audit record. It stores a
snapshot of input values and calculation steps at the time of calculation.
It must never be updated after creation.

### 5. Simulation never touches commission records

Impact analysis (simulation) is always a dry-run. It reads contracts and
runs the formula evaluator in memory. It writes to impact_analyses table only.
It must never write to commission_calculations.

### 6. Dependency resolution uses topological sort

When a formula contains calculated variables that depend on other variables
(e.g. BaseCommission → BonusCommission → FinalCommission), the system must:
- Build a directed graph of variable dependencies
- Run a topological sort (Kahn's algorithm or DFS)
- Detect cycles and reject the formula if any cycle is found
- Execute variables in resolved order during evaluation

### 7. Money values use DECIMAL(15,4)

Never floats. Commission results, contract values, usage figures — all DECIMAL(15,4).

### 8. Queue jobs for heavy operations

Impact analysis against potentially thousands of contracts runs as a queued job
(RunImpactAnalysisJob). The API endpoint dispatches the job and returns the
impact_analysis id immediately. A polling endpoint lets the frontend check status.

---

## Domain knowledge

### Allowed formula variables (system-level, always available)

- AnnualUsage — the annual energy usage from the contract
- ContractValue — the total monetary value of the contract
- ContractLength — duration of the contract (in months)
- RiskScore — a numeric risk rating on the contract

### Calculated variables (administrator-defined, formula-scoped)

Administrators can define intermediate variables within a formula set, for example:
  BaseCommission = AnnualUsage * 0.05
  BonusCommission = BaseCommission * 0.10
  FinalCommission = BaseCommission + BonusCommission

These are stored in formula_variables table linked to the formula.

### Formula versions

Each time a formula is created, it gets version number 1. Each subsequent save
increments the version. The version number is per-formula-name, not global.

---

## Database schema

### formulas
- id (BIGINT, auto-increment PK)
- name (string) — human label e.g. "Standard Commission Q1 2025"
- version (int, default 1)
- expression (text) — human-readable formula string
- ast_json (json) — parsed AST stored for evaluation
- status (enum: draft, active, archived, default: draft)
- activated_at (timestamp, nullable)
- created_by (string, nullable) — who created this version
- timestamps

### formula_variables
- id (BIGINT, auto-increment PK)
- formula_id (FK → formulas)
- variable_name (string) — e.g. "BaseCommission"
- expression (text) — e.g. "AnnualUsage * 0.05"
- ast_json (json)
- sort_order (int) — resolved execution order after topological sort
- timestamps

### contracts (test data for calculations)
- id (BIGINT, auto-increment PK)
- customer_name (string)
- annual_usage (DECIMAL 15,4)
- contract_value (DECIMAL 15,4)
- contract_length (int) — months
- risk_score (DECIMAL 5,2)
- timestamps

### commission_calculations (immutable audit records)
- id (BIGINT, auto-increment PK)
- contract_id (FK → contracts)
- formula_id (FK → formulas)
- input_values (json) — snapshot of contract values at calculation time
- calculation_steps (json) — step-by-step AST walk result
- result (DECIMAL 15,4)
- calculated_at (timestamp)

### impact_analyses (simulation results — never modifies commission_calculations)
- id (BIGINT, auto-increment PK)
- formula_id (FK → formulas)
- triggered_by (string, nullable)
- affected_contracts (int, nullable)
- current_total (DECIMAL 15,4, nullable)
- new_total (DECIMAL 15,4, nullable)
- difference (DECIMAL 15,4, nullable)
- status (enum: pending, complete, failed, default: pending)
- timestamps

---

## Folder structure

```
app/
  Http/Controllers/Api/
    FormulaController.php
    CommissionController.php
    SimulationController.php
    AuditController.php
    ContractController.php
  Services/
    Formula/
      FormulaParserService.php
      FormulaEvaluatorService.php
      DependencyResolverService.php
    CommissionService.php
    SimulationService.php
  Jobs/
    RunImpactAnalysisJob.php
  Models/
    Formula.php
    FormulaVariable.php
    Contract.php
    CommissionCalculation.php
    ImpactAnalysis.php

resources/js/
  views/
    HomeView.vue
    FormulaListView.vue
    FormulaBuilderView.vue
    CommissionCalculatorView.vue
    SimulationView.vue
    AuditTrailView.vue
  stores/
    formula.js
    commission.js
  composables/
    useFormula.js
    useSimulation.js
  router/
    index.js
  services/
    api.js
  components/
    (shared components go here)
  App.vue
  app.js
```

---

## API routes (all under /api/v1)

- GET    /formulas                      — list all formula versions
- POST   /formulas                      — create a new formula (status: draft)
- GET    /formulas/{id}                 — get single formula with variables
- POST   /formulas/{id}/activate        — activate this version (archives current active)
- POST   /formulas/{id}/validate        — validate formula expression, return errors or AST preview
- POST   /commission/calculate          — calculate commission for a contract using active formula
- GET    /commission/history            — list all commission_calculations
- GET    /commission/history/{id}       — single calculation with full audit steps
- POST   /simulation/run               — dispatch impact analysis job for a formula_id
- GET    /simulation/{id}              — poll simulation status and results
- GET    /audit                        — list commission_calculations with formula version info
- GET    /audit/{id}                   — full audit record: formula, inputs, steps, result
- GET    /contracts                    — list contracts
- POST   /contracts                    — create test contract
- PUT    /contracts/{id}               — update test contract
- DELETE /contracts/{id}               — delete test contract

---

## Common commands

- php artisan serve — start the development server
- npm run dev — start Vite for frontend hot-reload
- php artisan migrate — run migrations
- php artisan migrate:fresh --seed — drop, rebuild, and seed with test data
- php artisan queue:work — process queued jobs (needed for simulation)
- php artisan test — run the PHPUnit test suite

---

## Conventions

- Controllers must be thin — validate, delegate to service, return response
- All business logic lives in app/Services/
- Use Laravel Form Request classes for validation on any endpoint that accepts data
- Use Eloquent relationships; avoid manual joins
- API responses always use Laravel API Resources for consistent JSON structure
- Money is always DECIMAL(15,4) — no floats anywhere
- Never use eval() for formula evaluation — always walk the AST
- Never modify commission_calculations after creation
- Simulation runs must never write to commission_calculations
- Write PHPUnit tests as test classes (not Pest functions)
- Name things clearly — this is an assessment, readability is being scored
