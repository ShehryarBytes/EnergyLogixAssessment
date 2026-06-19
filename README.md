# EnergyLogix — Dynamic Commission Engine

## Project Overview

The EnergyLogix Commission Engine is an internal administration tool that allows energy brokers to define, version, and activate commission formulas without touching application code. Administrators write formulas as human-readable expressions — for example `(AnnualUsage * 0.05) + (ContractLength * 100)` — and the system parses them into a safe Abstract Syntax Tree that is evaluated against any contract record. Every calculation produces a permanent, tamper-proof audit record showing the exact input values and step-by-step arithmetic used to arrive at the result.

The system solves a real operational problem: commission structures change regularly in energy broking, but deploying code changes for every rate adjustment is slow, risky, and requires engineering involvement. This engine separates the formula logic from application code entirely. A formula can be drafted, validated, simulated against all existing contracts to preview the financial impact, and then activated with a single click — all without a deployment.

---

## Tech Stack

| Layer | Technology | Version |
|---|---|---|
| Backend | Laravel (PHP) | 13.x / PHP 8.3 |
| Frontend | Vue 3 (Composition API) | 3.5 |
| State management | Pinia | 3.0 |
| Routing (SPA) | Vue Router | 4.6 |
| CSS | Tailwind CSS | 3.4 |
| Database | MySQL | 8.x |
| Queue | Laravel database queue | — |
| Build tool | Vite | 8.x |
| Testing | Pest (PHPUnit) | 4.x |

---

## Setup Instructions

1. **Clone the repository and install PHP dependencies**
   ```bash
   git clone <repo-url> energylogix
   cd energylogix
   composer install
   ```

2. **Install Node dependencies**
   ```bash
   npm install
   ```

3. **Copy the environment file**
   ```bash
   cp .env.example .env
   ```

4. **Configure the database** — open `.env` and set your MySQL credentials:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=energylogix_commission
   DB_USERNAME=root
   DB_PASSWORD=
   ```
   Then create the database:
   ```sql
   CREATE DATABASE energylogix_commission;
   ```

5. **Generate the application key**
   ```bash
   php artisan key:generate
   ```

6. **Run migrations and seed test data**
   ```bash
   php artisan migrate --seed
   ```
   This creates all tables and seeds 15 sample contracts and two user accounts:

   | Email | Password | Role |
   |---|---|---|
   | admin@energylogix.com | password | admin |
   | viewer@energylogix.com | password | viewer |

7. **Build frontend assets** (or `npm run dev` for hot-reload during development)
   ```bash
   npm run build
   ```

8. **Start the queue worker** — required for impact analysis simulations
   ```bash
   php artisan queue:work
   ```

9. **Start the development server**
   ```bash
   php artisan serve
   ```
   The application is available at **http://localhost:8000**.

---

## Running Tests

```bash
php artisan test
```

94 tests, 244 assertions — covers authentication, formula CRUD and versioning, commission calculation, simulation jobs, contract management, and all three formula engine services.

---

## Key Features

- **Formula Builder** — write commission formulas as text expressions with optional calculated variables; live validation parses and reports errors before anything is saved
- **Versioning and Activation** — every save is a new immutable version; activating a formula automatically archives the previous active one; only one formula can be active at any time
- **Commission Calculator** — select any contract, calculate commission against the active formula, and see a step-by-step breakdown of every arithmetic operation that produced the result
- **Impact Simulation** — run a dry-run of any draft formula against all contracts as a background job; a polling endpoint updates the UI when the job completes without blocking the HTTP response
- **Audit Trail** — every commission calculation is permanently recorded with a snapshot of the contract values at calculation time and an ordered list of every step; records are never modified after creation
