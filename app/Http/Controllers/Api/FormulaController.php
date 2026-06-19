<?php

namespace App\Http\Controllers\Api;

use App\Enums\FormulaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FormulaRequest;
use App\Http\Resources\FormulaResource;
use App\Models\Formula;
use App\Services\Formula\DependencyResolverService;
use App\Services\Formula\FormulaParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class FormulaController extends Controller
{
    public function __construct(
        private readonly FormulaParserService      $parser,
        private readonly DependencyResolverService $resolver,
    ) {}

    /**
     * GET /api/v1/formulas
     * Paginated list of all formula versions.
     */
    public function index(): AnonymousResourceCollection
    {
        $formulas = Formula::orderByDesc('created_at')->paginate(20);

        return FormulaResource::collection($formulas);
    }

    /**
     * POST /api/v1/formulas
     * Create a new formula draft.
     * Requires manage-formulas gate (admin only).
     */
    public function store(FormulaRequest $request): JsonResponse
    {
        $this->authorize('manage-formulas');

        $variableNames = collect($request->variables ?? [])->pluck('name')->toArray();

        // Parse and validate the main formula expression
        try {
            $ast = $this->parser->parse($request->expression, $variableNames);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Parse each variable expression and resolve a safe execution order
        $parsedVariables = [];
        foreach ($request->variables ?? [] as $varDef) {
            try {
                $parsedVariables[] = [
                    'name'       => $varDef['name'],
                    'expression' => $varDef['expression'],
                    'ast_json'   => $this->parser->parse($varDef['expression'], $variableNames),
                ];
            } catch (InvalidArgumentException $e) {
                return response()->json([
                    'message' => "Variable '{$varDef['name']}': " . $e->getMessage(),
                ], 422);
            }
        }

        if (!empty($parsedVariables)) {
            try {
                $parsedVariables = $this->resolver->resolve($parsedVariables);
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        // Version number is per formula name — first version for a new name is 1
        $version = (Formula::where('name', $request->name)->max('version') ?? 0) + 1;

        $formula = DB::transaction(function () use ($request, $ast, $parsedVariables, $version): Formula {
            $formula = Formula::create([
                'name'       => $request->name,
                'version'    => $version,
                'expression' => $request->expression,
                'ast_json'   => $ast,
                'status'     => FormulaStatus::Draft,
                'created_by' => auth()->user()->email,
            ]);

            foreach ($parsedVariables as $index => $variable) {
                $formula->variables()->create([
                    'variable_name' => $variable['name'],
                    'expression'    => $variable['expression'],
                    'ast_json'      => $variable['ast_json'],
                    'sort_order'    => $index,
                ]);
            }

            return $formula;
        });

        return (new FormulaResource($formula->load('variables')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/formulas/{id}
     * Single formula with all its variables.
     */
    public function show(string $id): FormulaResource
    {
        $formula = Formula::with('variables')->findOrFail($id);

        return new FormulaResource($formula);
    }

    /**
     * POST /api/v1/formulas/{id}/activate
     * Activate this formula version, archiving the current active one.
     * Requires manage-formulas gate (admin only).
     */
    public function activate(string $id): JsonResponse
    {
        $this->authorize('manage-formulas');

        $formula = Formula::findOrFail($id);

        if ($formula->status === FormulaStatus::Active) {
            return response()->json(['message' => 'This formula version is already active.'], 422);
        }

        DB::transaction(function () use ($formula): void {
            // Archive the current active formula (only one active at a time)
            Formula::where('status', FormulaStatus::Active->value)
                ->update(['status' => FormulaStatus::Archived->value]);

            $formula->update([
                'status'       => FormulaStatus::Active,
                'activated_at' => now(),
            ]);
        });

        return response()->json(
            new FormulaResource($formula->fresh()->load('variables'))
        );
    }

    /**
     * POST /api/v1/formulas/{id}/validate
     * Dry-run validation — parses the expression and resolves variables without saving.
     * Returns the AST preview on success, or a 422 with the specific error.
     * Requires manage-formulas gate (admin only).
     */
    public function validate(Request $request): JsonResponse
    {
        $this->authorize('manage-formulas');

        $request->validate([
            'expression'             => ['required', 'string'],
            'variables'              => ['sometimes', 'array'],
            'variables.*.name'       => ['required', 'string'],
            'variables.*.expression' => ['required', 'string'],
        ]);

        $variableNames = collect($request->variables ?? [])->pluck('name')->toArray();

        try {
            $ast = $this->parser->parse($request->expression, $variableNames);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $parsedVariables = [];
        foreach ($request->variables ?? [] as $varDef) {
            try {
                $parsedVariables[] = [
                    'name'       => $varDef['name'],
                    'expression' => $varDef['expression'],
                    'ast'        => $this->parser->parse($varDef['expression'], $variableNames),
                ];
            } catch (InvalidArgumentException $e) {
                return response()->json([
                    'message' => "Variable '{$varDef['name']}': " . $e->getMessage(),
                ], 422);
            }
        }

        if (!empty($parsedVariables)) {
            try {
                // Re-key for resolver (expects 'ast_json', returns same structure)
                $forResolver = array_map(
                    fn ($v) => ['name' => $v['name'], 'expression' => $v['expression']],
                    $parsedVariables
                );
                $resolved      = $this->resolver->resolve($forResolver);
                $resolvedNames = array_column($resolved, 'name');

                // Sort parsedVariables to match the resolved execution order
                usort(
                    $parsedVariables,
                    fn ($a, $b) => array_search($a['name'], $resolvedNames)
                        <=> array_search($b['name'], $resolvedNames)
                );
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json([
            'valid'     => true,
            'ast'       => $ast,
            'variables' => array_map(fn ($v, $i) => [
                'name'       => $v['name'],
                'expression' => $v['expression'],
                'ast'        => $v['ast'],
                'sort_order' => $i,
            ], $parsedVariables, array_keys($parsedVariables)),
        ]);
    }
}
