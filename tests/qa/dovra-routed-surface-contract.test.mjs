import test from "node:test"
import assert from "node:assert/strict"
import { existsSync, readFileSync } from "node:fs"
import { resolve } from "node:path"
import { pathToFileURL } from "node:url"
import ts from "typescript"

const repositoryRoot = resolve(import.meta.dirname, "../..")

function readRepositoryFile(relativePath) {
    return readFileSync(resolve(repositoryRoot, relativePath), "utf8")
}

function collectRoutedSurfaceCalls(source) {
    const sourceFile = ts.createSourceFile(
        "dovra-routed-surface-contract.ts",
        source,
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TS,
    )
    const calls = []

    const visit = (node) => {
        if (
            ts.isCallExpression(node)
            && ts.isIdentifier(node.expression)
            && node.expression.text === "visitRoutedSurface"
        ) {
            const pathArgument = node.arguments[1]
            if (pathArgument && ts.isStringLiteralLike(pathArgument)) {
                calls.push(pathArgument.text)
            }
        }

        ts.forEachChild(node, visit)
    }

    visit(sourceFile)

    return calls
}

function collectNamedFunctionSource(source, functionName) {
    const sourceFile = ts.createSourceFile(
        "DirectoryIndex.jsx",
        source,
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.JSX,
    )
    let functionNode = null

    const visit = (node) => {
        if (ts.isFunctionDeclaration(node) && node.name?.text === functionName) {
            functionNode = node
        }

        ts.forEachChild(node, visit)
    }

    visit(sourceFile)

    assert.ok(functionNode, `${functionName} function is missing`)
    return source.slice(functionNode.pos, functionNode.end)
}

function assertDirectoryGuideHeading(source) {
    const directoryGuideSource = collectNamedFunctionSource(source, "DirectoryGuide")
    const title = "Pregunta con datos públicos"
    const escapedTitle = title.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")
    const headingPattern = new RegExp(`<h2\\b[^>]*>\\s*${escapedTitle}\\s*</h2>`, "u")
    const headingWithAttributesPattern = new RegExp(`<h2\\b([^>]*)>\\s*${escapedTitle}\\s*</h2>`, "u")

    assert.match(directoryGuideSource, headingPattern, "DirectoryGuide title must be a visible h2")
    assert.doesNotMatch(directoryGuideSource, new RegExp(`<h3\\b[^>]*>\\s*${escapedTitle}\\s*</h3>`, "u"))
    assert.doesNotMatch(directoryGuideSource, new RegExp(`<CardTitle\\b[^>]*>\\s*${escapedTitle}\\s*</CardTitle>`, "u"))

    const headingMatch = directoryGuideSource.match(headingWithAttributesPattern)
    assert.ok(headingMatch, "DirectoryGuide h2 title attributes are missing")
    for (const className of ["font-display", "text-2xl", "font-semibold", "leading-none", "tracking-tight"]) {
        assert.match(headingMatch[1], new RegExp(`className=["'][^"']*\\b${className}\\b[^"']*["']`, "u"), `DirectoryGuide h2 lost ${className} styling`)
    }
}

function assertRegistrationSemanticContract(authLayoutSource, wizardSource) {
    const authLayout = collectNamedFunctionSource(authLayoutSource, "AuthSplitLayout")
    const wizard = collectNamedFunctionSource(wizardSource, "RegisterTenantWizard")

    assert.match(authLayout, /<header\b[^>]*>[\s\S]*<AuthBrand\s*\/>[\s\S]*\{topActions\s*\?[\s\S]*<\/header>/u, "AuthSplitLayout top brand/actions wrapper must be a semantic header")
    assert.doesNotMatch(authLayout, /<div\b[^>]*>\s*<AuthBrand\s*\/>/u, "AuthSplitLayout must not use a div for the top brand/actions wrapper")

    const progressMatch = wizard.match(/<div\b([^>]*aria-label=["']Progreso de registro por pasos["'][^>]*)>/u)
    assert.ok(progressMatch, "registration progress group is missing")
    assert.match(progressMatch[1], /\brole=["']group["']/u, "registration progress aria-label must have role=group")

    assert.match(wizard, /<p\b[^>]*className=["'][^"']*\bmb-1\b[^"']*\bfont-semibold\b[^"']*\bleading-none\b[^"']*["'][^>]*>\s*Datos incompletos\s*<\/p>/u, "Datos incompletos must be non-heading semantic text with preserved styles")
    assert.doesNotMatch(wizard, /<(?:h5|AlertTitle)\b[^>]*>\s*Datos incompletos\s*<\/(?:h5|AlertTitle)>/u, "Datos incompletos must not be an h5 or AlertTitle")
}

test("critical browser specs navigate the routed Inertia surfaces", () => {
    const criticalSource = readRepositoryFile("tests/e2e/dovra-launch-critical.spec.ts")
    const accessibilitySource = readRepositoryFile("tests/e2e/dovra-accessibility.spec.ts")
    const routesSource = readRepositoryFile("routes/web.php")
    const directoryController = readRepositoryFile("app/Http/Controllers/Public/DirectoryController.php")
    const registrationController = readRepositoryFile("app/Http/Controllers/Auth/RegisterTenantController.php")
    const directoryPage = readRepositoryFile("resources/js/Pages/Directory/Index.jsx")
    assertDirectoryGuideHeading(directoryPage)
    assertRegistrationSemanticContract(
        readRepositoryFile("resources/js/Components/Auth/AuthSplitLayout.jsx"),
        readRepositoryFile("resources/js/Components/Auth/RegisterTenantWizard.jsx"),
    )
    for (const source of [criticalSource, accessibilitySource]) {
        const routedCalls = collectRoutedSurfaceCalls(source)
        assert.ok(routedCalls.includes("/directorio"), "directory route call is missing")
        assert.ok(routedCalls.includes("/registrar-negocio"), "registration route call is missing")
        assert.match(source, /assertReducedMotion\(page\b/u)
    }

    const routedHelperPath = resolve(repositoryRoot, "tests/e2e/support/dovra-routed-surface.ts")
    assert.equal(existsSync(routedHelperPath), true, "shared routed-surface helper is missing")
    const routedHelper = readFileSync(routedHelperPath, "utf8")

    assert.match(routesSource, /Route::get\(\s*["']\/directorio["']\s*,\s*\[DirectoryController::class,\s*["']index["']\]\s*\)/u)
    assert.match(routesSource, /Route::controller\(\\?App\\Http\\Controllers\\Auth\\RegisterTenantController::class\)/u)
    assert.match(routesSource, /Route::get\(\s*["']\/registrar-negocio["']\s*,\s*["']create["']\)\s*->name\(\s*["']tenant\.register["']\s*\)/u)
    assert.match(directoryController, /class DirectoryController\b/u)
    assert.match(directoryController, /public function index\s*\(/u)
    assert.match(directoryController, /Inertia::render\(\s*["']Directory\/Index["']/u)
    assert.match(registrationController, /class RegisterTenantController\b/u)
    assert.match(registrationController, /public function create\s*\(/u)
    assert.match(registrationController, /Inertia::render\(\s*["']Auth\/RegisterTenant["']/u)
    assert.match(routedHelper, /getComputedStyle\(/u)
    assert.match(routedHelper, /transitionDuration/u)
    assert.match(routedHelper, /animationDuration/u)

    for (const source of [criticalSource, accessibilitySource]) {
        assert.doesNotMatch(source, /page\.setContent\s*\(/u)
        assert.doesNotMatch(source, /buildMarkup|mountFixture|HARNESS_CONTRACT|HTML harness evidence only/u)
        assert.doesNotMatch(source, /dovra-program-10\.fixtures\.json|applicationSurface\s*:\s*false/u)
    }
})

test("routed surface contract ignores comments and string literals", () => {
    const fixture = [
        "// visitRoutedSurface(page, '/directorio')",
        "const copy = \"visitRoutedSurface(page, '/registrar-negocio')\";",
        "await visitRoutedSurface(page, '/registrar-negocio');",
    ].join("\n")

    assert.deepEqual(collectRoutedSurfaceCalls(fixture), ["/registrar-negocio"])
})

test("reduced motion contract accepts sub-millisecond CSS durations", async () => {
    const modulePath = resolve(repositoryRoot, "tests/e2e/support/reduced-motion-duration.mjs")
    assert.equal(existsSync(modulePath), true, "shared reduced-motion duration predicate is missing")

    const { isWithinReducedMotionThreshold } = await import(pathToFileURL(modulePath).href)
    assert.equal(isWithinReducedMotionThreshold("0s"), true)
    assert.equal(isWithinReducedMotionThreshold("0.01ms"), true)
    assert.equal(isWithinReducedMotionThreshold("1e-05s"), true)
    assert.equal(isWithinReducedMotionThreshold("+1e-05s"), true)
    assert.equal(isWithinReducedMotionThreshold("0s, 0.01ms"), true)
    assert.equal(isWithinReducedMotionThreshold("10ms"), false)
    assert.equal(isWithinReducedMotionThreshold("0.01ms, 10ms"), false)
    assert.equal(isWithinReducedMotionThreshold("1e-2s"), false)
    assert.equal(isWithinReducedMotionThreshold("-1e-05s"), false)
    assert.equal(isWithinReducedMotionThreshold("1e--05s"), false)
    assert.equal(isWithinReducedMotionThreshold("1e-05"), false)
    assert.equal(isWithinReducedMotionThreshold("1e309s"), false)
})

test("the routed merchant surface exposes a keyboard skip target", () => {
    const pageSource = readRepositoryFile("resources/js/Pages/Auth/RegisterTenant.jsx")
    const wizardSource = readRepositoryFile("resources/js/Components/Auth/RegisterTenantWizard.jsx")
    const layoutSource = readRepositoryFile("resources/js/Components/Auth/AuthSplitLayout.jsx")

    assert.match(pageSource, /<RegisterTenantWizard\s+\{\.\.\.props\}\s*\/>/u)
    assert.doesNotMatch(pageSource, /<main\b|<SkipLink\b/u)
    assert.match(wizardSource, /<AuthSplitLayout\b/u)
    assert.match(layoutSource, /import\s+\{\s*SkipLink\s*\}\s+from\s+["']@\/Components\/ui\/skip-link["']/u)
    assert.match(layoutSource, /<SkipLink\s*\/>/u)
    assert.match(layoutSource, /<main\b[^>]*\bid=["']main-content["'][^>]*\btabIndex=\{?-1\}?/u)
})
